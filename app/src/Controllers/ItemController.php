<?php

declare(strict_types=1);

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../Repositories/ItemRepository.php';

class ItemController
{
    private ItemRepository $repo;

    public function __construct()
    {
        $this->repo = new ItemRepository();
    }

    // ── List ─────────────────────────────────────────────────────

    public function index(array $params): void
    {
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'tag'    => trim($_GET['tag'] ?? ''),
            'flag'   => trim($_GET['flag'] ?? ''),
        ];

        $items    = $this->repo->findAll($filters);
        $allTags  = $this->repo->allTags();
        $allFlags = $this->repo->allFlags();

        require __DIR__ . '/../../templates/items/index.php';
    }

    // ── Show ─────────────────────────────────────────────────────

    public function show(array $params): void
    {
        $id   = (int) $params['id'];
        $item = $this->repo->findById($id);

        if (!$item) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        require __DIR__ . '/../../templates/items/show.php';
    }

    // ── Create form ──────────────────────────────────────────────

    public function create(array $params): void
    {
        $item   = ['item_name' => '', 'author_name' => '', 'category' => '', 'link' => '', 'link_image' => '', 'tags' => '', 'notes' => '', 'rating' => '', 'flag' => ''];
        $errors = [];
        require __DIR__ . '/../../templates/items/form.php';
    }

    // ── Store (POST) ─────────────────────────────────────────────

    public function store(array $params): void
    {
        verifyCsrf();

        $data   = $this->sanitize($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            $item = $data;
            require __DIR__ . '/../../templates/items/form.php';
            return;
        }

        $id = $this->repo->create($data);
        header('Location: ' . url('items/' . $id));
        exit;
    }

    // ── Edit form ────────────────────────────────────────────────

    public function edit(array $params): void
    {
        $id   = (int) $params['id'];
        $item = $this->repo->findById($id);

        if (!$item) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $errors = [];
        require __DIR__ . '/../../templates/items/form.php';
    }

    // ── Update (POST) ────────────────────────────────────────────

    public function update(array $params): void
    {
        verifyCsrf();

        $id   = (int) $params['id'];
        $data   = $this->sanitize($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            $item = array_merge($data, ['id' => $id]);
            require __DIR__ . '/../../templates/items/form.php';
            return;
        }

        $this->repo->update($id, $data);
        header('Location: ' . url('items/' . $id));
        exit;
    }

    // ── Delete (POST) ────────────────────────────────────────────

    public function destroy(array $params): void
    {
        verifyCsrf();

        $id = (int) $params['id'];
        $this->repo->delete($id);
        header('Location: ' . url('items'));
        exit;
    }

    // ── Validation ───────────────────────────────────────────────

    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['item_name'])) {
            $errors['item_name'] = 'Item name is required.';
        }

        if ($data['rating'] !== '' && $data['rating'] !== null) {
            $r = (int) $data['rating'];
            if ($r < 1 || $r > 100) {
                $errors['rating'] = 'Rating must be between 1 and 100.';
            }
        }

        if (!empty($data['link']) && !filter_var($data['link'], FILTER_VALIDATE_URL)) {
            $errors['link'] = 'Link must be a valid URL.';
        }

        if (!empty($data['link_image']) && !filter_var($data['link_image'], FILTER_VALIDATE_URL)) {
            $errors['link_image'] = 'Image link must be a valid URL.';
        }

        return $errors;
    }

    private function sanitize(array $post): array
    {
        return [
            'item_name'   => trim($post['item_name'] ?? ''),
            'author_name' => trim($post['author_name'] ?? ''),
            'category'    => trim($post['category'] ?? ''),
            'link'        => trim($post['link'] ?? ''),
            'link_image'  => trim($post['link_image'] ?? ''),
            'tags'        => trim($post['tags'] ?? ''),
            'notes'       => trim($post['notes'] ?? ''),
            'rating'      => trim($post['rating'] ?? ''),
            'flag'        => trim($post['flag'] ?? ''),
        ];
    }
}
