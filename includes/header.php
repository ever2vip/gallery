<?php
/**
 * هيدر الموقع العام المطور - يدعم تعدد اللغات والخطوط المخصصة
 */
$siteTitle = getSetting('site_title', SITE_NAME);
$siteLogo = getSetting('site_logo', '');
$currentPage = basename($_SERVER['PHP_SELF']);

// جلب الخط المخصص من الإعدادات (إذا كان موجوداً، وإلا نستخدم الخط الافتراضي)
$customFont = getSetting('custom_font_family', 'Default');
?>
<!DOCTYPE html>
<html lang="<?= SITE_LANG ?>" dir="<?= SITE_DIR ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e($siteTitle) ?></title>
    <meta name="description" content="<?= e(getSetting('site_description', '')) ?>">
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    
    <?php if (SITE_DIR === 'ltr'): ?>
        <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        <?= getAccentThemeCSS() ?>
        
        /* تطبيق الخط المخصص ديناميكياً عند رفعه من لوحة التحكم */
        <?php if ($customFont !== 'Default' && !empty($customFont)): ?>
        @font-face {
            font-family: 'CustomSiteFont';
            src: url('<?= SITE_URL ?>/assets/fonts/<?= e($customFont) ?>');
        }
        .logo span {
            font-family: 'CustomSiteFont', sans-serif !important;
        }
        <?php endif; ?>

        /* تنسيق سريع لزر تغيير اللغة ليناسب هيدر السكربت الحالي */
        .lang-switcher {
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            color: var(--text-color, #333);
            font-size: 0.9rem;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .lang-switcher:hover {
            background-color: #f5f5f5;
        }
        /* لتعديل المحاذاة في الـ LTR */
        html[dir="ltr"] .header-inner {
            flex-direction: row-reverse;
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        
        <div class="language-zone">
            <a href="?lang=<?= SITE_LANG === 'ar' ? 'en' : 'ar' ?>" class="lang-switcher">
                <i class="fa-solid fa-language"></i>
                <span><?= SITE_LANG === 'ar' ? 'English' : 'العربية' ?></span>
            </a>
        </div>

        <nav class="main-nav">
            <a href="<?= SITE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"><?= __('dashboard') ?></a>
            <a href="<?= SITE_URL ?>/albums.php" class="<?= $currentPage === 'albums.php' ? 'active' : '' ?>"><?= __('albums') ?></a>
            <a href="<?= SITE_URL ?>/about.php" class="<?= $currentPage === 'about.php' ? 'active' : '' ?>"><?= __('about_us') ?></a>
            <a href="<?= SITE_URL ?>/contact.php" class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>"><?= __('contact_us') ?></a>
        </nav>

        <a href="<?= SITE_URL ?>/index.php" class="logo">
            <?php if ($siteLogo): ?>
                <img src="<?= UPLOAD_URL . e($siteLogo) ?>" alt="<?= e($siteTitle) ?>" style="width:42px;height:42px;border-radius:8px;object-fit:cover;">
            <?php else: ?>
                <span class="logo-icon"><i class="fa-solid fa-camera-retro"></i></span>
            <?php endif; ?>
            <span><?= e($siteTitle) ?></span>
        </a>

        <button class="mobile-menu-btn"><i class="fa-solid fa-bars"></i></button>
    </div>
</header>

<main>
<div class="container" style="padding-top: 30px;">
<?php
$flash = getFlashMessage();
if ($flash):
    $icon = match ($flash['type']) {
        'success' => 'fa-circle-check',
        'danger' => 'fa-circle-exclamation',
        'warning' => 'fa-triangle-exclamation',
        default => 'fa-circle-info',
    };
?>
    <div class="alert alert-<?= e($flash['type']) ?>" data-autoclose>
        <i class="fa-solid <?= $icon ?>"></i>
        <span><?= e($flash['message']) ?></span>
    </div>
<?php endif; ?>
</div>