<?php
/**
 * PE Smart School System - Students API
 */

function getStudents() {
    requireLogin();
    $db = getDB();
    $classId         = getParam('class_id');
    $gradeId         = getParam('grade_id');
    $search          = getParam('search', '');
    $teacherClassIds = getTeacherClassIds();
    $sid = schoolId();

    // Validate single-class access
    if ($classId && !canAccessClass((int)$classId)) {
        jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);
    }
    // Teacher with no classes → empty result
    if ($teacherClassIds !== null && empty($teacherClassIds)) {
        jsonSuccess([]);
        return;
    }

    $params = [];
    $where = '';

    // SaaS: scope to school
    if ($sid) { $where .= " AND s.school_id = ?"; $params[] = $sid; }
    if ($classId) {
        $where .= " AND s.class_id = ?";
        $params[] = $classId;
    } elseif ($gradeId) {
        $where .= " AND c.grade_id = ?";
        $params[] = $gradeId;
    }

    // Apply teacher class restriction if not admin
    if ($teacherClassIds !== null && !$classId) {
        $ph     = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $where .= " AND s.class_id IN ($ph)";
        $params = array_merge($params, $teacherClassIds);
    }

    if ($search) {
        $where .= " AND (s.name LIKE ? OR s.student_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $queries = [
        "SELECT s.id, s.name, s.student_number, s.class_id, s.active,
            s.date_of_birth, s.blood_type, s.guardian_phone, s.medical_notes,
            c.name as class_name, c.grade_id, g.name as grade_name,
            CONCAT(g.name, ' - ', c.name) as full_class_name,
            TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age,
            (SELECT COUNT(*) FROM student_health sh WHERE sh.student_id = s.id AND sh.is_active = 1) as health_alerts
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE s.active = 1 $where ORDER BY s.name",

        "SELECT s.id, s.name, s.student_number, s.class_id, s.active,
            s.date_of_birth, s.blood_type, s.guardian_phone, s.medical_notes,
            c.name as class_name, c.grade_id, g.name as grade_name,
            CONCAT(g.name, ' - ', c.name) as full_class_name,
            TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age,
            0 as health_alerts
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE s.active = 1 $where ORDER BY s.name",

        "SELECT s.id, s.name, s.student_number, s.class_id,
            c.name as class_name, c.grade_id, g.name as grade_name,
            CONCAT(g.name, ' - ', c.name) as full_class_name,
            NULL as date_of_birth, NULL as blood_type, NULL as guardian_phone,
            NULL as medical_notes, 0 as age, 0 as health_alerts
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE s.active = 1 $where ORDER BY s.name"
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            jsonSuccess($stmt->fetchAll());
            return;
        } catch (PDOException $e) {
            continue;
        }
    }
    jsonError('خطأ في جلب بيانات الطلاب');
}

function saveStudent() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['name', 'student_number', 'class_id']);
    $db      = getDB();
    $id      = $data['id'] ?? null;
    $name    = sanitize($data['name']);
    $studentNumber = sanitize($data['student_number']);
    $classId = (int)$data['class_id'];
    $sid     = schoolId();

    // Validate class ownership
    if (!canAccessClass($classId)) {
        jsonError('لا تملك صلاحية إضافة طالب في هذا الفصل', 403);
    }

    // Duplicate check scoped to school
    $dupSql = "SELECT id FROM students WHERE student_number = ? AND id != ? AND active = 1";
    $dupParams = [$studentNumber, $id ?? 0];
    if ($sid) { $dupSql .= " AND school_id = ?"; $dupParams[] = $sid; }
    $stmt = $db->prepare($dupSql);
    $stmt->execute($dupParams);
    if ($stmt->fetch()) jsonError('رقم الطالب مستخدم بالفعل');

    $dob = !empty($data['date_of_birth']) ? sanitize($data['date_of_birth']) : null;
    $bloodType = !empty($data['blood_type']) ? sanitize($data['blood_type']) : null;
    $guardianPhone = !empty($data['guardian_phone']) ? sanitize($data['guardian_phone']) : null;
    $medicalNotes = !empty($data['medical_notes']) ? sanitize($data['medical_notes']) : null;
    $password = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;

    try {
        if ($id) {
            $sql = "UPDATE students SET name=?, student_number=?, class_id=?, date_of_birth=?, blood_type=?, guardian_phone=?, medical_notes=?";
            $params = [$name, $studentNumber, $classId, $dob, $bloodType, $guardianPhone, $medicalNotes];
            if ($password) {
                $sql .= ", password=?";
                $params[] = $password;
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);
        } else {
            Subscription::requireLimit('students');
            // New student default password
            if (!$password) {
                $password = password_hash($studentNumber, PASSWORD_DEFAULT);
            }
            $db->prepare("INSERT INTO students (school_id, name, student_number, class_id, date_of_birth, blood_type, guardian_phone, medical_notes, password) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$sid, $name, $studentNumber, $classId, $dob, $bloodType, $guardianPhone, $medicalNotes, $password]);
            $id = $db->lastInsertId();
        }
        jsonSuccess(['id' => $id], 'تم حفظ بيانات الطالب بنجاح');
    } catch (PDOException $e) {
        jsonError('خطأ في حفظ الطالب: ' . $e->getMessage());
    }
}

