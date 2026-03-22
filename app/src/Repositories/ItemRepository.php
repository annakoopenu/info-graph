<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class ItemRepository
{
    private string $dataFile;

    public function __construct(?string $dataFile = null)
    {
        $this->dataFile = $dataFile ?? dataFilePath();
    }

    public function findAll(array $filters = []): array
    {
        $items = $this->loadItems();

        if (!empty($filters['search'])) {
            $needle = $this->lower(trim((string) $filters['search']));
            $items = array_values(array_filter($items, function (array $item) use ($needle): bool {
                $haystack = $this->lower(implode(' ', [
                    $item['item_name'] ?? '',
                    $item['author_name'] ?? '',
                    $item['category'] ?? '',
                    $item['tags'] ?? '',
                    $item['notes'] ?? '',
                ]));
                return str_contains($haystack, $needle);
            }));
        }

        if (!empty($filters['tag'])) {
            $tagFilter = $this->lower(trim((string) $filters['tag']));
            $items = array_values(array_filter($items, function (array $item) use ($tagFilter): bool {
                foreach ($this->tagsAsArray($item['tags'] ?? '') as $tag) {
                    if ($this->lower($tag) === $tagFilter) {
                        return true;
                    }
                }
                return false;
            }));
        }

        if (!empty($filters['flag'])) {
            $flagFilter = trim((string) $filters['flag']);
            $items = array_values(array_filter($items, fn(array $item): bool => ($item['flag'] ?? '') === $flagFilter));
        }

        return $items;
    }

    public function findById(int $id): ?array
    {
        foreach ($this->loadItems() as $item) {
            if ((int) $item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    public function create(array $data): int
    {
        $records = $this->loadRawRecords();
        $items = $this->normalizeRecords($records);
        $nextId = 1;

        foreach ($items as $item) {
            $nextId = max($nextId, (int) $item['id'] + 1);
        }

        $timestamp = date('c');
        $records[] = $this->normalizeForStorage($data, [
            'id' => $nextId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $this->saveRecords($records);

        return $nextId;
    }

    public function update(int $id, array $data): void
    {
        $records = $this->loadRawRecords();
        $found = false;

        foreach ($records as $index => $record) {
            $recordId = $this->resolveRecordId($record, $index);
            if ($recordId !== $id) {
                continue;
            }

            $existing = $this->normalizeRecord($record, $recordId);
            $records[$index] = $this->normalizeForStorage($data, [
                'id' => $id,
                'created_at' => $existing['created_at'] ?: date('c'),
                'updated_at' => date('c'),
            ]);
            $found = true;
            break;
        }

        if (!$found) {
            throw new RuntimeException('Item not found.');
        }

        $this->saveRecords($records);
    }

    public function delete(int $id): void
    {
        $records = $this->loadRawRecords();
        $filtered = [];

        foreach ($records as $index => $record) {
            $recordId = $this->resolveRecordId($record, $index);
            if ($recordId !== $id) {
                $filtered[] = $record;
            }
        }

        $this->saveRecords($filtered);
    }

    public function allTags(): array
    {
        $tags = [];
        foreach ($this->loadItems() as $item) {
            foreach ($this->tagsAsArray($item['tags'] ?? '') as $tag) {
                $tags[$this->lower($tag)] = $tag;
            }
        }

        natcasesort($tags);
        return array_values($tags);
    }

    public function allFlags(): array
    {
        $flags = [];
        foreach ($this->loadItems() as $item) {
            $flag = trim((string) ($item['flag'] ?? ''));
            if ($flag !== '') {
                $flags[$flag] = $flag;
            }
        }

        natcasesort($flags);
        return array_values($flags);
    }

    private function loadItems(): array
    {
        return $this->normalizeRecords($this->loadRawRecords());
    }

    private function loadRawRecords(): array
    {
        if (!is_file($this->dataFile)) {
            return [];
        }

        $json = file_get_contents($this->dataFile);
        if ($json === false || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON in data file: ' . $this->dataFile);
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private function normalizeRecords(array $records): array
    {
        $items = [];
        foreach ($records as $index => $record) {
            $items[] = $this->normalizeRecord($record, $this->resolveRecordId($record, $index));
        }
        return $items;
    }

    private function normalizeRecord(array $record, int $id): array
    {
        $rating = $record['rating'] ?? null;
        $rating = ($rating === '' || $rating === null) ? null : (int) round((float) $rating);

        return [
            'id' => $id,
            'item_name' => trim((string) ($record['item_name'] ?? '')),
            'author_name' => trim((string) ($record['author_name'] ?? '')),
            'category' => trim((string) ($record['category'] ?? '')),
            'link' => trim((string) ($record['link'] ?? '')),
            'link_image' => trim((string) ($record['link_image'] ?? '')),
            'tags' => $this->normalizeTags((string) ($record['tags'] ?? '')),
            'notes' => trim((string) ($record['notes'] ?? '')),
            'rating' => $rating,
            'flag' => trim((string) ($record['flag'] ?? '')),
            'created_at' => trim((string) ($record['created_at'] ?? '')),
            'updated_at' => trim((string) ($record['updated_at'] ?? '')),
        ];
    }

    private function normalizeForStorage(array $data, array $overrides = []): array
    {
        $item = [
            'item_id' => $overrides['id'] ?? ($data['id'] ?? $data['item_id'] ?? null),
            'item_name' => trim((string) ($data['item_name'] ?? '')),
            'author_name' => trim((string) ($data['author_name'] ?? '')),
            'category' => trim((string) ($data['category'] ?? '')),
            'link' => trim((string) ($data['link'] ?? '')),
            'tags' => $this->normalizeTags((string) ($data['tags'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'rating' => ($data['rating'] ?? '') === '' ? null : (int) round((float) $data['rating']),
            'flag' => trim((string) ($data['flag'] ?? '')),
            'link_image' => trim((string) ($data['link_image'] ?? '')),
            'created_at' => $overrides['created_at'] ?? trim((string) ($data['created_at'] ?? '')),
            'updated_at' => $overrides['updated_at'] ?? trim((string) ($data['updated_at'] ?? '')),
        ];

        return array_filter($item, static fn($value): bool => $value !== '');
    }

    private function resolveRecordId(array $record, int $index): int
    {
        if (isset($record['id']) && $record['id'] !== '') {
            return (int) $record['id'];
        }

        if (isset($record['item_id']) && $record['item_id'] !== '') {
            return (int) $record['item_id'];
        }

        return $index + 1;
    }

    private function saveRecords(array $records): void
    {
        $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON data.');
        }

        file_put_contents($this->dataFile, $json . "\n", LOCK_EX);
    }

    private function normalizeTags(string $tags): string
    {
        return implode('; ', $this->tagsAsArray($tags));
    }

    private function tagsAsArray(string $tags): array
    {
        $result = [];
        foreach (preg_split('/[;,]+/', $tags) as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            $result[$this->lower($tag)] = $tag;
        }

        return array_values($result);
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }
}
