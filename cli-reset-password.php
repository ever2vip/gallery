<?php
/**
 * أداة إعادة تعيين كلمة مرور المدير - لأصحاب الاستضافة التي تدعم SSH / سطر الأوامر
 *
 * طريقة الاستخدام (عبر SSH في مجلد المشروع):
 *   php cli-reset-password.php
 *
 * ملاحظة أمنية: هذا الملف مصمم للعمل فقط عبر سطر الأوامر (CLI) ويرفض
 * العمل تماماً إذا تم فتحه عبر المتصفح، لذا لا خطورة من تركه على السيرفر.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("ممنوع الوصول: هذه الأداة تعمل فقط عبر سطر الأوامر (SSH) لأسباب أمنية.\n");
}

require __DIR__ . '/config/database.php';

echo "=========================================\n";
echo "  أداة إعادة تعيين كلمة مرور المشرف\n";
echo "=========================================\n\n";

echo "اسم المستخدم (افتراضي: admin): ";
$username = trim(fgets(STDIN));
if ($username === '') {
    $username = 'admin';
}

echo "كلمة المرور الجديدة (8 أحرف على الأقل): ";
$password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    die("\n❌ كلمة المرور يجب أن تكون 8 أحرف على الأقل. تم الإلغاء.\n");
}

try {
    $db = Database::getInstance();

    $checkStmt = $db->prepare('SELECT id FROM admins WHERE username = ?');
    $checkStmt->execute([$username]);

    if (!$checkStmt->fetch()) {
        die("\n❌ لا يوجد مستخدم باسم \"$username\" في قاعدة البيانات.\n");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE admins SET password = ?, is_active = 1 WHERE username = ?');
    $stmt->execute([$hash, $username]);

    echo "\n✅ تم تحديث كلمة المرور بنجاح للمستخدم \"$username\".\n";
    echo "يمكنك الآن تسجيل الدخول من: /admin/login.php\n";
} catch (Exception $e) {
    die("\n❌ حدث خطأ أثناء الاتصال بقاعدة البيانات: " . $e->getMessage() . "\n");
}
