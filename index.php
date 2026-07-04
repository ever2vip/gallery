<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/Album.php';

$albumModel = new Album();
$db = Database::getInstance();

// الحصول على أحدث الألبومات المنشورة
$featuredAlbums = $db->query('
    SELECT a.*, (SELECT COUNT(*) FROM photos WHERE album_id = a.id) as photos_count
    FROM albums a
    WHERE a.is_published = 1 AND a.is_featured = 1
    ORDER BY a.created_at DESC
    LIMIT 3
')->fetchAll();

$recentAlbums = $albumModel->getPublished(8, 0);

// التصنيفات لأغراض الفلترة
$categories = $db->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
$stats = $albumModel->getStats();

$pageTitle = 'الرئيسية';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <span class="hero-badge"><i class="fa-solid fa-sparkles"></i> مرحباً بكم</span>
    <h1>مساحتك الخاصة <span class="gold-text">لعرض أجمل اللحظات</span></h1>
    <p>استعرض ألبومات صور منظمة وأنيقة، مع إمكانية حماية الألبومات الخاصة بكلمة مرور لضمان خصوصيتك التامة.</p>
    <div class="hero-actions">
        <a href="albums.php" class="btn btn-primary"><i class="fa-solid fa-images"></i> تصفح الألبومات</a>
        <a href="#recent" class="btn btn-outline"><i class="fa-solid fa-arrow-down"></i> أحدث الإضافات</a>
    </div>
</section>

<!-- إحصائيات سريعة -->
<section style="padding: 20px 0 60px;">
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;">
        <div style="background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(212,175,55,0.1);border-radius:20px;padding:24px;text-align:center;">
            <div style="font-size:1.8rem;font-weight:800;" class="gold-text"><?= number_format((int)$stats['total_albums']) ?></div>
            <div style="color:var(--color-silver-muted);font-size:0.9rem;margin-top:6px;">ألبوم</div>
        </div>
        <div style="background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(212,175,55,0.1);border-radius:20px;padding:24px;text-align:center;">
            <div style="font-size:1.8rem;font-weight:800;" class="gold-text"><?= number_format((int)$stats['total_photos']) ?></div>
            <div style="color:var(--color-silver-muted);font-size:0.9rem;margin-top:6px;">صورة</div>
        </div>
        <div style="background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(212,175,55,0.1);border-radius:20px;padding:24px;text-align:center;">
            <div style="font-size:1.8rem;font-weight:800;" class="gold-text"><?= number_format((int)$stats['total_views']) ?></div>
            <div style="color:var(--color-silver-muted);font-size:0.9rem;margin-top:6px;">مشاهدة</div>
        </div>
        <div style="background:linear-gradient(145deg,#1a1a1e,#131316);border:1px solid rgba(212,175,55,0.1);border-radius:20px;padding:24px;text-align:center;">
            <div style="font-size:1.8rem;font-weight:800;" class="gold-text"><?= number_format((int)$stats['protected_albums']) ?></div>
            <div style="color:var(--color-silver-muted);font-size:0.9rem;margin-top:6px;">ألبوم محمي</div>
        </div>
    </div>
</section>

<?php if (!empty($featuredAlbums)): ?>
<section style="padding-bottom: 60px;">
    <h2 class="section-title">ألبومات <span class="gold-text">مميزة</span></h2>
    <p class="section-subtitle">مجموعة مختارة من أفضل الألبومات المضافة حديثاً</p>

    <div class="albums-grid">
        <?php foreach ($featuredAlbums as $album): ?>
            <?php require __DIR__ . '/includes/partials/album-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section id="recent" style="padding-bottom: 40px;">
    <h2 class="section-title">أحدث <span class="gold-text">الألبومات</span></h2>
    <p class="section-subtitle">تصفح آخر الألبومات المضافة إلى المعرض</p>

    <?php if (!empty($categories)): ?>
    <div class="filter-bar">
        <span class="filter-chip active" data-filter="all">الكل</span>
        <?php foreach ($categories as $cat): ?>
            <span class="filter-chip" data-filter="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($recentAlbums)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-image"></i>
            <h3>لا توجد ألبومات بعد</h3>
            <p>سيتم عرض الألبومات هنا فور إضافتها</p>
        </div>
    <?php else: ?>
        <div class="albums-grid">
            <?php foreach ($recentAlbums as $album): ?>
                <?php require __DIR__ . '/includes/partials/album-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;">
            <a href="albums.php" class="btn btn-outline">عرض كل الألبومات <i class="fa-solid fa-arrow-left"></i></a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
