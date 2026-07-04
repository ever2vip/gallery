<?php
require_once __DIR__ . '/../includes/functions.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'حدث خطأ في التحقق من الطلب، الرجاء المحاولة مرة أخرى';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'الرجاء إدخال اسم المستخدم وكلمة المرور';
        } else {
            // حماية من محاولات تخمين كلمة المرور المتكررة
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $attemptsKey = 'login_attempts_' . md5($ip);
            $_SESSION[$attemptsKey] = $_SESSION[$attemptsKey] ?? ['count' => 0, 'time' => time()];

            if ($_SESSION[$attemptsKey]['count'] >= 5 && (time() - $_SESSION[$attemptsKey]['time']) < 900) {
                $error = 'تم تجاوز عدد محاولات الدخول المسموحة، الرجاء المحاولة بعد 15 دقيقة';
            } else {
                $db = Database::getInstance();
                $stmt = $db->prepare('SELECT * FROM admins WHERE username = ? AND is_active = 1');
                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    unset($_SESSION[$attemptsKey]);

                    $updateStmt = $db->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?');
                    $updateStmt->execute([$admin['id']]);

                    logActivity('login', 'تسجيل دخول ناجح');
                    header('Location: index.php');
                    exit;
                } else {
                    $_SESSION[$attemptsKey]['count']++;
                    $_SESSION[$attemptsKey]['time'] = time();
                    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - لوحة التحكم</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style><?= getAccentThemeCSS() ?></style>
</head>
<body>

<div class="login-page">
    <div class="login-box">
        <div class="login-logo"><i class="fa-solid fa-camera-retro"></i></div>
        <h1>لوحة تحكم معرض الصور</h1>
        <p class="subtitle">سجّل دخولك للوصول إلى لوحة التحكم</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">اسم المستخدم</label>
                <input type="text" name="username" class="form-control" placeholder="أدخل اسم المستخدم" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور</label>
                <div class="password-input-wrapper" style="position:relative;">
                    <input type="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required style="padding-left:46px;">
                    <button type="button" class="password-toggle" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--color-silver-muted);cursor:pointer;">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:10px;">
                <i class="fa-solid fa-right-to-bracket"></i> تسجيل الدخول
            </button>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
