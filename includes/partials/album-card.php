<?php
/**
 * بطاقة عرض ألبوم واحد - يتم تضمينها ضمن حلقة foreach
 * المتغير المتوقع: $album
 */
?>
<a href="album.php?slug=<?= e($album['slug']) ?>" class="album-card">
    <div class="album-cover">
        <?php if (!empty($album['cover_image'])): ?>
            <img src="<?= UPLOAD_URL . e($album['cover_image']) ?>" alt="<?= e($album['title']) ?>" loading="lazy">
        <?php else: ?>
            <div class="album-cover-placeholder"><i class="fa-solid fa-images"></i></div>
        <?php endif; ?>

        <?php if (!empty($album['is_protected'])): ?>
            <span class="album-lock-badge"><i class="fa-solid fa-lock"></i></span>
        <?php endif; ?>

        <?php if (!empty($album['is_featured'])): ?>
            <span class="album-featured-badge"><i class="fa-solid fa-star"></i> مميز</span>
        <?php else: ?>
            <span class="album-count-badge"><i class="fa-solid fa-image"></i> <?= (int) $album['photos_count'] ?></span>
        <?php endif; ?>

        <div class="album-overlay">
            <span style="color:#fff;font-size:0.85rem;"><i class="fa-solid fa-eye"></i> عرض الألبوم</span>
        </div>
    </div>
    <div class="album-info">
        <h3 class="album-title"><?= e($album['title']) ?></h3>
        <div class="album-meta">
            <span><i class="fa-solid fa-image"></i> <?= (int) $album['photos_count'] ?> صورة</span>
            <?php if (!empty($album['category_name'])): ?>
                <span><i class="fa-solid fa-tag"></i> <?= e($album['category_name']) ?></span>
            <?php endif; ?>
            <span><i class="fa-regular fa-calendar"></i> <?= formatArabicDate($album['created_at']) ?></span>
        </div>
    </div>
</a>
