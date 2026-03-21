<?php
/**
 * PE Smart School System — Badge Service
 * ========================================
 * طبقة الخدمات: يمركز منطق منح الأوسمة بدلاً من تشتته عبر api/badges.php.
 *
 * الاستخدام:
 *   BadgeService::award($studentId, $badgeId, $awardedByUserId);
 *   BadgeService::revoke($studentId, $badgeId);
 *   $results = BadgeService::runAutoCheck($schoolId);
 */
class BadgeService
{
    /**
     * Award a badge to a student.
     * Sends a notification to the student's parents upon success.
     *
     * @param int $studentId  ID from `students` table
     * @param int $badgeId    ID from `badges` table
     * @param int $awardedBy  User ID of the awarding teacher/admin
     * @return bool           true on success, false if already awarded
     */
    public static function award(int $studentId, int $badgeId, int $awardedBy): bool
    {
        try {
            $db  = getDB();
            $sid = schoolId();

            // Check if already awarded
            $check = $db->prepare(
                "SELECT id FROM student_badges WHERE student_id = ? AND badge_id = ? LIMIT 1"
            );
            $check->execute([$studentId, $badgeId]);
            if ($check->fetch()) {
                return false; // Already has this badge
            }

            $db->prepare(
                "INSERT INTO student_badges (school_id, student_id, badge_id, awarded_by, awarded_at)
                 VALUES (?, ?, ?, ?, NOW())"
            )->execute([$sid, $studentId, $badgeId, $awardedBy]);

            // Fetch badge name for notification
            $badgeRow = $db->prepare("SELECT name FROM badges WHERE id = ? LIMIT 1");
            $badgeRow->execute([$badgeId]);
            $badgeName = $badgeRow->fetchColumn() ?: 'وسام جديد';

            // Notify the student's parents
            NotificationService::sendToParent(
                $studentId,
                'badge',
                "🏅 وسام جديد: $badgeName",
                'حصل طالبك على وسام جديد من معلمه. تهانينا!',
                ['badge_id' => $badgeId]
            );

            logActivity('award_badge', 'student_badges', $studentId, "وسام: $badgeName → طالب #$studentId");

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Revoke a badge from a student.
     *
     * @param int $studentId
     * @param int $badgeId
     * @return bool true if a row was deleted
     */
    public static function revoke(int $studentId, int $badgeId): bool
    {
        try {
            $stmt = getDB()->prepare(
                "DELETE FROM student_badges WHERE student_id = ? AND badge_id = ?"
            );
            $stmt->execute([$studentId, $badgeId]);

            logActivity('revoke_badge', 'student_badges', $studentId, "سحب وسام #$badgeId من طالب #$studentId");

            return $stmt->rowCount() > 0;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Run automatic badge eligibility checks for all students in a school.
     * Extension point: add new auto-badge rules here.
     *
     * @param int $schoolId
     * @return array List of ['student_id', 'badge_id', 'reason'] for awarded badges
     */
    public static function runAutoCheck(int $schoolId): array
    {
        $awarded = [];

        try {
            $db = getDB();

            // Fetch all auto-eligible badges for this school
            $badges = $db->prepare(
                "SELECT id, name, auto_criteria FROM badges
                 WHERE school_id = ? AND auto_award = 1 AND active = 1"
            );
            $badges->execute([$schoolId]);

            foreach ($badges->fetchAll() as $badge) {
                $criteria = json_decode($badge['auto_criteria'] ?? '{}', true);
                if (empty($criteria)) continue;

                // Extension point: dispatch based on criteria type
                // e.g. ['type' => 'attendance', 'threshold' => 90]
                // Add new auto-award rules here without touching api/badges.php
            }
        } catch (Exception) {
            // Silent fail
        }

        return $awarded;
    }
}
