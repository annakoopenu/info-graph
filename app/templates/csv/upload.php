<?php
/**
 * CSV upload form.
 * Variables: $errors (array), $success (string|null)
 */
$pageTitle = 'Import CSV';
ob_start();
?>

<h1>Import CSV</h1>

<?php if (!empty($_GET['imported'])): ?>
    <div class="alert alert-success">
        Import complete: <?= (int) $_GET['imported'] ?> created,
        <?= (int) ($_GET['updated'] ?? 0) ?> updated,
        <?= (int) ($_GET['skipped'] ?? 0) ?> skipped.
        <a href="<?= url('items') ?>">View items &rarr;</a>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= url('import') ?>" enctype="multipart/form-data" class="upload-form">
        <?= csrfField() ?>

        <div class="form-group">
            <label for="csv_file">Select a CSV file</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv">
            <small class="help-text">
                Max 2 MB. Required columns: <code>item_name</code>.
                Optional: <code>author_name</code>, <code>link</code>, <code>tags</code> (separated by ;),
                <code>notes</code>, <code>rating</code> (1–100), <code>flag</code>, <code>id</code> (for updates).
            </small>
        </div>

        <button type="submit" class="btn btn-primary">Upload &amp; Preview</button>
    </form>

    <hr>
    <p>
        <a href="<?= url('export') ?>" class="btn">Download Export CSV</a>
        <small class="help-text">Export all current items as CSV.</small>
    </p>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
