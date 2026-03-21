<?php
/**
 * PE Smart School System — Schema Manager
 * ========================================
 * Extension point: يمكن إضافة auto-migration هنا مستقبلاً.
 * حالياً: إدارة المخطط تتم عبر ملفات SQL يدوياً.
 *
 * كيفية التوسع:
 *   1. أضف ملف migration في migrations/ (مثل: 001_add_xyz_column.php)
 *   2. نفذه هنا عبر ensureSchema() بشرط التحقق من الإصدار الحالي
 */
class SchemaManager
{
    /**
     * Called once on every bootstrap via config.php.
     * Extension point — add migration logic here when needed.
     */
    public static function ensureSchema(): void
    {
        // No-op: schema is managed via SQL files in /backups and /modules
        // Future: loop over /migrations/*.php files and apply pending ones
    }
}
