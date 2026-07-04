<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/Album.php';
require_once __DIR__ . '/includes/Photo.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: albums.php');
    exit;
}

$albumModel = new Album();
$photoModel = new Photo();

$album = $albumModel->getBySlug($slug);

if (!$album || (!$album['is_published'] && !isAdminLoggedIn())) {
    http_response_code(404);
    $pageTitle = 'الألبوم غير موجود';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><h3>عذراً، هذا الألبوم غير موجود</h3><p><a href="albums.php" class="btn btn-primary" style="margin-top:20px;">العودة للألبومات</a></p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$errorMessage = '';
$isUnlocked = !$album['is_protected'] || isAlbumUnlocked((int) $album['id']) || isAdminLoggedIn();

// معالجة إرسال نموذج كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['album_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'حدث خطأ في التحقق من الطلب، الرجاء المحاولة مرة أخرى';
    } elseif (!checkAlbumRateLimit((int) $album['id'], $ip)) {
        $errorMessage = 'تم تجاوز عدد المحاولات المسموحة، الرجاء المحاولة لاحقاً';
    } else {
        $isValid = $albumModel->verifyPassword((int) $album['id'], $_POST['album_password']);
        logAlbumAttempt((int) $album['id'], $ip, $isValid);

        if ($isValid) {
            unlockAlbum((int) $album['id']);
            $isUnlocked = true;
        } else {
            $errorMessage = 'كلمة المرور غير صحيحة، الرجاء المحاولة مرة أخرى';
        }
    }
}

// معالجة إرسال تعليق جديد على الألبوم
$commentSuccess = false;
$commentError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    if (!$isUnlocked) {
        $commentError = 'هذا الألبوم محمي، الرجاء إدخال كلمة المرور أولاً';
    } elseif (getSetting('allow_comments') !== '1') {
        $commentError = 'التعليقات غير مفعّلة على هذا الموقع حالياً';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $commentError = 'حدث خطأ في التحقق من الطلب، الرجاء المحاولة مرة أخرى';
    } else {
        $commentName = trim($_POST['comment_name'] ?? '');
        $commentEmail = trim($_POST['comment_email'] ?? '');
        $commentText = trim($_POST['comment_text'] ?? '');

        if (empty($commentName) || empty($commentText)) {
            $commentError = 'الرجاء إدخال الاسم ونص التعليق';
        } else {
            $stmt = Database::getInstance()->prepare(
                'INSERT INTO comments (album_id, name, email, comment, ip_address) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([(int) $album['id'], $commentName, $commentEmail ?: null, $commentText, $ip]);
            $commentSuccess = true;
        }
    }
}

$pageTitle = $album['title'];
require_once __DIR__ . '/includes/header.php';

if (!$isUnlocked):
?>
    <div class="password-gate">
        <div class="password-gate-icon"><i class="fa-solid fa-lock"></i></div>
        <h2><?= e($album['title']) ?></h2>
        <p>هذا الألبوم محمي بكلمة مرور، الرجاء إدخالها للمتابعة</p>

        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <div class="password-input-wrapper">
                    <input type="password" name="album_password" class="form-control" placeholder="أدخل كلمة المرور" required autofocus>
                    <button type="button" class="password-toggle"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="width:100%;">
                <i class="fa-solid fa-unlock"></i> دخول الألبوم
            </button>
            <?php if ($errorMessage): ?>
                <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= e($errorMessage) ?></div>
            <?php endif; ?>
        </form>
    </div>
<?php
else:
    // زيادة عداد المشاهدات مرة واحدة فقط لكل جلسة
    $viewedKey = 'viewed_album_' . $album['id'];
    if (empty($_SESSION[$viewedKey])) {
        $albumModel->incrementViews((int) $album['id']);
        $_SESSION[$viewedKey] = true;
    }

    $photos = $photoModel->getByAlbum((int) $album['id']);
    $allowDownloads = getSetting('allow_downloads', '1') === '1';
