<?php

declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ItemRepository.php';
require_once __DIR__ . '/../Services/CsvService.php';

/**
 * MCP controller — exposes tool listing + tool calling endpoints.
 *
 * GET  /mcp/tools  → list available tools with JSON schemas
 * POST /mcp/call   → { "tool": "items.list", "arguments": { ... } }
 */
class McpController
{
    private ItemRepository $repo;
    private CsvService $csv;

    /** Tool registry: name → [description, inputSchema, handler] */
    private array $tools;

    public function __construct()
    {
        $this->repo = new ItemRepository();
        $this->csv  = new CsvService();
        $this->registerTools();
    }

    // ── Endpoints ────────────────────────────────────────────────

    public function listTools(array $params): void
    {
        $list = [];
        foreach ($this->tools as $name => $tool) {
            $list[] = [
                'name'        => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }
        $this->json(['tools' => $list]);
    }

    public function call(array $params): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        if (!is_array($body) || empty($body['tool'])) {
            $this->json(['error' => 'Missing "tool" field in request body.'], 400);
            return;
        }

        $toolName  = $body['tool'];
        $arguments = $body['arguments'] ?? [];

        if (!isset($this->tools[$toolName])) {
            $this->json(['error' => "Unknown tool: $toolName"], 404);
            return;
        }

        // Audit log
        $this->auditLog($toolName, $arguments);

        try {
            $result = ($this->tools[$toolName]['handler'])($arguments);
            $this->json([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_UNICODE)],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'error'   => $e->getMessage(),
                'content' => [
                    ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
                ],
            ], 500);
        }
    }

    // ── Tool registration ────────────────────────────────────────

    private function registerTools(): void
    {
        $this->tools = [

            'items.list' => [
                'description' => 'List items with optional filters (search, tag, flag).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Text search across name, author, and notes.'],
                        'tag'    => ['type' => 'string', 'description' => 'Filter by exact tag name.'],
                        'flag'   => ['type' => 'string', 'description' => 'Filter by flag value (e.g. revisit, completed).'],
                    ],
                ],
                'handler' => function (array $args): array {
                    return $this->repo->findAll([
                        'search' => $args['search'] ?? '',
                        'tag'    => $args['tag'] ?? '',
                        'flag'   => $args['flag'] ?? '',
                    ]);
                },
            ],

            'items.get' => [
                'description' => 'Get a single item by ID.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Item ID.'],
                    ],
                    'required' => ['id'],
                ],
                'handler' => function (array $args): ?array {
                    $item = $this->repo->findById((int) $args['id']);
                    if (!$item) {
                        throw new \RuntimeException('Item not found.');
                    }
                    return $item;
                },
            ],

            'items.create' => [
                'description' => 'Create a new item.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'item_name'   => ['type' => 'string', 'description' => 'Name/title of the item (required).'],
                        'author_name' => ['type' => 'string', 'description' => 'Author name.'],
                        'link'        => ['type' => 'string', 'description' => 'URL link.'],
                        'tags'        => ['type' => 'string', 'description' => 'Tags separated by semicolons or commas.'],
                        'notes'       => ['type' => 'string', 'description' => 'Free-text notes.'],
                        'rating'      => ['type' => 'integer', 'description' => 'Rating 1–100.'],
                        'flag'        => ['type' => 'string', 'description' => 'Flag value (revisit, completed).'],
                    ],
                    'required' => ['item_name'],
                ],
                'handler' => function (array $args): array {
                    $this->validateItemData($args);
                    $data = $this->normalizeItemData($args);
                    $id = $this->repo->create($data);
                    return $this->repo->findById($id);
                },
            ],

            'items.update' => [
                'description' => 'Update an existing item by ID. Only provided fields are changed.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'          => ['type' => 'integer', 'description' => 'Item ID (required).'],
                        'item_name'   => ['type' => 'string'],
                        'author_name' => ['type' => 'string'],
                        'link'        => ['type' => 'string'],
                        'tags'        => ['type' => 'string'],
                        'notes'       => ['type' => 'string'],
                        'rating'      => ['type' => 'integer'],
                        'flag'        => ['type' => 'string'],
                    ],
                    'required' => ['id'],
                ],
                'handler' => function (array $args): array {
                    $id   = (int) $args['id'];
                    $item = $this->repo->findById($id);
                    if (!$item) {
                        throw new \RuntimeException('Item not found.');
                    }
                    // Merge: keep existing values for missing fields
                    $merged = [
                        'item_name'   => $args['item_name']   ?? $item['item_name'],
                        'author_name' => $args['author_name'] ?? $item['author_name'],
                        'link'        => $args['link']        ?? $item['link'] ?? '',
                        'tags'        => $args['tags']         ?? $item['tags'] ?? '',
                        'notes'       => $args['notes']       ?? $item['notes'] ?? '',
                        'rating'      => array_key_exists('rating', $args) ? (string) $args['rating'] : (string) ($item['rating'] ?? ''),
                        'flag'        => array_key_exists('flag', $args) ? ($args['flag'] ?? '') : ($item['flag'] ?? ''),
                    ];
                    $this->validateItemData($merged);
                    $this->repo->update($id, $merged);
                    return $this->repo->findById($id);
                },
            ],

            'items.delete' => [
                'description' => 'Delete an item by ID.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Item ID.'],
                    ],
                    'required' => ['id'],
                ],
                'handler' => function (array $args): array {
                    $id   = (int) $args['id'];
                    $item = $this->repo->findById($id);
                    if (!$item) {
                        throw new \RuntimeException('Item not found.');
                    }
                    $this->repo->delete($id);
                    return ['deleted' => true, 'id' => $id];
                },
            ],

            'items.export_csv' => [
                'description' => 'Export all items as CSV text.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [],
                ],
                'handler' => function (array $args): array {
                    $stream = fopen('php://temp', 'r+');
                    $this->csv->export($stream);
                    rewind($stream);
                    $output = stream_get_contents($stream);
                    fclose($stream);
                    return ['csv' => $output];
                },
            ],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function validateItemData(array $data): void
    {
        if (isset($data['item_name']) && trim($data['item_name']) === '') {
            throw new \InvalidArgumentException('item_name is required.');
        }
        $rating = $data['rating'] ?? '';
        if ($rating !== '' && $rating !== null) {
            $r = (int) $rating;
            if ($r < 1 || $r > 100) {
                throw new \InvalidArgumentException('rating must be between 1 and 100.');
            }
        }
        if (!empty($data['link']) && !filter_var($data['link'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('link must be a valid URL.');
        }
    }

    private function normalizeItemData(array $args): array
    {
        return [
            'item_name'   => trim($args['item_name'] ?? ''),
            'author_name' => trim($args['author_name'] ?? ''),
            'link'        => trim($args['link'] ?? ''),
            'tags'        => trim($args['tags'] ?? ''),
            'notes'       => trim($args['notes'] ?? ''),
            'rating'      => isset($args['rating']) ? (string) $args['rating'] : '',
            'flag'        => trim($args['flag'] ?? ''),
        ];
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private function auditLog(string $tool, array $arguments): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        $entry = [
            'time'      => date('c'),
            'tool'      => $tool,
            'arguments' => $arguments,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];
        @file_put_contents(
            $logDir . '/mcp-audit.log',
            json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
