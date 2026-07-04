<?php
/**
 * فئة نموذج الألبومات - Album Model
 */

class Album
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * إنشاء ألبوم جديد
     */
    public function create(array $data): int
    {
        $slug = createSlug($data['title']);
        $password = null;
        $isProtected = 0;

        if (!empty($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $isProtected = 1;
        }

        $stmt = $this->db->prepare('
            INSERT INTO albums (title, slug, description, password, is_protected, is_published, is_featured, category_id, created_by, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['title'],
            $slug,
            $data['description'] ?? null,
            $password,
            $isProtected,
            $data['is_published'] ?? 1,
            $data['is_featured'] ?? 0,
            $data['category_id'] ?: null,
            $_SESSION['admin_id'] ?? null,
            $data['sort_order'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * تحديث بيانات ألبوم موجود
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $params[] = $data['title'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
        }
        if (isset($data['category_id'])) {
            $fields[] = 'category_id = ?';
            $params[] = $data['category_id'] ?: null;
        }
        if (isset($data['is_published'])) {
            $fields[] = 'is_published = ?';
            $params[] = $data['is_published'];
        }
        if (isset($data['is_featured'])) {
            $fields[] = 'is_featured = ?';
            $params[] = $data['is_featured'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = $data['sort_order'];
        }

        // تحديث كلمة المرور فقط إن تم إرسال قيمة جديدة
        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $fields[] = 'password = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
                $fields[] = 'is_protected = 1';
            } elseif ($data['password'] === '' && isset($data['remove_password'])) {
                $fields[] = 'password = NULL';
                $fields[] = 'is_protected = 0';
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE albums SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * الحصول على ألبوم عن طريق المعرف
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT a.*, c.name as category_name,
                (SELECT COUNT(*) FROM photos WHERE album_id = a.id) as photos_count
            FROM albums a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.id = ?
        ');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * الحصول على ألبوم عن طريق الـ slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('
            SELECT a.*, c.name as category_name,
                (SELECT COUNT(*) FROM photos WHERE album_id = a.id) as photos_count
            FROM albums a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.slug = ?
        ');
        $stmt->execute([$slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * الحصول على كل الألبومات (للوحة التحكم - بدون فلترة النشر)
     */
    public function getAll(array $filters = []): array
    {
        $sql = '
            SELECT a.*, c.name as category_name,
                (SELECT COUNT(*) FROM photos WHERE album_id = a.id) as photos_count
            FROM albums a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND a.title LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND a.category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (isset($filters['is_published'])) {
            $sql .= ' AND a.is_published = ?';
            $params[] = $filters['is_published'];
        }

        $sql .= ' ORDER BY a.sort_order ASC, a.created_at DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . (int) $filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= ' OFFSET ' . (int) $filters['offset'];
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * الحصول على الألبومات المنشورة فقط (للعرض العام)
     */
    public function getPublished(int $limit = 12, int $offset = 0, ?int $categoryId = null): array
    {
        $filters = ['is_published' => 1, 'limit' => $limit, 'offset' => $offset];
        if ($categoryId) {
            $filters['category_id'] = $categoryId;
        }
        return $this->getAll($filters);
    }

    /**
     * عدد كل الألبومات (لأغراض الترقيم الصفحي)
     */
    public function countAll(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as cnt FROM albums WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND title LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['is_published'])) {
            $sql .= ' AND is_published = ?';
            $params[] = $filters['is_published'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND category_id = ?';
            $params[] = $filters['category_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['cnt'];
    }

    /**
     * حذف ألبوم (سيحذف الصور المرتبطة تلقائياً بسبب ON DELETE CASCADE)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM albums WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * التحقق من كلمة مرور الألبوم
     */
    public function verifyPassword(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password FROM albums WHERE id = ?');
        $stmt->execute([$id]);
        $album = $stmt->fetch();

        if (!$album || empty($album['password'])) {
            return false;
        }

        return password_verify($password, $album['password']);
    }

    /**
     * تحديث صورة الغلاف
     */
    public function updateCover(int $id, string $coverImage): bool
    {
        $stmt = $this->db->prepare('UPDATE albums SET cover_image = ? WHERE id = ?');
        return $stmt->execute([$coverImage, $id]);
    }

    /**
     * زيادة عداد المشاهدات
     */
    public function incrementViews(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE albums SET views_count = views_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * تبديل حالة النشر
     */
    public function togglePublished(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE albums SET is_published = NOT is_published WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * تبديل حالة التمييز (مميز/غير مميز)
     */
    public function toggleFeatured(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE albums SET is_featured = NOT is_featured WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * إحصائيات عامة للوحة التحكم
     */
    public function getStats(): array
    {
        $stats = [];

        $stmt = $this->db->query('SELECT COUNT(*) as cnt FROM albums');
        $stats['total_albums'] = $stmt->fetch()['cnt'];

        $stmt = $this->db->query('SELECT COUNT(*) as cnt FROM photos');
        $stats['total_photos'] = $stmt->fetch()['cnt'];

        $stmt = $this->db->query('SELECT COUNT(*) as cnt FROM albums WHERE is_protected = 1');
        $stats['protected_albums'] = $stmt->fetch()['cnt'];

        $stmt = $this->db->query('SELECT SUM(views_count) as total FROM albums');
        $stats['total_views'] = $stmt->fetch()['total'] ?? 0;

        $stmt = $this->db->query('SELECT SUM(file_size) as total FROM photos');
        $stats['total_storage'] = $stmt->fetch()['total'] ?? 0;

        return $stats;
    }
}
