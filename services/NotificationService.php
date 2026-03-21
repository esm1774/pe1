<?php
/**
 * PE Smart School System — Notification Service
 * ===============================================
 * طبقة الخدمات: يمنع تشتت منطق الإشعارات عبر ملفات الـ API.
 *
 * الاستخدام:
 *   NotificationService::send($userId, 'badge', 'حصلت على وسام', 'تهانينا!');
 *   NotificationService::sendToClass($classId, 'attendance', 'تغيب اليوم', '...');
 */
class NotificationService
{
    /**
     * Send a notification to a single user.
     *
     * @param int    $userId  ID of the target user (from `users` table)
     * @param string $type    Notification type: 'badge', 'attendance', 'fitness', 'general'
     * @param string $title   Short title (displayed in notification bell)
     * @param string $body    Full message body
     * @param array  $meta    Optional JSON-serializable metadata (e.g. ['badge_id' => 5])
     */
    public static function send(
        int    $userId,
        string $type,
        string $title,
        string $body,
        array  $meta = []
    ): void {
        try {
            $db   = getDB();
            $sid  = schoolId();
            $stmt = $db->prepare("
                INSERT INTO notifications (school_id, user_id, type, title, message, data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sid,
                $userId,
                $type,
                $title,
                $body,
                !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Exception) {
            // Silent fail — notification failure must never abort a business operation
        }
    }

    /**
     * Send a notification to all teachers/admin of a school.
     * Extension point: can be extended to target parents of a specific class.
     *
     * @param int    $classId Class ID to target (sends to users assigned to this class)
     * @param string $type    Notification type
     * @param string $title   Short title
     * @param string $body    Full message body
     */
    public static function sendToClass(
        int    $classId,
        string $type,
        string $title,
        string $body
    ): void {
        try {
            $db  = getDB();
            $sid = schoolId();

            // Get all teachers assigned to this class
            $stmt = $db->prepare("
                SELECT DISTINCT tc.teacher_id
                FROM   teacher_classes tc
                WHERE  tc.class_id = ?
            ");
            $stmt->execute([$classId]);
            $teacherIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($teacherIds as $teacherId) {
                self::send((int)$teacherId, $type, $title, $body);
            }
        } catch (Exception) {
            // Silent fail
        }
    }

    /**
     * Send a notification to the parent of a specific student.
     *
     * @param int    $studentId Target student ID
     * @param string $type      Notification type
     * @param string $title     Short title
     * @param string $body      Full message body
     * @param array  $meta      Optional metadata
     */
    public static function sendToParent(
        int    $studentId,
        string $type,
        string $title,
        string $body,
        array  $meta = []
    ): void {
        try {
            $db  = getDB();

            // Find all parents linked to this student
            $stmt = $db->prepare("
                SELECT parent_id FROM parent_students WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $parentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($parentIds as $parentId) {
                self::send((int)$parentId, $type, $title, $body, $meta);
            }
        } catch (Exception) {
            // Silent fail
        }
    }
}
