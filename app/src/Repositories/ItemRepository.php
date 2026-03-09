<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';

class ItemRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    // ── List / filter ────────────────────────────────────────────

    /**
     * Return items with optional filters.
     * Each item includes a `tags` key (comma-separated string).
     */
    public function findAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(i.item_name LIKE :search OR i.author_name LIKE :search2 OR i.notes LIKE :search3)';
            $like     = '%' . $filters['search'] . '%';
            $params['search']  = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
        }
        if (!empty($filters['tag'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM item_tags it2 JOIN tags t2 ON t2.id = it2.tag_id WHERE it2.item_id = i.id AND t2.name = :tag)';
            $params['tag'] = $filters['tag'];
        }
        if (!empty($filters['flag'])) {
            $where[]  = 'i.flag = :flag';
            $params['flag'] = $filters['flag'];
        }

        $sql = "SELECT i.*,
                       GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tags
                FROM items i
                LEFT JOIN item_tags it ON it.item_id = i.id
                LEFT JOIN tags t       ON t.id = it.tag_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY i.id ORDER BY i.updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Single item ──────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*,
                    GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tags
             FROM items i
             LEFT JOIN item_tags it ON it.item_id = i.id
             LEFT JOIN tags t       ON t.id = it.tag_id
             WHERE i.id = :id
             GROUP BY i.id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ───────────────────────────────────────────────────

    public function create(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO items (item_name, author_name, link, notes, rating, flag)
                 VALUES (:item_name, :author_name, :link, :notes, :rating, :flag)"
            );
            $stmt->execute([
                'item_name'   => $data['item_name'],
                'author_name' => $data['author_name'] ?? '',
                'link'        => $data['link'] ?: null,
                'notes'       => $data['notes'] ?: null,
                'rating'      => $data['rating'] !== '' ? (int) $data['rating'] : null,
                'flag'        => $data['flag'] ?: null,
            ]);
            $itemId = (int) $this->db->lastInsertId();

            $this->syncTags($itemId, $data['tags'] ?? '');

            $this->db->commit();
            return $itemId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Update ───────────────────────────────────────────────────

    public function update(int $id, array $data): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE items
                 SET item_name   = :item_name,
                     author_name = :author_name,
                     link        = :link,
                     notes       = :notes,
                     rating      = :rating,
                     flag        = :flag
                 WHERE id = :id"
            );
            $stmt->execute([
                'id'          => $id,
                'item_name'   => $data['item_name'],
                'author_name' => $data['author_name'] ?? '',
                'link'        => $data['link'] ?: null,
                'notes'       => $data['notes'] ?: null,
                'rating'      => $data['rating'] !== '' ? (int) $data['rating'] : null,
                'flag'        => $data['flag'] ?: null,
            ]);

            $this->syncTags($id, $data['tags'] ?? '');

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Delete ───────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM items WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    // ── Tag helpers ──────────────────────────────────────────────

    /**
     * Replace all tags for an item.
     * $tagsString: comma or semicolon-separated tag names.
     */
    private function syncTags(int $itemId, string $tagsString): void
    {
        // Remove existing
        $this->db->prepare("DELETE FROM item_tags WHERE item_id = :id")->execute(['id' => $itemId]);

        $names = array_unique(array_filter(array_map('trim', preg_split('/[;,]+/', $tagsString))));
        if (empty($names)) {
            return;
        }

        foreach ($names as $name) {
            // Upsert tag
            $this->db->prepare("INSERT IGNORE INTO tags (name) VALUES (:name)")->execute(['name' => $name]);
            $tagId = $this->db->prepare("SELECT id FROM tags WHERE name = :name");
            $tagId->execute(['name' => $name]);
            $tid = (int) $tagId->fetchColumn();

            $this->db->prepare("INSERT INTO item_tags (item_id, tag_id) VALUES (:iid, :tid)")
                     ->execute(['iid' => $itemId, 'tid' => $tid]);
        }
    }

    // ── Utility ──────────────────────────────────────────────────

    /** Return all distinct tag names (for filter dropdowns). */
    public function allTags(): array
    {
        return $this->db->query("SELECT name FROM tags ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Return all distinct flag values currently in use. */
    public function allFlags(): array
    {
        return $this->db->query("SELECT DISTINCT flag FROM items WHERE flag IS NOT NULL AND flag != '' ORDER BY flag")
                        ->fetchAll(PDO::FETCH_COLUMN);
    }
}