?>
    <div style="margin-bottom: 30px;">
        <a href="albums.php" style="color:var(--color-silver-dim);font-size:0.9rem;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-arrow-right"></i> العودة للألبومات
        </a>
    </div>

    <div style="text-align:center;margin-bottom:40px;">
        <?php if ($album['is_protected']): ?>
            <span class="hero-badge" style="margin-bottom:16px;"><i class="fa-solid fa-lock"></i> ألبوم محمي</span><br>
        <?php endif; ?>
        <h1 class="gold-text" style="font-family:var(--font-display);font-size:2.2rem;margin-bottom:12px;"><?= e($album['title']) ?></h1>
        <?php if (!empty($album['description'])): ?>
            <p style="color:var(--color-silver-dim);max-width:650px;margin:0 auto;"><?= nl2br(e($album['description'])) ?></p>
        <?php endif; ?>
        <div style="display:flex;gap:20px;justify-content:center;margin-top:20px;color:var(--color-silver-muted);font-size:0.9rem;flex-wrap:wrap;">
            <span><i class="fa-solid fa-image"></i> <?= count($photos) ?> صورة</span>
            <span><i class="fa-solid fa-eye"></i> <?= (int) $album['views_count'] ?> مشاهدة</span>
            <span><i class="fa-regular fa-calendar"></i> <?= formatArabicDate($album['created_at']) ?></span>
        </div>
    </div>

    <?php if (empty($photos)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-image"></i>
            <h3>لا توجد صور في هذا الألبوم بعد</h3>
        </div>
    <?php else: ?>
        <div class="photos-grid">
            <?php foreach ($photos as $photo): ?>
                <div class="photo-item"
                     data-full="<?= UPLOAD_URL . e($photo['filename']) ?>"
                     data-title="<?= e($photo['title'] ?: $album['title']) ?>"
                     data-photo-id="<?= (int) $photo['id'] ?>">
                    <img src="<?= UPLOAD_URL . e($photo['thumbnail'] ?: $photo['filename']) ?>" alt="<?= e($photo['title'] ?: $album['title']) ?>" loading="lazy">
                    <div class="photo-item-overlay">
                        <span class="photo-zoom-icon"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (getSetting('allow_comments') === '1'): ?>
    <div style="max-width:750px;margin:50px auto 0;background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(var(--gold-rgb),0.1);border-radius:22px;padding:36px;">
        <h3 style="margin-bottom:24px;"><i class="fa-solid fa-comments" style="color:var(--color-gold-light);margin-left:8px;"></i>التعليقات</h3>

        <?php
        $approvedComments = Database::getInstance()->prepare('SELECT * FROM comments WHERE album_id = ? AND is_approved = 1 ORDER BY created_at DESC');
        $approvedComments->execute([(int) $album['id']]);
        $approvedComments = $approvedComments->fetchAll();
        ?>

        <?php if (empty($approvedComments)): ?>
            <p style="color:var(--color-silver-muted);margin-bottom:28px;">لا توجد تعليقات بعد، كن أول من يعلّق على هذا الألبوم</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:30px;">
            <?php foreach ($approvedComments as $c): ?>
                <div style="padding:16px 18px;background:var(--color-black-soft);border-radius:14px;">
                    <strong style="color:var(--color-silver-light);"><?= e($c['name']) ?></strong>
                    <span style="color:var(--color-silver-muted);font-size:0.8rem;margin-right:10px;"><?= formatArabicDate($c['created_at']) ?></span>
                    <p style="margin-top:8px;color:var(--color-silver-dim);"><?= nl2br(e($c['comment'])) ?></p>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($commentSuccess): ?>
            <div class="alert alert-success" data-autoclose><i class="fa-solid fa-circle-check"></i> تم إرسال تعليقك بنجاح، سيظهر للعامة بعد مراجعة المشرف</div>
        <?php else: ?>
            <?php if ($commentError): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($commentError) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="comment_submit" value="1">
                <div class="form-group">
                    <label class="form-label">الاسم <span style="color:#e0525f;">*</span></label>
                    <input type="text" name="comment_name" class="form-control" required value="<?= e($_POST['comment_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني (اختياري، لن يُنشر)</label>
                    <input type="email" name="comment_email" class="form-control" value="<?= e($_POST['comment_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">تعليقك <span style="color:#e0525f;">*</span></label>
                    <textarea name="comment_text" class="form-control" required><?= e($_POST['comment_text'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> إرسال التعليق</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
