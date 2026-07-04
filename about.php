<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'من نحن';
require_once __DIR__ . '/includes/header.php';
?>

<section style="padding: 30px 0 60px;max-width:800px;margin:0 auto;">
    <h1 class="section-title">من <span class="gold-text">نحن</span></h1>

    <div style="background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(212,175,55,0.1);border-radius:22px;padding:40px;margin-top:30px;line-height:2;color:var(--color-silver-dim);">
        <p style="margin-bottom:20px;"><?= e(getSetting('site_description', 'معرض صور احترافي مخصص لعرض وتنظيم الألبومات بأناقة وسهولة.')) ?></p>
        <p>نوفر لك تجربة عرض بصرية استثنائية مع إمكانية حماية ألبوماتك الخاصة بكلمة مرور، لضمان أن تبقى لحظاتك الثمينة محفوظة وآمنة كما تريد.</p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
