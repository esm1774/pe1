<?php
/**
 * PE Smart School - Assessments API
 */

function getAssessments() {
    $user = requireLogin();
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) {
        jsonError('معرف الفصل مطلوب');
    }

    try {
        $db = getDB();
        
        // Get students in class
        $students = $db->prepare("SELECT id, name, student_number FROM students WHERE class_id = ? AND active = 1 ORDER BY name ASC");
        $students->execute([$classId]);
        $studentList = $students->fetchAll();

        // Get assessments for these students
        $assessments = $db->prepare("SELECT * FROM student_assessments WHERE student_id IN (SELECT id FROM students WHERE class_id = ?) ORDER BY assessment_date DESC");
        $assessments->execute([$classId]);
        $assessmentData = $assessments->fetchAll();

        // Group by student
        $grouped = [];
        foreach ($studentList as $s) {
            $grouped[$s['id']] = [
                'student_id' => $s['id'],
                'name' => $s['name'],
                'student_number' => $s['student_number'],
                'quiz' => 0,
                'project' => 0,
                'final_exam' => 0
            ];
        }

        foreach ($assessmentData as $a) {
            if (isset($grouped[$a['student_id']])) {
                // For now, we take the latest or a specific one? 
                // Usually there's one per semester. Let's just track the value.
                $grouped[$a['student_id']][$a['type']] = (float)$a['score'];
            }
        }

        // Get school max scores
        $sid = schoolId();
        $weights = $db->prepare("SELECT quiz_max, project_max, final_exam_max FROM school_grading_weights WHERE school_id = ?");
        $weights->execute([$sid]);
        $w = $weights->fetch();
        $maxScores = [
            'quiz' => (int)($w['quiz_max'] ?? 10),
            'project' => (int)($w['project_max'] ?? 10),
            'final_exam' => (int)($w['final_exam_max'] ?? 10)
        ];

        jsonSuccess([
            'students' => array_values($grouped),
            'max_scores' => $maxScores
        ]);
    } catch (Exception $e) {
        jsonError($e->getMessage());
    }
}

function saveAssessments() {
    $user = requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
    
    $data = getPostData();
    if (!$data || !isset($data['scores'])) {
        jsonError('بيانات غير مكتملة');
    }

    try {
        $db = getDB();
        $sid = schoolId();

        // Get school max scores for validation
        $weights = $db->prepare("SELECT quiz_max, project_max, final_exam_max FROM school_grading_weights WHERE school_id = ?");
        $weights->execute([$sid]);
        $w = $weights->fetch();
        $max = [
            'quiz' => (int)($w['quiz_max'] ?? 10),
            'project' => (int)($w['project_max'] ?? 10),
            'final_exam' => (int)($w['final_exam_max'] ?? 10)
        ];

        $db->beginTransaction();

        $stmtInsert = $db->prepare("INSERT INTO student_assessments (student_id, type, score, max_score, assessment_date, recorded_by) 
                                   VALUES (?, ?, ?, ?, CURRENT_DATE, ?) 
                                   ON DUPLICATE KEY UPDATE score = VALUES(score), max_score = VALUES(max_score), updated_at = CURRENT_TIMESTAMP");

        foreach ($data['scores'] as $entry) {
            $studentId = (int)$entry['student_id'];
            $recordedBy = (int)$user['id'];
            
            $types = ['quiz', 'project', 'final_exam'];
            foreach ($types as $type) {
                if (isset($entry[$type])) {
                    $score = (float)$entry[$type];
                    $maxVal = $max[$type];
                    if ($score > $maxVal) {
                        throw new Exception("الدرجة المدخلة ($score) لنوع ($type) تتجاوز الدرجة العظمى ($maxVal)");
                    }
                    $stmtInsert->execute([$studentId, $type, $score, $maxVal, $recordedBy]);
                }
            }
        }

        $db->commit();
        jsonSuccess(null, 'تم حفظ الدرجات بنجاح');
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        jsonError($e->getMessage());
    }
}
