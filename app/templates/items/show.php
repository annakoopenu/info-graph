<?php
/**
 * Item detail view.
 * Variable: $item
 */
$pageTitle = $item['item_name'];
ob_start();
?>

<article class="item-detail">
    <div class="item-header">
        <div>
            <h1><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if (!empty($item['category'])): ?>
                <p class="item-subtitle"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <div class="item-actions">
            <a href="<?= url('items/' . (int) $item['id'] . '/edit') ?>" class="btn">Edit</a>
            <form method="post" action="<?= url('items/' . (int) $item['id'] . '/replace-image') ?>" class="inline-form">
                <?= csrfField() ?>
                <button type="submit" class="btn">Replace pic</button>
            </form>
            <form method="post" action="<?= url('items/' . (int) $item['id'] . '/delete') ?>" class="inline-form"
                  onsubmit="return confirm('Delete this item?')">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>

    <?php if (!empty($item['link_image'])): ?>
        <div class="item-image-wrap">
            <img src="<?= htmlspecialchars($item['link_image'], ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>"
                 class="item-image">
        </div>
    <?php endif; ?>

    <dl class="detail-grid">
        <dt>Author</dt>
        <dd><?= htmlspecialchars($item['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></dd>

        <dt>Category</dt>
        <dd><?= htmlspecialchars($item['category'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></dd>

        <dt>Link</dt>
        <dd>
            <?php if ($item['link']): ?>
                <a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener">
                    <?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>

        <dt>Image Link</dt>
        <dd>
            <?php if ($item['link_image']): ?>
                <a href="<?= htmlspecialchars($item['link_image'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener">
                    <?= htmlspecialchars($item['link_image'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>

        <dt>Tags</dt>
        <dd>
            <?php if ($item['tags']): ?>
                <?php foreach (explode(', ', $item['tags']) as $tag): ?>
                    <span class="tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>

        <dt>Rating</dt>
        <dd><?= $item['rating'] !== null ? (int) $item['rating'] . ' / 100' : '—' ?></dd>

        <dt>Flag</dt>
        <dd>
            <?php if ($item['flag']): ?>
                <span class="flag flag-<?= htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($item['flag'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>

        <dt>Notes</dt>
        <dd class="notes"><?= nl2br(htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8')) ?: '—' ?></dd>

        <dt>Created</dt>
        <dd><?= htmlspecialchars($item['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></dd>

        <dt>Updated</dt>
        <dd><?= htmlspecialchars($item['updated_at'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></dd>
    </dl>
</article>

<a href="<?= url('items') ?>" class="btn">&larr; Back to list</a>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
