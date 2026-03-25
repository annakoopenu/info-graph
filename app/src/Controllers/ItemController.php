<?php

declare(strict_types=1);

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../Repositories/ItemRepository.php';
require_once __DIR__ . '/../Services/ImageReplacementService.php';

class ItemController
{
    private ItemRepository $repo;
    private ImageReplacementService $imageReplacementService;

    public function __construct()
    {
        $this->repo = new ItemRepository();
        $this->imageReplacementService = new ImageReplacementService();
    }

    // ── List ─────────────────────────────────────────────────────

    public function index(array $params): void
    {
        $collection = $this->currentCollection($_GET);
        $repo = $this->repoForCollection($collection);
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'tag'    => trim($_GET['tag'] ?? ''),
            'category' => trim($_GET['category'] ?? ''),
        ];

        $items         = $repo->findAll($filters);
        $allTags       = $repo->allTags();
        $allCategories = $repo->allCategories();

        require __DIR__ . '/../../templates/items/index.php';
    }

    // ── Show ─────────────────────────────────────────────────────

    public function show(array $params): void
    {
        $collection = $this->currentCollection($_GET);
        $repo = $this->repoForCollection($collection);
        $id   = (int) $params['id'];
        $item = $repo->findById($id);

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
        $collection = $this->currentCollection($_GET);
        $item   = ['item_name' => '', 'author_name' => '', 'category' => '', 'link' => '', 'link_image' => '', 'tags' => '', 'notes' => '', 'rating' => '', 'flag' => ''];
        $errors = [];
        $formCategories = $collection === 'people' ? $this->repoForCollection('people')->allCategories() : [];
        require __DIR__ . '/../../templates/items/form.php';
    }

    // ── Store (POST) ─────────────────────────────────────────────

    public function store(array $params): void
    {
        verifyCsrf();

        $collection = $this->currentCollection($_POST);
        $repo = $this->repoForCollection($collection);
        $data   = $this->sanitize($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            $item = $data;
            $formCategories = $collection === 'people' ? $this->repoForCollection('people')->allCategories() : [];
            require __DIR__ . '/../../templates/items/form.php';
            return;
        }

        $id = $repo->create($data);
        header('Location: ' . $this->collectionUrl('items/' . $id, $collection));
        exit;
    }

    // ── Edit form ────────────────────────────────────────────────

    public function edit(array $params): void
    {
        $collection = $this->currentCollection($_GET);
        $repo = $this->repoForCollection($collection);
        $id   = (int) $params['id'];
        $item = $repo->findById($id);

        if (!$item) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $errors = [];
        $formCategories = $collection === 'people' ? $this->repoForCollection('people')->allCategories() : [];
        require __DIR__ . '/../../templates/items/form.php';
    }

    // ── Update (POST) ────────────────────────────────────────────

    public function update(array $params): void
    {
        verifyCsrf();

        $collection = $this->currentCollection($_POST);
        $repo = $this->repoForCollection($collection);
        $id   = (int) $params['id'];
        $data   = $this->sanitize($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            $item = array_merge($data, ['id' => $id]);
            $formCategories = $collection === 'people' ? $this->repoForCollection('people')->allCategories() : [];
            require __DIR__ . '/../../templates/items/form.php';
            return;
        }

        $repo->update($id, $data);
        header('Location: ' . $this->collectionUrl('items/' . $id, $collection));
        exit;
    }

    // ── Delete (POST) ────────────────────────────────────────────

    public function destroy(array $params): void
    {
        verifyCsrf();

        $collection = $this->currentCollection($_POST);
        $repo = $this->repoForCollection($collection);
        $id = (int) $params['id'];
        $repo->delete($id);
        header('Location: ' . $this->collectionUrl('items', $collection));
        exit;
    }

    public function replaceImage(array $params): void
    {
        verifyCsrf();

        $collection = $this->currentCollection($_POST);
        $repo = $this->repoForCollection($collection);
        $id = (int) $params['id'];
        $item = $repo->findById($id);

        if (!$item) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $replacementUrl = $this->imageReplacementService->findReplacementUrl($item);
        if ($replacementUrl === null) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'No replacement image was found for this item.',
            ];
            header('Location: ' . $this->collectionUrl('items/' . $id . '/edit', $collection));
            exit;
        }

        $repo->update($id, array_merge($item, ['link_image' => $replacementUrl]));
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Image replaced successfully.',
        ];

        header('Location: ' . $this->collectionUrl('items/' . $id . '/edit', $collection));
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
            'collection'  => trim($post['collection'] ?? ''),
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

    private function currentCollection(array $source): string
    {
        $collection = trim((string) ($source['collection'] ?? 'items'));
        if (!in_array($collection, ['items', 'people', 'groups'], true)) {
            return 'items';
        }

        return $collection;
    }

    private function repoForCollection(string $collection): ItemRepository
    {
        return new ItemRepository(collectionDataFilePath($collection), $collection);
    }

    private function collectionUrl(string $path, string $collection): string
    {
        if ($collection === 'items') {
            return url($path);
        }

        return url($path) . '?' . http_build_query(['collection' => $collection]);
    }
}
