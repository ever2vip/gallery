<?php
/**
 * ملف الاتصال بقاعدة البيانات
 * Database Connection File
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'photo_gallery');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع العامة
define('SITE_NAME', 'معرض الصور');
define('SITE_URL', 'http://localhost/photo-gallery');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 ميجابايت
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('THUMB_WIDTH', 400);
define('THUMB_HEIGHT', 300);

// مفتاح سري للجلسات (غيّره في بيئة الإنتاج)
define('SECRET_KEY', 'change_this_secret_key_in_production_2026');

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                die('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
