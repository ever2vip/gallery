<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/Album.php';

$albumModel = new Album();
$db = Database::getInstance();

$itemsPerPage = (int) getSetting('items_per_page', '12');
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

$search = trim($_GET['search'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');

$categoryId = null;
if ($categorySlug) {
    $stmt = $db->prepare('SELECT id FROM categories WHERE slug = ?');
    $stmt->execute([$categorySlug]);
    $cat = $stmt->fetch();
    $categoryId = $cat ? (int) $cat['id'] : null;
}

$filters = ['is_published' => 1, 'limit' => $itemsPerPage, 'offset' => $offset];
if ($search) $filters['search'] = $search;
if ($categoryId) $filters['category_id'] = $categoryId;

$albums = $albumModel->getAll($filters);
$totalAlbums = $albumModel->countAll($filters);
$totalPages = (int) ceil($totalAlbums / $itemsPerPage);

$categories = $db->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();

$pageTitle = 'الألبومات';
require_once __DIR__ . '/includes/header.php';
?>

<section style="padding: 20px 0 40px;">
    <h1 class="section-title">جميع <span class="gold-text">الألبومات</span></h1>
    <p class="section-subtitle">تصفح مجموعتنا الكاملة من الألبومات المصورة</p>

    <form method="get" style="max-width:500px;margin:0 auto 35px;">
        <div style="position:relative;">
            <input type="text" name="search" class="form-control" placeholder="ابحث عن ألبوم..." value="<?= e($search) ?>" style="padding-left:50px;">
            <button type="submit" style="position:absolute;left:6px;top:50%;transform:translateY(-50%);background:var(--gradient-gold);border:none;width:38px;height:38px;border-radius:50%;color:var(--color-black);cursor:pointer;">
                <i class="fa-solid fa-search"></i>
            </button>
        </div>
    </form>

    <?php if (!empty($categories)): ?>
    <div class="filter-bar">
        <a href="albums.php" class="filter-chip <?= !$categorySlug ? 'active' : '' ?>">الكل</a>
        <?php foreach ($categories as $cat): ?>
            <a href="albums.php?category=<?= e($cat['slug']) ?>" class="filter-chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($albums)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <h3>لا توجد نتائج</h3>
            <p><?= $search ? 'لم يتم العثور على ألبومات تطابق بحثك' : 'لا توجد ألبومات في هذا التصنيف حالياً' ?></p>
        </div>
    <?php else: ?>
        <div class="albums-grid">
            <?php foreach ($albums as $album): ?>
                <?php require __DIR__ . '/includes/partials/album-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : '' ?>"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : '' ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
