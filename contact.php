<?php
require_once __DIR__ . '/includes/config.php'; // أو ملف التعريف الأساسي لديك
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_helper.php';

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $errors[] = 'جميع الحقول مطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني المدخل غير صحيح.';
    }

    if (empty($errors)) {
        // البريد الإلكتروني للمدير المستهدف (مجلوب من الإعدادات)
        $adminEmail = getSetting('contact_email', 'admin@gallery.com');
        
        $subject = "رسالة اتصال جديدة من: " . e($name);
        $body = "<h3>تفاصيل الرسالة:</h3>
                 <p><b>الاسم:</b> " . e($name) . "</p>
                 <p><b>البريد الإلكتروني:</b> " . e($email) . "</p>
                 <p><b>الرسالة:</b><br>" . nl2br(e($message)) . "</p>";

        if (sendSMTPMail($adminEmail, $subject, $body)) {
            $successMessage = 'تم إرسال رسالتك بنجاح! سنتواصل معك في أقرب وقت.';
        } else {
            $errors[] = 'عذراً، حدث خطأ أثناء محاولة إرسال الرسالة، يرجى المحاولة لاحقاً.';
        }
    }
}

$pageTitle = __('contact_us');
include __DIR__ . '/includes/header.php';
?>

<div class="container my-5" style="max-width: 600px;">
    <div class="card p-4 shadow-sm border-0">
        <h2 class="mb-4 text-center"><i class="fa-solid fa-envelope text-primary"></i> <?= __('contact_us') ?></h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?php foreach($errors as $err) echo e($err) . '<br>'; ?></div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $successMessage ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><?= __('name') ?>:</label>
                <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= __('email') ?>:</label>
                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= __('message') ?>:</label>
                <textarea name="message" class="form-control" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= __('send_message') ?> <i class="fa-solid fa-paper-plane"></i></button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
