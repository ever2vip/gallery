<?php
/**
 * تحميل نسخة الصورة الأصلية (يحترم حماية الألبوم وإعداد السماح بالتحميل)
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/Album.php';
require_once __DIR__ . '/includes/Photo.php';

function guessMimeType(string $filePath): string
{
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($filePath);
        if ($mime) {
            return $mime;
        }
    }
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    return $map[$ext] ?? 'application/octet-stream';
}

$photoId = (int) ($_GET['id'] ?? 0);
if (!$photoId) {
    http_response_code(404);
    exit('الصورة غير موجودة');
}

if (getSetting('allow_downloads', '1') !== '1' && !isAdminLoggedIn()) {
    http_response_code(403);
    exit('تحميل الصور غير متاح حالياً على هذا الموقع');
}

$photoModel = new Photo();
$albumModel = new Album();

$photo = $photoModel->getById($photoId);
if (!$photo) {
    http_response_code(404);
    exit('الصورة غير موجودة');
}

$album = $albumModel->getById((int) $photo['album_id']);
if (!$album || (!$album['is_published'] && !isAdminLoggedIn())) {
    http_response_code(404);
    exit('الألبوم غير موجود');
}

$isUnlocked = !$album['is_protected'] || isAlbumUnlocked((int) $album['id']) || isAdminLoggedIn();
if (!$isUnlocked) {
    http_response_code(403);
    exit('هذا الألبوم محمي بكلمة مرور، الرجاء فتحه أولاً');
}

$filePath = UPLOAD_DIR . $photo['filename'];
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('ملف الصورة غير موجود على الخادم');
}

$photoModel->incrementDownloads($photoId);

$downloadName = $photo['original_name'] ?: basename($photo['filename']);
$downloadName = basename($downloadName);
$downloadName = str_replace(['"', "\r", "\n"], '', $downloadName);

header('Content-Type: ' . guessMimeType($filePath));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
