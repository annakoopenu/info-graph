<?php
/**
 * Create / Edit item form.
 * Variables: $item (array of field values), $errors (array of field => message), $collection, $formCategories
 */
$isEdit    = isset($item['id']);
$isPeople  = $collection === 'people';
$isGroups  = $collection === 'groups';
$isEntity  = in_array($collection, ['people', 'groups'], true);
$entityLabel = $isPeople ? 'Person' : ($isGroups ? 'Group' : 'Item');
$pageTitle = $isEdit ? 'Edit ' . $entityLabel : 'New ' . $entityLabel;
ob_start();
?>

<h1><?= $isEdit ? 'Edit ' . $entityLabel : 'New ' . $entityLabel ?></h1>

<form method="post"
      action="<?= $isEdit ? url('items/' . (int) $item['id']) : url('items') ?>"
      class="item-form" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="collection" value="<?= htmlspecialchars($collection, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group <?= isset($errors['item_name']) ? 'has-error' : '' ?>">
        <label for="item_name">Name *</label>
        <input type="text" id="item_name" name="item_name"
               value="<?= htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        <?php if (isset($errors['item_name'])): ?>
            <span class="error"><?= htmlspecialchars($errors['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>

    <?php if (!$isEntity): ?>
        <div class="form-group">
            <label for="author_name">Author</label>
            <input type="text" id="author_name" name="author_name"
                   value="<?= htmlspecialchars($item['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="category">Category</label>
        <?php if ($isPeople): ?>
            <select id="category" name="category">
                <option value="">— Select category —</option>
                <?php foreach ($formCategories as $category): ?>
                    <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"
                        <?= ($item['category'] ?? '') === $category ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php elseif (!$isGroups): ?>
            <input type="text" id="category" name="category"
                   value="<?= htmlspecialchars($item['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="music, book, art, film">
        <?php else: ?>
            <input type="text" id="category" name="category"
                   value=""
                   placeholder="No category for groups"
                   disabled>
        <?php endif; ?>
    </div>

    <?php if (!$isEntity): ?>
        <div class="form-group <?= isset($errors['link']) ? 'has-error' : '' ?>">
            <label for="link">Link</label>
            <input type="url" id="link" name="link"
                   value="<?= htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="https://…">
            <?php if (isset($errors['link'])): ?>
                <span class="error"><?= htmlspecialchars($errors['link'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="form-group <?= isset($errors['link_image']) ? 'has-error' : '' ?>">
        <label for="link_image">Image Link</label>
        <div class="image-link-row">
            <input type="url" id="link_image" name="link_image"
                   value="<?= htmlspecialchars($item['link_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="https://…">
            <?php if ($isEdit): ?>
                <button type="submit"
                        class="btn"
                        formaction="<?= $collection === 'items' ? url('items/' . (int) $item['id'] . '/replace-image') : url('items/' . (int) $item['id'] . '/replace-image') . '?collection=' . urlencode($collection) ?>"
                        formmethod="post">
                    Replace pic
                </button>
            <?php endif; ?>
        </div>
        <?php if (isset($errors['link_image'])): ?>
            <span class="error"><?= htmlspecialchars($errors['link_image'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>

    <?php if (!$isEntity): ?>
        <div class="form-group">
            <label for="tags">Tags <small>(comma or semicolon separated)</small></label>
            <input type="text" id="tags" name="tags"
                   value="<?= htmlspecialchars($item['tags'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="music, painting, book">
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="4"><?= htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group <?= isset($errors['rating']) ? 'has-error' : '' ?>">
            <label for="rating">Rating <small>(1–100)</small></label>
            <input type="number" id="rating" name="rating" min="1" max="100"
                   value="<?= htmlspecialchars((string)($item['rating'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php if (isset($errors['rating'])): ?>
                <span class="error"><?= htmlspecialchars($errors['rating'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="flag">Flag</label>
            <select id="flag" name="flag">
                <option value="">— None —</option>
                <option value="revisit"   <?= ($item['flag'] ?? '') === 'revisit'   ? 'selected' : '' ?>>Revisit</option>
                <option value="completed" <?= ($item['flag'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create ' . $entityLabel ?></button>
        <a href="<?= $isEdit ? ($collection === 'items' ? url('items/' . (int) $item['id']) : url('items/' . (int) $item['id']) . '?collection=' . urlencode($collection)) : ($collection === 'items' ? url('items') : url('items') . '?collection=' . urlencode($collection)) ?>" class="btn">Cancel</a>
    </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
