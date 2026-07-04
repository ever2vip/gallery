<?php
require_once __DIR__ . '/includes/functions.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        // في بيئة الإنتاج: إرسال بريد إلكتروني أو حفظ الرسالة في قاعدة البيانات
        $success = true;
    }
}

$pageTitle = 'تواصل معنا';
require_once __DIR__ . '/includes/header.php';
?>

<section style="padding: 30px 0 60px;max-width:600px;margin:0 auto;">
    <h1 class="section-title">تواصل <span class="gold-text">معنا</span></h1>
    <p class="section-subtitle">يسعدنا تواصلك معنا لأي استفسار أو ملاحظة</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span>تم إرسال رسالتك بنجاح، سنتواصل معك قريباً</span>
        </div>
    <?php else: ?>
    <form method="post" style="background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(212,175,55,0.1);border-radius:22px;padding:36px;">
        <?= csrfField() ?>
        <div class="form-group">
            <label class="form-label">الاسم</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label class="form-label">الرسالة</label>
            <textarea name="message" class="form-control" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">
            <i class="fa-solid fa-paper-plane"></i> إرسال الرسالة
        </button>
    </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
