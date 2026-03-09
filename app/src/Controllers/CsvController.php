<?php

declare(strict_types=1);

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../Services/CsvService.php';

class CsvController
{
    private CsvService $csv;

    public function __construct()
    {
        $this->csv = new CsvService();
    }

    // ── Export ────────────────────────────────────────────────────

    public function export(array $params): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="info-graph-export.csv"');

        $stream = fopen('php://output', 'w');
        $this->csv->export($stream);
        fclose($stream);
        exit;
    }

    // ── Import: show upload form ─────────────────────────────────

    public function importForm(array $params): void
    {
        $errors  = [];
        $success = $_GET['imported'] ?? null;
        require __DIR__ . '/../../templates/csv/upload.php';
    }

    // ── Import: upload → preview ─────────────────────────────────

    public function importUpload(array $params): void
    {
        verifyCsrf();

        $errors = [];

        // Validate upload
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a CSV file to upload.';
            require __DIR__ . '/../../templates/csv/upload.php';
            return;
        }

        $file = $_FILES['csv_file'];

        // Size limit: 2 MB
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'File too large (max 2 MB).';
            require __DIR__ . '/../../templates/csv/upload.php';
            return;
        }

        // MIME check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Invalid file type. Please upload a .csv file.';
            require __DIR__ . '/../../templates/csv/upload.php';
            return;
        }

        // Parse
        $result = $this->csv->parseFile($file['tmp_name']);

        if (empty($result['rows'])) {
            $errors[] = 'No data rows found in the file.';
            if ($result['errors']) {
                $errors = array_merge($errors, $result['errors']);
            }
            require __DIR__ . '/../../templates/csv/upload.php';
            return;
        }

        // Store parsed data in session for the confirm step
        $_SESSION['csv_import'] = $result;

        $rows       = $result['rows'];
        $parseErrors = $result['errors'];
        require __DIR__ . '/../../templates/csv/preview.php';
    }

    // ── Import: confirm → insert ─────────────────────────────────

    public function importConfirm(array $params): void
    {
        verifyCsrf();

        if (empty($_SESSION['csv_import'])) {
            header('Location: ' . url('import'));
            exit;
        }

        $result = $_SESSION['csv_import'];
        $selectedLines = array_map('intval', $_POST['selected'] ?? []);

        if (empty($selectedLines)) {
            $rows       = $result['rows'];
            $parseErrors = array_merge($result['errors'], ['No rows selected for import.']);
            require __DIR__ . '/../../templates/csv/preview.php';
            return;
        }

        $stats = $this->csv->importRows($result['rows'], $selectedLines);
        unset($_SESSION['csv_import']);

        header('Location: ' . url('import') . '?imported=' . $stats['created'] . '&updated=' . $stats['updated'] . '&skipped=' . $stats['skipped']);
        exit;
    }
}
