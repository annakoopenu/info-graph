<?php
/**
 * CSV import preview — shows parsed rows, lets user select which to import.
 * Variables: $rows (array), $parseErrors (array)
 */
$pageTitle = 'Import Preview';
ob_start();
?>

<h1>Import Preview</h1>

<?php if (!empty($parseErrors)): ?>
    <div class="alert alert-error">
        <strong>Issues found:</strong>
        <ul>
            <?php foreach ($parseErrors as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('import/confirm') ?>">
    <?= csrfField() ?>

    <div class="preview-actions">
        <button type="submit" class="btn btn-primary">Import Selected Rows</button>
        <a href="<?= url('import') ?>" class="btn">Cancel</a>
        <label class="select-all-label">
            <input type="checkbox" id="select-all" checked> Select/deselect all
        </label>
    </div>

    <div class="table-wrap">
        <table class="items-table preview-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Row</th>
                    <th>Action</th>
                    <th>Name</th>
                    <th>Author</th>
                    <th>Tags</th>
                    <th>Rating</th>
                    <th>Flag</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $hasErr = !empty($row['_errors']); ?>
                    <tr class="<?= $hasErr ? 'row-error' : '' ?>">
                        <td>
                            <?php if (!$hasErr): ?>
                                <input type="checkbox" name="selected[]"
                                       value="<?= (int) $row['_line'] ?>" checked>
                            <?php else: ?>
                                <span title="Has errors — cannot import">✕</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['_line'] ?></td>
                        <td>
                            <span class="badge badge-<?= $row['_action'] ?>">
                                <?= $row['_action'] === 'update' ? 'Update #' . (int)($row['id'] ?? 0) : 'Create' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['tags'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['rating'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['flag'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($hasErr): ?>
                                <span class="text-danger"><?= htmlspecialchars(implode('; ', $row['_errors']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="text-success">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('.preview-table input[type="checkbox"][name="selected[]"]')
        .forEach(cb => cb.checked = this.checked);
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
