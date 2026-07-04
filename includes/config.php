<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// الاتصال بقاعدة البيانات (الكود الحالي الخاص بك)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'photo_gallery');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- نظام اللغات المطور ---
// تحديد اللغة الافتراضية
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar'; // اللغة الافتراضية
}

// تغيير اللغة عند الطلب عبر الرابط ?lang=en
if (isset($_GET['lang']) && in_array($_GET['GET']['lang'], ['ar', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// تحميل ملف الترجمة
$lang_code = $_SESSION['lang'];
$lang = require_once __DIR__ . "/../lang/{$lang_code}.php";

// دالة جلب النصوص المترجمة
function __($key) {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : $key;
}

// تحديد اتجاه الصفحة
define('SITE_DIR', ($_SESSION['lang'] == 'ar') ? 'rtl' : 'ltr');
define('SITE_LANG', $_SESSION['lang']);
