<?php
/**
 * تعريف كل الصلاحيات المتاحة في النظام
 * تُستخدم لبناء واجهة إدارة الصلاحيات في صفحة المشرفين
 */

function getAllPermissions(): array
{
    return [
        'manage_albums' => [
            'label' => 'إدارة الألبومات',
            'description' => 'إنشاء، تعديل، حذف الألبومات ورفع الصور',
        ],
        'delete_albums' => [
            'label' => 'حذف الألبومات',
            'description' => 'صلاحية حذف الألبومات نهائياً',
        ],
        'manage_categories' => [
            'label' => 'إدارة التصنيفات',
            'description' => 'إضافة وتعديل وحذف تصنيفات الألبومات',
        ],
        'manage_admins' => [
            'label' => 'إدارة المشرفين',
            'description' => 'إضافة مشرفين جدد وتعديل صلاحياتهم',
        ],
        'manage_settings' => [
            'label' => 'إعدادات الموقع',
            'description' => 'تعديل الإعدادات العامة ومظهر الموقع',
        ],
        'view_activity_log' => [
            'label' => 'سجل النشاطات',
            'description' => 'عرض سجل كل العمليات التي تمت في لوحة التحكم',
        ],
        'manage_comments' => [
            'label' => 'إدارة التعليقات',
            'description' => 'الموافقة على التعليقات أو حذفها',
        ],
    ];
}

/**
 * تسمية عربية لدور المستخدم
 */
function roleLabel(string $role): string
{
    return match ($role) {
        'super_admin' => 'مدير عام',
        'admin' => 'مشرف',
        'editor' => 'محرر',
        default => $role,
    };
}

/**
 * تحديث صلاحيات مشرف معين (يستبدل القائمة الحالية بالكامل)
 */
function updateAdminPermissions(int $adminId, array $permissionKeys): void
{
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare('DELETE FROM admin_permissions WHERE admin_id = ?');
        $stmt->execute([$adminId]);

        $validKeys = array_keys(getAllPermissions());
        $insertStmt = $db->prepare('INSERT INTO admin_permissions (admin_id, permission_key) VALUES (?, ?)');

        foreach ($permissionKeys as $key) {
            if (in_array($key, $validKeys, true)) {
                $insertStmt->execute([$adminId, $key]);
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * الحصول على قائمة صلاحيات مشرف معين
 */
function getAdminPermissions(int $adminId): array
{
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = ?');
    $stmt->execute([$adminId]);
    return array_column($stmt->fetchAll(), 'permission_key');
}
