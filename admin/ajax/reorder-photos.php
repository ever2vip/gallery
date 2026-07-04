<?php
/**
 * حفظ ترتيب الصور الجديد بعد السحب والإفلات في لوحة التحكم
 * يُستدعى عبر fetch() من admin.js
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/Photo.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn() || !hasPermission('manage_albums')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بهذا الإجراء']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input) || !verifyCsrfToken($input['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'رمز التحقق غير صالح، الرجاء إعادة تحميل الصفحة']);
    exit;
}

$order = $input['order'] ?? [];

if (!is_array($order) || empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'لا توجد بيانات ترتيب صالحة']);
    exit;
}

// التأكد من أن كل المعرفات أرقام صحيحة فقط قبل التمرير للنموذج
$cleanOrder = array_map('intval', $order);

$photoModel = new Photo();
$result = $photoModel->reorder($cleanOrder);

echo json_encode(['success' => $result]);