function deleteStudent() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $db = getDB();
    // Validate the student belongs to an accessible class
    $student = $db->prepare("SELECT class_id FROM students WHERE id = ? AND active = 1");
    $student->execute([$id]);
    $s = $student->fetch();
    if ($s && !canAccessClass((int)$s['class_id'])) {
        jsonError('لا تملك صلاحية حذف هذا الطالب', 403);
    }
    $db->prepare("UPDATE students SET active = 0 WHERE id = ?")->execute([$id]);
    logActivity('delete', 'student', $id);
    jsonSuccess(null, 'تم حذف الطالب');
}

/**
/**
 * Import Students from CSV/Excel - VERSION CORRIGEE (Memory-Efficient)
 */
function importStudents() {
    requireRole(['admin', 'teacher']);
    
    // Check if file uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف أكبر من المسموح',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف أكبر من المسموح',
            UPLOAD_ERR_PARTIAL => 'لم يتم رفع الملف بالكامل',
            UPLOAD_ERR_NO_FILE => 'لم يتم اختيار ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد مؤقت غير موجود',
            UPLOAD_ERR_CANT_WRITE => 'فشل في كتابة الملف',
        ];
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        jsonError($errors[$code] ?? 'خطأ في رفع الملف');
    }
    
    $file = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Validate extension
    if (!in_array($ext, ['csv', 'txt'])) {
        jsonError('يجب رفع ملف CSV أو TXT فقط. يمكنك حفظ ملف Excel كـ CSV');
    }
    
    // --- بداية التعديل: قراءة الملف سطراً بسطر ---
    
    $handle = fopen($file, 'r');
    if (!$handle) {
        jsonError('لا يمكن فتح الملف للقراءة');
    }

    // Read and process header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        jsonError('الملف فارغ أو لا يمكن قراءته');
    }
    
    // Remove BOM if present from the first header cell
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $header = array_map('trim', $header);

    // Map Arabic/English column names to fields
    $columnMap = [
        'اسم الطالب' => 'name', 'الاسم' => 'name', 'name' => 'name', 'student_name' => 'name',
        'رقم الطالب' => 'student_number', 'الرقم' => 'student_number', 'student_number' => 'student_number', 'number' => 'student_number',
        'رمز الصف' => 'grade_code', 'الصف' => 'grade_code', 'grade_code' => 'grade_code', 'grade' => 'grade_code',
        'رقم الفصل' => 'section', 'الفصل' => 'section', 'section' => 'section', 'class' => 'section',
        'تاريخ الميلاد' => 'date_of_birth', 'الميلاد' => 'date_of_birth', 'date_of_birth' => 'date_of_birth', 'dob' => 'date_of_birth',
        'فصيلة الدم' => 'blood_type', 'blood_type' => 'blood_type', 'blood' => 'blood_type',
        'رقم ولي الأمر' => 'guardian_phone', 'الجوال' => 'guardian_phone', 'guardian_phone' => 'guardian_phone', 'phone' => 'guardian_phone',
        'ملاحظات طبية' => 'medical_notes', 'ملاحظات' => 'medical_notes', 'medical_notes' => 'medical_notes', 'notes' => 'medical_notes',
        'كلمة المرور' => 'password', 'password' => 'password', 'pass' => 'password'
    ];
    
    // Build field index mapping
    $fieldIndex = [];
    foreach ($header as $i => $col) {
        $colLower = mb_strtolower(trim($col));
        if (isset($columnMap[$colLower])) {
            $fieldIndex[$columnMap[$colLower]] = $i;
        }
    }
    
    // Validate required fields
    if (!isset($fieldIndex['name'])) {
        fclose($handle);
        jsonError('عمود "اسم الطالب" مطلوب في الملف. الأعمدة الموجودة: ' . implode(', ', $header));
    }
    if (!isset($fieldIndex['student_number'])) {
        fclose($handle);
        jsonError('عمود "رقم الطالب" مطلوب في الملف. الأعمدة الموجودة: ' . implode(', ', $header));
    }
    
    // --- نهاية التعديل الأولي ---

    $db = getDB();
    
    // Cache grades and classes for lookup (scoped to school)
    $grades = [];
    $gradeSql = "SELECT id, code, name FROM grades WHERE active = 1";
    if ($sid) $gradeSql .= " AND school_id = $sid";
    $stmt = $db->query($gradeSql);
    foreach ($stmt->fetchAll() as $g) {
        $grades[mb_strtolower($g['code'])] = $g['id'];
        $grades[mb_strtolower($g['name'])] = $g['id'];
    }
    
    $classes = [];
    $classSql = "SELECT id, grade_id, section, name FROM classes WHERE active = 1";
    if ($sid) $classSql .= " AND school_id = $sid";
    $stmt = $db->query($classSql);
    foreach ($stmt->fetchAll() as $c) {
        $key = $c['grade_id'] . '_' . mb_strtolower($c['section']);
        $classes[$key] = $c['id'];
        $key_name = $c['grade_id'] . '_' . mb_strtolower($c['name']);
        $classes[$key_name] = $c['id'];
    }
    
    // Default class_id from POST
    $defaultClassId = !empty($_POST['default_class_id']) ? (int)$_POST['default_class_id'] : null;
    
    // Valid blood types
    $validBloodTypes = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
    
    // Process data rows
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors_list = [];
    $lineNum = 1; // Header is line 1
    
    $stmtCheck = $db->prepare("SELECT id, password FROM students WHERE student_number = ?" . ($sid ? " AND school_id = $sid" : ""));
    $sid = schoolId(); // ensure school_id for inserts
    
    // --- بداية التعديل: حلقة القراءة الفعالة ---
    while (($cols = fgetcsv($handle)) !== FALSE) {
        $lineNum++;
        if (empty($cols) || count($cols) < 2) continue;
        
        // Extract fields
        $name = isset($fieldIndex['name']) && isset($cols[$fieldIndex['name']]) 
            ? trim($cols[$fieldIndex['name']]) : '';
        $studentNumber = isset($fieldIndex['student_number']) && isset($cols[$fieldIndex['student_number']]) 
            ? trim($cols[$fieldIndex['student_number']]) : '';
        
        // Validate required
        if (empty($name)) {
            $errors_list[] = "سطر $lineNum: اسم الطالب فارغ";
            $skipped++;
            continue;
        }
        if (empty($studentNumber)) {
            $errors_list[] = "سطر $lineNum: رقم الطالب فارغ ($name)";
            $skipped++;
            continue;
        }
        
        // Determine class_id
        $classId = $defaultClassId;
        
        if (isset($fieldIndex['grade_code']) && isset($fieldIndex['section'])) {
            $gradeCode = isset($cols[$fieldIndex['grade_code']]) ? trim($cols[$fieldIndex['grade_code']]) : '';
            $section = isset($cols[$fieldIndex['section']]) ? trim($cols[$fieldIndex['section']]) : '';
            
            if (!empty($gradeCode) && !empty($section)) {
                $gradeId = $grades[mb_strtolower($gradeCode)] ?? null;
                if ($gradeId) {
                    $key = $gradeId . '_' . mb_strtolower($section);
                    $classId = $classes[$key] ?? $classId;
                }
            }
        }
        
        if (!$classId) {
            $errors_list[] = "سطر $lineNum: لم يتم تحديد الفصل ($name)";
            $skipped++;
            continue;
        }
        
        // Optional fields (no change needed here)
        $dob = null;
        if (isset($fieldIndex['date_of_birth']) && isset($cols[$fieldIndex['date_of_birth']])) {
            $dobRaw = trim($cols[$fieldIndex['date_of_birth']]);
            if (!empty($dobRaw)) {
                $parsed = false;
                foreach (['Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm/d/Y'] as $fmt) {
                    $dt = DateTime::createFromFormat($fmt, $dobRaw);
                    if ($dt) {
                        $dob = $dt->format('Y-m-d');
                        $parsed = true;
                        break;
                    }
                }
                if (!$parsed && is_numeric($dobRaw)) {
                    $unix = ($dobRaw - 25569) * 86400;
                    if ($unix > 0) $dob = date('Y-m-d', $unix);
                }
            }
        }
        
        $bloodType = null;
        if (isset($fieldIndex['blood_type']) && isset($cols[$fieldIndex['blood_type']])) {
            $bt = trim($cols[$fieldIndex['blood_type']]);
            if (in_array($bt, $validBloodTypes)) $bloodType = $bt;
        }
        
        $guardianPhone = null;
        if (isset($fieldIndex['guardian_phone']) && isset($cols[$fieldIndex['guardian_phone']])) {
            $guardianPhone = trim($cols[$fieldIndex['guardian_phone']]) ?: null;
        }
        
        $medicalNotes = null;
        if (isset($fieldIndex['medical_notes']) && isset($cols[$fieldIndex['medical_notes']])) {
            $medicalNotes = trim($cols[$fieldIndex['medical_notes']]) ?: null;
        }

        $password = null;
        if (isset($fieldIndex['password']) && isset($cols[$fieldIndex['password']])) {
            $passRaw = trim($cols[$fieldIndex['password']]);
            if (!empty($passRaw)) $password = password_hash($passRaw, PASSWORD_DEFAULT);
        }
        
        // Check if student exists
        $stmtCheck->execute([$studentNumber]);
        $existing = $stmtCheck->fetch();
        
        try {
            if ($existing) {
                $sql = "UPDATE students SET name=?, class_id=?, date_of_birth=?, blood_type=?, guardian_phone=?, medical_notes=?, active=1";
                $params = [sanitize($name), $classId, $dob, $bloodType, $guardianPhone, $medicalNotes ? sanitize($medicalNotes) : null];
                
                // Set password if provided, OR if existing student has no password
                if ($password) {
                    $sql .= ", password=?";
                    $params[] = $password;
                } elseif (empty($existing['password'])) {
                    $sql .= ", password=?";
                    $params[] = password_hash($studentNumber, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id=?";
                $params[] = $existing['id'];
                $db->prepare($sql)->execute($params);
                $updated++;
            } else {
                // For new students, if no password provided, use student_number as default
                if (!$password) {
                    $password = password_hash($studentNumber, PASSWORD_DEFAULT);
                }
                $db->prepare("INSERT INTO students (school_id, name, student_number, class_id, date_of_birth, blood_type, guardian_phone, medical_notes, password) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$sid, sanitize($name), sanitize($studentNumber), $classId, $dob, $bloodType, $guardianPhone, $medicalNotes ? sanitize($medicalNotes) : null, $password]);
                $imported++;
            }
        } catch (PDOException $e) {
            $errors_list[] = "سطر $lineNum: خطأ في الحفظ ($name) - " . $e->getMessage();
            $skipped++;
        }
    }
    fclose($handle); // إغلاق الملف
    // --- نهاية التعديل ---
    
    logActivity('import_students', 'students', null, "Imported: $imported, Updated: $updated, Skipped: $skipped");
    
    $message = "تم الاستيراد: $imported طالب جديد";
    if ($updated > 0) $message .= " + تحديث $updated طالب";
    if ($skipped > 0) $message .= " | تخطي $skipped";
    
    jsonSuccess([
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => array_slice($errors_list, 0, 20) // أول 20 خطأ فقط
    ], $message);
}

/**
 * Export Students as CSV Template
 */
function exportStudentsTemplate() {
    requireLogin();
    
    try {
        $db = getDB();
        $classId = getParam('class_id');
        $withData = getParam('with_data', '0');
        
        // BOM for Excel UTF-8 support
        $bom = "\xEF\xBB\xBF";
        
        // Headers
        $headers = ['اسم الطالب', 'رقم الطالب', 'رمز الصف', 'رقم الفصل', 'تاريخ الميلاد', 'فصيلة الدم', 'رقم ولي الأمر', 'ملاحظات طبية', 'كلمة المرور'];
        
        $rows = [];
        
        if ($withData === '1') {
            // Export existing students - try with sort_order first, fallback without
            $queries = [
                "SELECT s.name, s.student_number, g.code as grade_code, c.section,
                        s.date_of_birth, s.blood_type, s.guardian_phone, s.medical_notes
                 FROM students s
                 JOIN classes c ON s.class_id = c.id
                 JOIN grades g ON c.grade_id = g.id
                 WHERE s.active = 1" . ($classId ? " AND s.class_id = ?" : "") . "
                 ORDER BY g.sort_order, c.section, s.name",
                 
                "SELECT s.name, s.student_number, g.code as grade_code, c.section,
                        s.date_of_birth, s.blood_type, s.guardian_phone, s.medical_notes
                 FROM students s
                 JOIN classes c ON s.class_id = c.id
                 JOIN grades g ON c.grade_id = g.id
                 WHERE s.active = 1" . ($classId ? " AND s.class_id = ?" : "") . "
                 ORDER BY g.id, c.section, s.name",
                 
                "SELECT s.name, s.student_number, '' as grade_code, '' as section,
                        NULL as date_of_birth, NULL as blood_type, NULL as guardian_phone, NULL as medical_notes
                 FROM students s
                 WHERE s.active = 1" . ($classId ? " AND s.class_id = ?" : "") . "
                 ORDER BY s.name"
            ];
            
            $params = $classId ? [$classId] : [];
            $students = [];
            
            foreach ($queries as $sql) {
                try {
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $students = $stmt->fetchAll();
                    break;
                } catch (PDOException $e) {
                    continue;
                }
            }
            
            foreach ($students as $s) {
                $rows[] = [
                    $s['name'] ?? '',
                    $s['student_number'] ?? '',
                    $s['grade_code'] ?? '',
                    $s['section'] ?? '',
                    $s['date_of_birth'] ?? '',
                    $s['blood_type'] ?? '',
                    $s['guardian_phone'] ?? '',
                    $s['medical_notes'] ?? '',
                    '' // Password column starts empty for security
                ];
            }
        } else {
            // Add sample rows
            $rows[] = ['محمد أحمد العتيبي', '1001', '1', '1', '2008-05-15', 'A+', '0512345678', ''];
            $rows[] = ['خالد سعد الشمري', '1002', '1', '2', '2007-11-20', 'B+', '0587654321', 'حساسية صدرية'];
            $rows[] = ['عبدالله فهد القحطاني', '1003', '2', '1', '2008-03-10', 'O+', '0556789012', ''];
        }
        
        // Build CSV content
        $csv = $bom;
        $csv .= implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function($val) {
                $val = (string)$val;
                if (strpos($val, ',') !== false || strpos($val, '"') !== false || strpos($val, "\n") !== false) {
                    return '"' . str_replace('"', '""', $val) . '"';
                }
                return $val;
            }, $row)) . "\n";
        }
        
        // Clear any previous output
        if (ob_get_level()) ob_end_clean();
        
        // Send as downloadable file
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_template.csv"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csv;
        exit;
        
    } catch (Exception $e) {
        // If CSV fails, return JSON error
        header('Content-Type: application/json; charset=utf-8');
        jsonError('خطأ في تصدير البيانات: ' . $e->getMessage());
    }
}
