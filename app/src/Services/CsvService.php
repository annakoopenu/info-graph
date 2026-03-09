<?php

declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ItemRepository.php';

class CsvService
{
    private ItemRepository $repo;

    /** Canonical column order for export. */
    private const COLUMNS = ['item_name', 'author_name', 'link', 'tags', 'notes', 'rating', 'flag'];

    public function __construct()
    {
        $this->repo = new ItemRepository();
    }

    // ── Export ────────────────────────────────────────────────────

    /**
     * Write all items (optionally filtered) as CSV to an open stream.
     */
    public function export($stream, array $filters = []): void
    {
        $items = $this->repo->findAll($filters);

        // BOM for Excel UTF-8 compatibility
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::COLUMNS);

        foreach ($items as $item) {
            fputcsv($stream, [
                $item['item_name'],
                $item['author_name'] ?? '',
                $item['link'] ?? '',
                // Tags come as "a, b" from GROUP_CONCAT — convert to "a; b" for CSV
                str_replace(', ', '; ', $item['tags'] ?? ''),
                $item['notes'] ?? '',
                $item['rating'] ?? '',
                $item['flag'] ?? '',
            ]);
        }
    }

    // ── Import / Parse ───────────────────────────────────────────

    /**
     * Parse a CSV file and return structured preview data.
     *
     * Returns ['rows' => [...], 'errors' => [...], 'headers' => [...]]
     * Each row includes an 'action' key: 'create' or 'update'.
     */
    public function parseFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['rows' => [], 'errors' => ['Could not open file.'], 'headers' => []];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['rows' => [], 'errors' => ['File is empty or has no header row.'], 'headers' => []];
        }

        // Strip BOM if present
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        $headers = array_map('trim', array_map('strtolower', $headers));

        // Validate required columns
        if (!in_array('item_name', $headers, true)) {
            fclose($handle);
            return ['rows' => [], 'errors' => ['Missing required column: item_name'], 'headers' => $headers];
        }

        $rows   = [];
        $errors = [];
        $line   = 1; // 1 = header

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $mapped = [];
            foreach ($headers as $i => $col) {
                $mapped[$col] = trim($row[$i] ?? '');
            }

            // Validate
            $rowErrors = [];
            if (empty($mapped['item_name'])) {
                $rowErrors[] = 'item_name is required';
            }
            if (isset($mapped['rating']) && $mapped['rating'] !== '') {
                $r = filter_var($mapped['rating'], FILTER_VALIDATE_INT);
                if ($r === false || $r < 1 || $r > 100) {
                    $rowErrors[] = 'rating must be 1–100';
                }
            }
            if (isset($mapped['link']) && $mapped['link'] !== '' && !filter_var($mapped['link'], FILTER_VALIDATE_URL)) {
                $rowErrors[] = 'invalid URL in link';
            }

            // Determine action
            $hasId = isset($mapped['id']) && $mapped['id'] !== '';
            $mapped['_action'] = $hasId ? 'update' : 'create';
            $mapped['_line']   = $line;
            $mapped['_errors'] = $rowErrors;

            if ($rowErrors) {
                $errors[] = "Row $line: " . implode('; ', $rowErrors);
            }

            $rows[] = $mapped;
        }

        fclose($handle);
        return ['rows' => $rows, 'errors' => $errors, 'headers' => $headers];
    }

    /**
     * Import validated rows into the database.
     * $selectedLines: array of line numbers the user confirmed to import.
     */
    public function importRows(array $rows, array $selectedLines): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!in_array($row['_line'], $selectedLines, false)) {
                $skipped++;
                continue;
            }
            if (!empty($row['_errors'])) {
                $skipped++;
                continue;
            }

            $data = [
                'item_name'   => $row['item_name'] ?? '',
                'author_name' => $row['author_name'] ?? '',
                'link'        => $row['link'] ?? '',
                // CSV uses ; separator for tags
                'tags'        => $row['tags'] ?? '',
                'notes'       => $row['notes'] ?? '',
                'rating'      => $row['rating'] ?? '',
                'flag'        => $row['flag'] ?? '',
            ];

            if ($row['_action'] === 'update' && !empty($row['id'])) {
                $existing = $this->repo->findById((int) $row['id']);
                if ($existing) {
                    $this->repo->update((int) $row['id'], $data);
                    $updated++;
                } else {
                    // ID doesn't exist — create new
                    $this->repo->create($data);
                    $created++;
                }
            } else {
                $this->repo->create($data);
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }
}
