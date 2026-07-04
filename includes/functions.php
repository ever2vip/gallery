<?php
/**
 * ملف الدوال المساعدة العامة
 * Core Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * تنظيف المدخلات لمنع XSS
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * إنشاء رابط صديق للمحركات (slug) من نص عربي أو إنجليزي
 */
function createSlug(string $string): string
{
    $string = trim($string);
    // السماح بالحروف العربية والإنجليزية والأرقام والمسافات
    $string = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $string);
    $string = preg_replace('/[\s-]+/u', '-', $string);
    $string = trim($string, '-');
    if (empty($string)) {
        $string = 'album-' . time();
    }
    return $string . '-' . substr(md5(uniqid('', true)), 0, 6);
}

/**
 * التحقق من تسجيل دخول المشرف
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * إعادة توجيه المستخدم إن لم يكن مسجل دخول
 */
function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * الحصول على بيانات المشرف الحالي
 */
function getCurrentAdmin(): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM admins WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

/**
 * التحقق من صلاحية معينة للمشرف الحالي
 */
function hasPermission(string $permissionKey): bool
{
    $admin = getCurrentAdmin();
    if (!$admin) {
        return false;
    }
    // المدير العام لديه كل الصلاحيات دائماً
    if ($admin['role'] === 'super_admin') {
        return true;
    }
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM admin_permissions WHERE admin_id = ? AND permission_key = ?');
    $stmt->execute([$admin['id'], $permissionKey]);
    $result = $stmt->fetch();
    return $result['cnt'] > 0;
}

/**
 * إيقاف التنفيذ إن لم تتوفر الصلاحية المطلوبة
 */
function requirePermission(string $permissionKey): void
{
    if (!hasPermission($permissionKey)) {
        http_response_code(403);
        die('عذراً، ليس لديك صلاحية للقيام بهذا الإجراء.');
    }
}

/**
 * تسجيل نشاط في سجل الأحداث
 */
function logActivity(string $action, string $details = ''): void
{
    $db = Database::getInstance();
    $adminId = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare('INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $details, $ip]);
}

/**
 * الحصول على إعداد من إعدادات الموقع
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    $value = $result ? $result['setting_value'] : $default;
    $cache[$key] = $value;
    return $value;
}

/**
 * تحديث إعداد في إعدادات الموقع
 */
function updateSetting(string $key, string $value): void
{
    $db = Database::getInstance();
    $stmt = $db->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

/**
 * توليد رمز CSRF للحماية من هجمات تزوير الطلبات
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من صحة رمز CSRF
 */
function verifyCsrfToken(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * حقل CSRF جاهز للإدراج في النماذج
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

/**
 * التحقق من كون الألبوم محمي بكلمة مرور وما إذا كان الوصول ممنوحاً في الجلسة
 */
function isAlbumUnlocked(int $albumId): bool
{
    return !empty($_SESSION['unlocked_albums'][$albumId]);
}

/**
 * منح صلاحية الوصول لألبوم محمي في الجلسة الحالية
 */
function unlockAlbum(int $albumId): void
{
    $_SESSION['unlocked_albums'][$albumId] = true;
}

/**
 * التحقق من محاولات الدخول المتكررة على ألبوم معين (حماية brute force)
 */
function checkAlbumRateLimit(int $albumId, string $ip): bool
{
    $db = Database::getInstance();
    $maxAttempts = (int) getSetting('max_login_attempts', '5');
    $lockoutMinutes = (int) getSetting('lockout_minutes', '15');

    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM album_access_logs 
                           WHERE album_id = ? AND ip_address = ? AND success = 0 
                           AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
    $stmt->execute([$albumId, $ip, $lockoutMinutes]);
    $result = $stmt->fetch();

    return $result['cnt'] < $maxAttempts;
}

/**
 * تسجيل محاولة دخول لألبوم محمي
 */
function logAlbumAttempt(int $albumId, string $ip, bool $success): void
{
    $db = Database::getInstance();
    $stmt = $db->prepare('INSERT INTO album_access_logs (album_id, ip_address, success) VALUES (?, ?, ?)');
    $stmt->execute([$albumId, $ip, $success ? 1 : 0]);
}

/**
 * تنسيق حجم الملف بشكل قابل للقراءة
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' ميجابايت';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' كيلوبايت';
    }
    return $bytes . ' بايت';
}

/**
 * تنسيق التاريخ بالعربية
 */
function formatArabicDate(string $date): string
{
    $months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int) date('n', $timestamp)];
    $year = date('Y', $timestamp);
    return "{$day} {$month} {$year}";
}

/**
 * إعادة توجيه مع رسالة (flash message)
 */
function redirectWithMessage(string $url, string $message, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * الحصول على رسالة الفلاش وحذفها
 */
function getFlashMessage(): ?array
{
    if (!empty($_SESSION['flash_message'])) {
        $msg = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'success'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $msg;
    }
    return null;
}

/**
 * التحقق من صحة امتداد الصورة المرفوعة
 */
function isValidImageExtension(string $filename): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS, true);
}

/**
 * توليد اسم ملف فريد وآمن للصورة المرفوعة
 */
function generateUniqueFilename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return uniqid('img_', true) . '_' . time() . '.' . $ext;
}

/**
 * إرجاع كود CSS لتخصيص ألوان النظام (الذهبي/الفضي/البرونزي) حسب اختيار المشرف
 * يُستخدم في هيدر الموقع العام ولوحة التحكم لتوحيد المظهر
 */
function getAccentThemeCSS(): string
{
    $theme = getSetting('accent_theme', 'gold');

    $presets = [
        'gold'   => ['light' => '#f2d879', 'base' => '#d4af37', 'dark' => '#a8842a', 'rgb' => '212, 175, 55'],
        'silver' => ['light' => '#f0f0f4', 'base' => '#c0c0c8', 'dark' => '#8a8a94', 'rgb' => '192, 192, 200'],
        'bronze' => ['light' => '#d9a066', 'base' => '#a86a32', 'dark' => '#6e4620', 'rgb' => '168, 106, 50'],
    ];

    $colors = $presets[$theme] ?? $presets['gold'];

    return sprintf(
        ':root{--color-gold-light:%s;--color-gold:%s;--color-gold-dark:%s;--gold-rgb:%s;}',
        $colors['light'], $colors['base'], $colors['dark'], $colors['rgb']
    );
}

/**
 * قائمة الثيمات اللونية المتاحة للاختيار في لوحة التحكم
 */
function getAvailableAccentThemes(): array
{
    return [
        'gold'   => ['label' => 'ذهبي (افتراضي)', 'swatch' => '#d4af37'],
        'silver' => ['label' => 'فضي', 'swatch' => '#c0c0c8'],
        'bronze' => ['label' => 'برونزي', 'swatch' => '#a86a32'],
    ];
}
