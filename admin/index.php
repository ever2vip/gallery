<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/Album.php';
require_once __DIR__ . '/../includes/Photo.php';

requireAdminLogin();

$albumModel = new Album();
$photoModel = new Photo();
$db = Database::getInstance();

$stats = $albumModel->getStats();
$recentAlbums = $albumModel->getAll(['limit' => 5]);
$recentPhotos = $photoModel->getRecent(6);

$recentActivity = $db->query('
    SELECT al.*, a.username, a.full_name
    FROM activity_logs al
    LEFT JOIN admins a ON al.admin_id = a.id
    ORDER BY al.created_at DESC
    LIMIT 8
')->fetchAll();

$pageTitle = 'لوحة القيادة';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-images"></i></span>
        <div>
            <div class="stat-value"><?= number_format((int) $stats['total_albums']) ?></div>
            <div class="stat-label">إجمالي الألبومات</div>
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon silver"><i class="fa-solid fa-image"></i></span>
        <div>
            <div class="stat-value"><?= number_format((int) $stats['total_photos']) ?></div>
            <div class="stat-label">إجمالي الصور</div>
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon danger"><i class="fa-solid fa-lock"></i></span>
        <div>
            <div class="stat-value"><?= number_format((int) $stats['protected_albums']) ?></div>
            <div class="stat-label">ألبومات محمية</div>
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon success"><i class="fa-solid fa-eye"></i></span>
        <div>
            <div class="stat-value"><?= number_format((int) $stats['total_views']) ?></div>
            <div class="stat-label">إجمالي المشاهدات</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <div>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-images" style="color:var(--color-gold-light);margin-left:8px;"></i>أحدث الألبومات</span>
                <?php if (hasPermission('manage_albums')): ?>
                <a href="album-edit.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> ألبوم جديد</a>
                <?php endif; ?>
            </div>

            <?php if (empty($recentAlbums)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>لا توجد ألبومات بعد</h3>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>الغلاف</th>
                                <th>العنوان</th>
                                <th>الصور</th>
                                <th>الحالة</th>
                                <th>تاريخ الإنشاء</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAlbums as $album): ?>
                            <tr>
                                <td>
                                    <?php if ($album['cover_image']): ?>
                                        <img src="<?= UPLOAD_URL . e($album['cover_image']) ?>" class="table-thumb" alt="">
                                    <?php else: ?>
                                        <div class="table-thumb" style="display:flex;align-items:center;justify-content:center;background:var(--color-black-elevated);color:var(--color-silver-muted);"><i class="fa-solid fa-image"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e($album['title']) ?></strong>
                                    <?php if ($album['is_protected']): ?> <i class="fa-solid fa-lock" style="color:var(--color-gold-light);font-size:0.8rem;"></i><?php endif; ?>
                                </td>
                                <td><?= (int) $album['photos_count'] ?></td>
                                <td>
                                    <?php if ($album['is_published']): ?>
                                        <span class="badge badge-success">منشور</span>
                                    <?php else: ?>
                                        <span class="badge badge-silver">مسودة</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatArabicDate($album['created_at']) ?></td>
                                <td>
                                    <div class="action-icons">
                                        <a href="album-edit.php?id=<?= (int) $album['id'] ?>" class="action-icon-btn edit" title="تعديل"><i class="fa-solid fa-pen"></i></a>
                                        <a href="album-photos.php?id=<?= (int) $album['id'] ?>" class="action-icon-btn view" title="إدارة الصور"><i class="fa-solid fa-images"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align:center;margin-top:16px;">
                    <a href="albums.php" class="btn btn-outline btn-sm">عرض كل الألبومات</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($recentPhotos)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-clock" style="color:var(--color-gold-light);margin-left:8px;"></i>أحدث الصور المضافة</span>
            </div>
            <div class="manage-photos-grid">
                <?php foreach ($recentPhotos as $photo): ?>
                    <div class="manage-photo-item" title="<?= e($photo['album_title']) ?>">
                        <img src="<?= UPLOAD_URL . e($photo['thumbnail'] ?: $photo['filename']) ?>" alt="">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-bolt" style="color:var(--color-gold-light);margin-left:8px;"></i>إجراءات سريعة</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php if (hasPermission('manage_albums')): ?>
                <a href="album-edit.php" class="btn btn-primary btn-block"><i class="fa-solid fa-plus"></i> إنشاء ألبوم جديد</a>
                <?php endif; ?>
                <?php if (hasPermission('manage_categories')): ?>
                <a href="categories.php" class="btn btn-outline btn-block"><i class="fa-solid fa-tags"></i> إدارة التصنيفات</a>
                <?php endif; ?>
                <?php if (hasPermission('manage_admins')): ?>
                <a href="admins.php" class="btn btn-outline btn-block"><i class="fa-solid fa-user-plus"></i> إضافة مشرف</a>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-outline btn-block"><i class="fa-solid fa-key"></i> تغيير كلمة المرور</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-hard-drive" style="color:var(--color-gold-light);margin-left:8px;"></i>مساحة التخزين</span>
            </div>
            <div style="font-size:1.4rem;font-weight:800;" class="gold-text-static">
                <?= formatFileSize((int) $stats['total_storage']) ?>
            </div>
            <div class="storage-bar">
                <div class="storage-bar-fill" style="width: <?= min(100, ((int)$stats['total_storage'] / (1024*1024*1024)) * 10) ?>%;"></div>
            </div>
            <p style="font-size:0.8rem;color:var(--color-silver-muted);">إجمالي حجم الصور المرفوعة</p>
        </div>

        <?php if (hasPermission('view_activity_log') && !empty($recentActivity)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--color-gold-light);margin-left:8px;"></i>آخر النشاطات</span>
            </div>
            <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                    <span class="activity-icon"><i class="fa-solid fa-circle-dot"></i></span>
                    <div>
                        <div class="activity-text">
                            <strong><?= e($activity['full_name'] ?: $activity['username'] ?: 'النظام') ?></strong>
                            — <?= e($activity['details'] ?: $activity['action']) ?>
                        </div>
                        <div class="activity-time"><?= formatArabicDate($activity['created_at']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div style="text-align:center;margin-top:10px;">
                <a href="activity-log.php" class="btn btn-outline btn-sm">عرض السجل الكامل</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>.gold-text-static { background: var(--gradient-gold); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }</style>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
