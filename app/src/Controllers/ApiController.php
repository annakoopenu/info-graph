<?php

declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ItemRepository.php';

/**
 * REST JSON API controller for ChatGPT Actions and general API use.
 *
 * All endpoints return JSON directly with proper Content-Type.
 */
class ApiController
{
    private ItemRepository $repo;

    public function __construct()
    {
        $this->repo = new ItemRepository();
    }

    // ── GET /api/items ───────────────────────────────────────────

    public function listItems(array $params): void
    {
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'tag'    => trim($_GET['tag'] ?? ''),
            'flag'   => trim($_GET['flag'] ?? ''),
        ];
        $items = $this->repo->findAll($filters);
        $this->json(['items' => $items, 'count' => count($items)]);
    }

    // ── POST /api/items ──────────────────────────────────────────

    public function createItem(array $params): void
    {
        $body   = $this->readBody();
        $errors = $this->validate($body);

        if ($errors) {
            $this->json(['error' => implode('; ', $errors)], 400);
            return;
        }

        $data = $this->normalize($body);
        $id   = $this->repo->create($data);
        $item = $this->repo->findById($id);
        $this->json(['item' => $item, 'message' => 'Item created.'], 201);
    }

    // ── GET /api/items/{id} ──────────────────────────────────────

    public function getItem(array $params): void
    {
        $item = $this->repo->findById((int) $params['id']);
        if (!$item) {
            $this->json(['error' => 'Item not found.'], 404);
            return;
        }
        $this->json(['item' => $item]);
    }

    // ── PATCH /api/items/{id} (emulate via POST for compatibility) ─

    public function updateItem(array $params): void
    {
        $id   = (int) $params['id'];
        $item = $this->repo->findById($id);
        if (!$item) {
            $this->json(['error' => 'Item not found.'], 404);
            return;
        }

        $body = $this->readBody();

        // Merge: keep existing values for fields not provided
        $merged = [
            'item_name'   => $body['item_name']   ?? $item['item_name'],
            'author_name' => $body['author_name'] ?? $item['author_name'],
            'link'        => $body['link']        ?? ($item['link'] ?? ''),
            'tags'        => $body['tags']        ?? ($item['tags'] ?? ''),
            'notes'       => $body['notes']       ?? ($item['notes'] ?? ''),
            'rating'      => array_key_exists('rating', $body) ? (string) $body['rating'] : (string) ($item['rating'] ?? ''),
            'flag'        => array_key_exists('flag', $body) ? ($body['flag'] ?? '') : ($item['flag'] ?? ''),
        ];

        $errors = $this->validate($merged);
        if ($errors) {
            $this->json(['error' => implode('; ', $errors)], 400);
            return;
        }

        $this->repo->update($id, $merged);
        $updated = $this->repo->findById($id);
        $this->json(['item' => $updated, 'message' => 'Item updated.']);
    }

    // ── DELETE /api/items/{id} (emulate via POST for compatibility) ─

    public function deleteItem(array $params): void
    {
        $id   = (int) $params['id'];
        $item = $this->repo->findById($id);
        if (!$item) {
            $this->json(['error' => 'Item not found.'], 404);
            return;
        }

        $this->repo->delete($id);
        $this->json(['message' => "Item $id deleted.", 'deleted_item' => $item]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function readBody(): array
    {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        return is_array($body) ? $body : [];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty(trim($data['item_name'] ?? ''))) {
            $errors[] = 'item_name is required';
        }
        $rating = $data['rating'] ?? '';
        if ($rating !== '' && $rating !== null) {
            $r = (int) $rating;
            if ($r < 1 || $r > 100) {
                $errors[] = 'rating must be between 1 and 100';
            }
        }
        if (!empty($data['link']) && !filter_var($data['link'], FILTER_VALIDATE_URL)) {
            $errors[] = 'link must be a valid URL';
        }
        return $errors;
    }

    private function normalize(array $body): array
    {
        return [
            'item_name'   => trim($body['item_name'] ?? ''),
            'author_name' => trim($body['author_name'] ?? ''),
            'link'        => trim($body['link'] ?? ''),
            'tags'        => trim($body['tags'] ?? ''),
            'notes'       => trim($body['notes'] ?? ''),
            'rating'      => isset($body['rating']) ? (string) $body['rating'] : '',
            'flag'        => trim($body['flag'] ?? ''),
        ];
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
