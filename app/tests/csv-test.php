<?php
/**
 * Unit tests for CSV parsing and validation logic.
 *
 * Run with: php tests/csv-test.php
 * (No PHPUnit needed — self-contained assertions.)
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Services/CsvService.php';

$pass = 0;
$fail = 0;

function assert_eq($label, $expected, $actual) {
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  ✓ $label\n";
        $pass++;
    } else {
        echo "  ✗ $label\n    Expected: " . var_export($expected, true) . "\n    Got:      " . var_export($actual, true) . "\n";
        $fail++;
    }
}

function assert_true($label, $condition) {
    assert_eq($label, true, $condition);
}

echo "=== CSV Parse Tests ===\n\n";

$csv = new CsvService();

// ── Test 1: Valid CSV ────────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "item_name,author_name,category,link,tags,notes,rating,flag,link_image\n\"Test Item\",\"Author A\",\"music\",\"https://example.com\",\"music; rock\",\"Some notes\",85,revisit,\"https://example.com/image.jpg\"\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('Valid CSV: 1 row parsed', 1, count($result['rows']));
assert_eq('Valid CSV: no errors', 0, count($result['errors']));
assert_eq('Valid CSV: item_name', 'Test Item', $result['rows'][0]['item_name']);
assert_eq('Valid CSV: tags', 'music; rock', $result['rows'][0]['tags']);
assert_eq('Valid CSV: rating', '85', $result['rows'][0]['rating']);
assert_eq('Valid CSV: image link', 'https://example.com/image.jpg', $result['rows'][0]['link_image']);
assert_eq('Valid CSV: action is create', 'create', $result['rows'][0]['_action']);

// ── Test 2: Row with id → update action ─────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "id,item_name,author_name,category,rating\n42,Updated Item,Author B,book,50\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('Update row: action is update', 'update', $result['rows'][0]['_action']);
assert_eq('Update row: id is 42', '42', $result['rows'][0]['id']);

// ── Test 3: Missing item_name header ─────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "author_name,rating\nBob,50\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('Missing item_name header: 0 rows', 0, count($result['rows']));
assert_true('Missing item_name header: has error', count($result['errors']) > 0);

// ── Test 4: Invalid rating ──────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "item_name,rating\nItem X,999\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('Invalid rating: 1 row parsed', 1, count($result['rows']));
assert_true('Invalid rating: row has errors', count($result['rows'][0]['_errors']) > 0);
assert_true('Invalid rating: error in list', count($result['errors']) > 0);

// ── Test 5: Empty rating is OK ──────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "item_name,rating\nItem Y,\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('Empty rating: no errors', 0, count($result['errors']));

// ── Test 6: Invalid URL ─────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "item_name,link,link_image\nItem Z,not-a-url,still-not-a-url\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_true('Invalid URL: row has errors', count($result['rows'][0]['_errors']) > 0);

// ── Test 7: Empty file ──────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('Empty file: 0 rows', 0, count($result['rows']));
assert_true('Empty file: has error', count($result['errors']) > 0);

// ── Test 8: BOM handling ────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, "\xEF\xBB\xBFitem_name,rating\nBOM Item,75\n");
$result = $csv->parseFile($tmp);
unlink($tmp);

assert_eq('BOM: 1 row parsed', 1, count($result['rows']));
assert_eq('BOM: no errors', 0, count($result['errors']));
assert_eq('BOM: item_name correct', 'BOM Item', $result['rows'][0]['item_name']);

// ── Test 9: Export produces valid CSV ───────────────────────────
$stream = fopen('php://temp', 'r+');
$csv->export($stream, []);
rewind($stream);
$output = stream_get_contents($stream);
fclose($stream);

// Strip BOM
$output = preg_replace('/^\xEF\xBB\xBF/', '', $output);
$lines = explode("\n", trim($output));
$header = str_getcsv($lines[0]);

assert_eq('Export: header starts with id', 'id', $header[0]);
assert_true('Export: has 10 columns', count($header) === 10);

// ── Summary ─────────────────────────────────────────────────────
echo "\n=== Results: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
