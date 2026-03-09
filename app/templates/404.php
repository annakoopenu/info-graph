<?php
$pageTitle = 'Not Found';
ob_start();
?>

<h1>404 — Not Found</h1>
<p>The page you requested doesn't exist.</p>
<a href="<?= url('items') ?>" class="btn">&larr; Back to items</a>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
