<?php
/**
 * Items list view — supports list / tiles / cloud modes.
 * Variables: $items, $filters, $allTags, $allCategories, $collection
 */
$pageTitle = ucfirst($collection);
$view = $_GET['view'] ?? 'list';
if (!in_array($view, ['list', 'tiles', 'cloud'], true)) {
    $view = 'list';
}

/** Build a URL that preserves current filters + sets a view mode. */
function viewUrl(string $mode): string {
    $params = $_GET;
    $params['view'] = $mode;
    return url('items') . '?' . http_build_query($params);
}

function collectionUrl(string $targetCollection): string {
    $params = $_GET;
    $params['collection'] = $targetCollection;
    unset($params['search'], $params['tag'], $params['category']);
    return url('items') . '?' . http_build_query($params);
}

function detailUrl(int $id, string $collection, string $suffix = ''): string {
    $path = 'items/' . $id . $suffix;
    if ($collection === 'items') {
        return url($path);
    }

    return url($path) . '?' . http_build_query(['collection' => $collection]);
}

function rowLinkUrl(int $id, string $collection): string {
    if (in_array($collection, ['people', 'groups'], true)) {
        return detailUrl($id, $collection, '/edit');
    }

    return detailUrl($id, $collection);
}

ob_start();
?>

<nav class="collection-menu" aria-label="Collection switcher">
    <a href="<?= collectionUrl('items') ?>" class="collection-btn <?= $collection === 'items' ? 'collection-btn-active' : '' ?>">Items</a>
    <a href="<?= collectionUrl('people') ?>" class="collection-btn <?= $collection === 'people' ? 'collection-btn-active' : '' ?>">People</a>
    <a href="<?= collectionUrl('groups') ?>" class="collection-btn <?= $collection === 'groups' ? 'collection-btn-active' : '' ?>">Groups</a>
</nav>

<section class="filters">
    <form method="get" action="<?= url('items') ?>" class="filter-form" id="filter-form">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="collection" value="<?= htmlspecialchars($collection, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text"
               name="search"
               placeholder="Search name, author, notes…"
               value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <select name="tag">
            <option value="">All tags</option>
            <?php foreach ($allTags as $t): ?>
                <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"
                    <?= ($filters['tag'] ?? '') === $t ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="category">
            <option value="">All categories</option>
            <?php foreach ($allCategories as $category): ?>
                <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"
                    <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>>
                    <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (($filters['search'] ?? '') !== '' || ($filters['tag'] ?? '') !== '' || ($filters['category'] ?? '') !== ''): ?>
            <a href="<?= url('items') ?>?<?= http_build_query(['view' => $view, 'collection' => $collection]) ?>" class="btn-clear">Clear</a>
        <?php endif; ?>
    </form>
</section>

<script>
(function() {
    var form = document.getElementById('filter-form');
    var searchInput = form.querySelector('input[name="search"]');
    var selects = form.querySelectorAll('select');
    var timer;

    // Dropdowns submit immediately
    for (var i = 0; i < selects.length; i++) {
        selects[i].addEventListener('change', function() { form.submit(); });
    }

    // Search input submits after 400ms debounce
    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() { form.submit(); }, 400);
    });
})();
</script>

<!-- View switcher -->
<div class="view-switcher">
    <a href="<?= viewUrl('list') ?>"  class="vs-btn <?= $view === 'list'  ? 'vs-active' : '' ?>" title="List view">☰ List</a>
    <a href="<?= viewUrl('tiles') ?>" class="vs-btn <?= $view === 'tiles' ? 'vs-active' : '' ?>" title="Tiles view">▦ Tiles</a>
    <a href="<?= viewUrl('cloud') ?>" class="vs-btn <?= $view === 'cloud' ? 'vs-active' : '' ?>" title="Cloud view">◌ Cloud</a>
    <span class="vs-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
</div>

