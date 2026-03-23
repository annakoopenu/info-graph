<?php
/** @var string $pageTitle */
/** @var string $content — captured output from the page template */
$pageTitle = $pageTitle ?? 'Info-Graph';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Info-Graph</title>
    <link rel="stylesheet" href="<?= url('assets/style.css') ?>">
</head>
<body>
    <?php
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?>
    <header class="site-header">
        <a href="<?= url('items') ?>" class="logo">Info-Graph</a>
        <nav>
            <a href="<?= url('items') ?>">All Items</a>
            <a href="<?= url('items/new') ?>">+ Add Item</a>
            <a href="<?= url('import') ?>">Import / Export</a>
        </nav>
    </header>

    <main class="container">
        <?php if (is_array($flash) && !empty($flash['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars((string) ($flash['type'] ?? 'success'), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
