<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_categories');

$db = Database::getInstance();

/**
 * توليد رابط صديق فريد للتصنيف دون لاحقة عشوائية (بعكس ألبومات الصور)
 */
function generateCategorySlug(PDO $db, string $name, ?int $excludeId = null): string
{
    $base = trim($name);
    $base = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $base);
    $base = preg_replace('/[\s-]+/u', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'category';
    }

    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT COUNT(*) as cnt FROM categories WHERE slug = ?' . ($excludeId ? ' AND id != ?' : '');
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetch()['cnt'] === 0) {
            break;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editCategory = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        redirectWithMessage('categories.php', 'حدث خطأ في التحقق من الطلب', 'danger');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$catId]);
        logActivity('delete_category', 'حذف تصنيف رقم ' . $catId);
        redirectWithMessage('categories.php', 'تم حذف التصنيف. الألبومات المرتبطة به أصبحت بلا تصنيف.');
    }

    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $catId = (int) ($_POST['category_id'] ?? 0);

        if (empty($name)) {
            $errors[] = 'اسم التصنيف مطلوب';
        }

        if (empty($errors)) {
            if ($catId) {
                $slug = generateCategorySlug($db, $name, $catId);
                $stmt = $db->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([$name, $slug, $description, $sortOrder, $catId]);
                logActivity('update_category', 'تعديل التصنيف: ' . $name);
                redirectWithMessage('categories.php', 'تم تحديث التصنيف بنجاح');
            } else {
                $slug = generateCategorySlug($db, $name);
                $stmt = $db->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $slug, $description, $sortOrder]);
                logActivity('create_category', 'إنشاء تصنيف جديد: ' . $name);
                redirectWithMessage('categories.php', 'تم إضافة التصنيف بنجاح');
            }
        }
    }
}

if ($editId) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch() ?: null;
}

$categories = $db->query('
    SELECT c.*, (SELECT COUNT(*) FROM albums WHERE category_id = c.id) as albums_count
    FROM categories c
    ORDER BY c.sort_order ASC, c.name ASC
')->fetchAll();

$pageTitle = 'التصنيفات';
$breadcrumb = 'الرئيسية / التصنيفات';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title"><?= $editCategory ? 'تعديل التصنيف' : 'إضافة تصنيف جديد' ?></span>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e(implode('، ', $errors)) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" value="<?= (int) ($editCategory['id'] ?? 0) ?>">
            <div class="form-group">
                <label class="form-label">اسم التصنيف <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= e($editCategory['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">وصف مختصر</label>
                <textarea name="description" class="form-control"><?= e($editCategory['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">ترتيب الظهور</label>
                <input type="number" name="sort_order" class="form-control" value="<?= (int) ($editCategory['sort_order'] ?? 0) ?>">
                <p class="form-hint">الرقم الأصغر يظهر أولاً في قوائم الفلترة</p>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-check"></i> <?= $editCategory ? 'حفظ التعديلات' : 'إضافة التصنيف' ?>
            </button>
            <?php if ($editCategory): ?>
                <a href="categories.php" class="btn btn-outline btn-block" style="margin-top:10px;">إلغاء التعديل</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">كل التصنيفات (<?= count($categories) ?>)</span>
        </div>
        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-tags"></i>
                <h3>لا توجد تصنيفات بعد</h3>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>الاسم</th><th>عدد الألبومات</th><th>الترتيب</th><th>إجراءات</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong><?= e($cat['name']) ?></strong></td>
                        <td><span class="badge badge-gold"><?= (int) $cat['albums_count'] ?></span></td>
                        <td><?= (int) $cat['sort_order'] ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="?edit=<?= (int) $cat['id'] ?>" class="action-icon-btn edit" title="تعديل"><i class="fa-solid fa-pen"></i></a>
                                <form method="post" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                    <button type="button" class="action-icon-btn delete" title="حذف"
                                        data-confirm-delete="هل تريد حذف تصنيف «<?= e($cat['name']) ?>»؟ ستصبح ألبوماته بدون تصنيف.">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
