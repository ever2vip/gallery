<?php
/**
 * هيدر ولوحة تنقل لوحة التحكم
 * يتطلب: $pageTitle, $breadcrumb (اختياري)
 */
requireAdminLogin();
$currentAdmin = getCurrentAdmin();
$currentFile = basename($_SERVER['PHP_SELF']);

// عدد التعليقات بانتظار المراجعة (لعرضه كشارة في القائمة الجانبية)
$pendingCommentsCount = 0;
if (hasPermission('manage_comments')) {
    $pendingCommentsCount = (int) Database::getInstance()
        ->query('SELECT COUNT(*) as cnt FROM comments WHERE is_approved = 0')
        ->fetch()['cnt'];
}

$navItems = [
    'general' => [
        'label' => 'عام',
        'items' => [
            ['file' => 'index.php', 'icon' => 'fa-gauge-high', 'label' => 'لوحة القيادة', 'perm' => null],
            ['file' => 'albums.php', 'icon' => 'fa-images', 'label' => 'الألبومات', 'perm' => 'manage_albums', 'match' => ['albums.php', 'album-edit.php', 'album-photos.php']],
            ['file' => 'photos.php', 'icon' => 'fa-image', 'label' => 'كل الصور', 'perm' => 'manage_albums'],
            ['file' => 'categories.php', 'icon' => 'fa-tags', 'label' => 'التصنيفات', 'perm' => 'manage_categories'],
        ]
    ],
    'management' => [
        'label' => 'الإدارة',
        'items' => [
            ['file' => 'comments.php', 'icon' => 'fa-comments', 'label' => 'التعليقات', 'perm' => 'manage_comments', 'badge' => $pendingCommentsCount],
            ['file' => 'admins.php', 'icon' => 'fa-user-shield', 'label' => 'المشرفون', 'perm' => 'manage_admins', 'match' => ['admins.php', 'admin-edit.php']],
            ['file' => 'activity-log.php', 'icon' => 'fa-clock-rotate-left', 'label' => 'سجل النشاطات', 'perm' => 'view_activity_log'],
        ]
    ],
    'settings' => [
        'label' => 'الإعدادات',
        'items' => [
            ['file' => 'settings.php', 'icon' => 'fa-gear', 'label' => 'إعدادات الموقع', 'perm' => 'manage_settings'],
            ['file' => 'appearance.php', 'icon' => 'fa-palette', 'label' => 'المظهر والتصميم', 'perm' => 'manage_settings'],
            ['file' => 'profile.php', 'icon' => 'fa-id-card', 'label' => 'الملف الشخصي', 'perm' => null],
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>لوحة التحكم</title>
    <meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style><?= getAccentThemeCSS() ?></style>
</head>
<body>

<div class="admin-layout">
    <div class="sidebar-overlay"></div>

    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <span class="sidebar-brand-icon"><i class="fa-solid fa-camera-retro"></i></span>
            <span class="sidebar-brand-text">
                لوحة التحكم
                <small>معرض الصور</small>
            </span>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($navItems as $group): ?>
                <div class="sidebar-nav-group">
                    <div class="sidebar-nav-label"><?= e($group['label']) ?></div>
                    <?php foreach ($group['items'] as $item): ?>
                        <?php if ($item['perm'] === null || hasPermission($item['perm'])): ?>
                            <?php $matchFiles = $item['match'] ?? [$item['file']]; ?>
                            <a href="<?= e($item['file']) ?>" class="sidebar-nav-item <?= in_array($currentFile, $matchFiles, true) ? 'active' : '' ?>">
                                <i class="fa-solid <?= e($item['icon']) ?>"></i>
                                <span><?= e($item['label']) ?></span>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="badge-count"><?= (int) $item['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <span class="sidebar-user-avatar">
                    <?php if (!empty($currentAdmin['avatar'])): ?>
                        <img src="<?= UPLOAD_URL . e($currentAdmin['avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <?= e(mb_substr($currentAdmin['full_name'] ?? $currentAdmin['username'], 0, 1)) ?>
                    <?php endif; ?>
                </span>
                <span class="sidebar-user-info">
                    <span class="sidebar-user-name"><?= e($currentAdmin['full_name'] ?: $currentAdmin['username']) ?></span>
                    <span class="sidebar-user-role"><?= e(roleLabel($currentAdmin['role'])) ?></span>
                </span>
                <a href="logout.php" class="sidebar-logout" title="تسجيل الخروج" data-confirm-delete="هل تريد تسجيل الخروج من لوحة التحكم؟">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:14px;">
                <button class="mobile-sidebar-toggle"><i class="fa-solid fa-bars"></i></button>
                <div class="admin-page-title">
                    <?= e($pageTitle ?? 'لوحة التحكم') ?>
                    <?php if (!empty($breadcrumb)): ?>
                        <span class="breadcrumb"><?= $breadcrumb ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/index.php" target="_blank" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-up-left-from-square"></i> عرض الموقع
            </a>
        </div>

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
