<?php
/**
 * فئة نموذج الصور - Photo Model
 */

class Photo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * إضافة صورة جديدة لألبوم
     */
    public function create(int $albumId, array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO photos (album_id, filename, original_name, thumbnail, title, file_size, width, height, uploaded_by, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $sortOrder = $this->getNextSortOrder($albumId);

        $stmt->execute([
            $albumId,
            $data['filename'],
            $data['original_name'] ?? null,
            $data['thumbnail'] ?? null,
            $data['title'] ?? null,
            $data['file_size'] ?? null,
            $data['width'] ?? null,
            $data['height'] ?? null,
            $_SESSION['admin_id'] ?? null,
            $sortOrder,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function getNextSortOrder(int $albumId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM photos WHERE album_id = ?');
        $stmt->execute([$albumId]);
        $result = $stmt->fetch();
        return ($result['max_order'] ?? 0) + 1;
    }

    /**
     * الحصول على كل صور ألبوم معين
     */
    public function getByAlbum(int $albumId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM photos WHERE album_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$albumId]);
        return $stmt->fetchAll();
    }

    /**
     * الحصول على صورة عن طريق المعرف
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM photos WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * تحديث بيانات صورة (العنوان والوصف)
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
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = $data['sort_order'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE photos SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * حذف صورة من قاعدة البيانات
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM photos WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * إعادة ترتيب الصور (يُستخدم مع السحب والإفلات في لوحة التحكم)
     */
    public function reorder(array $orderedIds): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE photos SET sort_order = ? WHERE id = ?');
            foreach ($orderedIds as $order => $id) {
                $stmt->execute([$order, (int) $id]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * تعيين صورة كغلاف للألبوم
     */
    public function setCover(int $photoId, int $albumId): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE photos SET is_cover = 0 WHERE album_id = ?');
            $stmt->execute([$albumId]);

            $stmt = $this->db->prepare('UPDATE photos SET is_cover = 1 WHERE id = ?');
            $stmt->execute([$photoId]);

            $photo = $this->getById($photoId);
            $albumModel = new Album();
            $albumModel->updateCover($albumId, $photo['thumbnail'] ?? $photo['filename']);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * زيادة عداد المشاهدات
     */
    public function incrementViews(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE photos SET views_count = views_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * زيادة عداد التحميلات
     */
    public function incrementDownloads(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE photos SET downloads_count = downloads_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * عدد صور ألبوم معين
     */
    public function countByAlbum(int $albumId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as cnt FROM photos WHERE album_id = ?');
        $stmt->execute([$albumId]);
        return (int) $stmt->fetch()['cnt'];
    }

    /**
     * أحدث الصور المضافة (للوحة التحكم)
     */
    public function getRecent(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, a.title as album_title, a.slug as album_slug
            FROM photos p
            JOIN albums a ON p.album_id = a.id
            ORDER BY p.created_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
