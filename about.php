<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// جلب النص بناءً على لغة السيرفر الحالية المحددة في الجلسة (SITE_LANG)
$aboutContent = (SITE_LANG === 'ar') 
    ? getSetting('about_text_ar', 'معلومات عنا باللغة العربية...') 
    : getSetting('about_text_en', 'About us in English...');

$pageTitle = __('about_us');
include __DIR__ . '/includes/header.php';
?>

<div class="container my-5">
    <div class="card p-5 shadow-sm border-0">
        <h2 class="mb-4 text-center"><i class="fa-solid fa-address-card text-primary"></i> <?= __('about_us') ?></h2>
        <div class="about-text-zone" style="line-height: 1.8; font-size: 1.1rem; color: #444;">
            <?= nl2br(e($aboutContent)) ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