<?php if (empty($items)): ?>
    <p class="empty-state">
        No items found.
        <?php if ($collection === 'items'): ?>
            <a href="<?= url('items/new') ?>">Add one?</a>
        <?php endif; ?>
    </p>

<?php elseif ($view === 'list'): ?>
    <!-- ── LIST VIEW ──────────────────────────────────────────── -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Author</th>
                <th>Category</th>
                <th>Tags</th>
                <th>Rating</th>
                <th>Flag</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="item-cell">
                            <?php if (!empty($item['link_image'])): ?>
                                <img src="<?= htmlspecialchars($item['link_image'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt=""
                                     class="item-thumb"
                                     loading="lazy">
                            <?php endif; ?>
                            <?php if (in_array($collection, ['items', 'people', 'groups'], true)): ?>
                                <a href="<?= rowLinkUrl((int) $item['id'], $collection) ?>">
                                    <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <span><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($item['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item['category'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                    <td>
                        <?php if ($item['tags']): ?>
                            <?php foreach (preg_split('/[;,]\s*/', $item['tags']) as $tag): ?>
                                <span class="tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['rating'] !== null ? (int) $item['rating'] : '—' ?></td>
                    <td>
                        <?php if ($item['flag']): ?>
                            <span class="flag flag-<?= htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($view === 'tiles'): ?>
    <!-- ── TILES VIEW ─────────────────────────────────────────── -->
    <div class="tiles-grid">
        <?php foreach ($items as $item): ?>
            <?php $tileTag = in_array($collection, ['items', 'people', 'groups'], true) ? 'a' : 'div'; ?>
            <?= '<' . $tileTag ?>
                <?= in_array($collection, ['items', 'people', 'groups'], true) ? 'href="' . htmlspecialchars(rowLinkUrl((int) $item['id'], $collection), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                class="tile <?= $item['flag'] ? 'tile-' . htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') : '' ?>">
                <?php if (!empty($item['link_image'])): ?>
                    <img src="<?= htmlspecialchars($item['link_image'], ENT_QUOTES, 'UTF-8') ?>"
                         alt=""
                         class="tile-image"
                         loading="lazy">
                <?php endif; ?>
                <div class="tile-name"><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="tile-author"><?= htmlspecialchars($item['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (!empty($item['category'])): ?>
                    <div class="tile-category"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($item['rating'] !== null): ?>
                    <div class="tile-rating">
                        <span class="rating-bar" style="width: <?= (int) $item['rating'] ?>%"></span>
                        <span class="rating-num"><?= (int) $item['rating'] ?></span>
                    </div>
                <?php endif; ?>
                <div class="tile-tags">
                    <?php if ($item['tags']): ?>
                        <?php foreach (preg_split('/[;,]\s*/', $item['tags']) as $tag): ?>
                            <span class="tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($item['flag']): ?>
                    <span class="flag flag-<?= htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') ?> tile-flag">
                        <?= htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </<?= $tileTag ?>>
        <?php endforeach; ?>
    </div>

<?php elseif ($view === 'cloud'): ?>
    <!-- ── CLOUD VIEW ─────────────────────────────────────────── -->
    <div class="cloud-container" id="cloud-container">
        <?php foreach ($items as $item): ?>
            <?php $cloudTag = in_array($collection, ['items', 'people', 'groups'], true) ? 'a' : 'div'; ?>
            <?= '<' . $cloudTag ?>
               <?= in_array($collection, ['items', 'people', 'groups'], true) ? 'href="' . htmlspecialchars(rowLinkUrl((int) $item['id'], $collection), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
               class="cloud-dot <?= $item['flag'] ? 'dot-' . htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') : 'dot-none' ?>"
               data-id="<?= (int) $item['id'] ?>"
               data-rating="<?= $item['rating'] !== null ? (int) $item['rating'] : 50 ?>"
               title="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($item['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <span class="dot-label"><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </<?= $cloudTag ?>>
        <?php endforeach; ?>
    </div>
    <script src="<?= url('assets/cloud.js') ?>"></script>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
