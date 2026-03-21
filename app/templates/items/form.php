<?php
/**
 * Create / Edit item form.
 * Variables: $item (array of field values), $errors (array of field => message)
 */
$isEdit    = isset($item['id']);
$pageTitle = $isEdit ? 'Edit Item' : 'New Item';
ob_start();
?>

<h1><?= $isEdit ? 'Edit Item' : 'New Item' ?></h1>

<form method="post"
      action="<?= $isEdit ? url('items/' . (int) $item['id']) : url('items') ?>"
      class="item-form" novalidate>
    <?= csrfField() ?>

    <div class="form-group <?= isset($errors['item_name']) ? 'has-error' : '' ?>">
        <label for="item_name">Name *</label>
        <input type="text" id="item_name" name="item_name"
               value="<?= htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        <?php if (isset($errors['item_name'])): ?>
            <span class="error"><?= htmlspecialchars($errors['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="author_name">Author</label>
        <input type="text" id="author_name" name="author_name"
               value="<?= htmlspecialchars($item['author_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="form-group">
        <label for="category">Category</label>
        <input type="text" id="category" name="category"
               value="<?= htmlspecialchars($item['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               placeholder="music, book, art, film">
    </div>

    <div class="form-group <?= isset($errors['link']) ? 'has-error' : '' ?>">
        <label for="link">Link</label>
        <input type="url" id="link" name="link"
               value="<?= htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               placeholder="https://…">
        <?php if (isset($errors['link'])): ?>
            <span class="error"><?= htmlspecialchars($errors['link'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group <?= isset($errors['link_image']) ? 'has-error' : '' ?>">
        <label for="link_image">Image Link</label>
        <input type="url" id="link_image" name="link_image"
               value="<?= htmlspecialchars($item['link_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               placeholder="https://…">
        <?php if (isset($errors['link_image'])): ?>
            <span class="error"><?= htmlspecialchars($errors['link_image'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>

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

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Item' ?></button>
        <a href="<?= $isEdit ? url('items/' . (int) $item['id']) : url('items') ?>" class="btn">Cancel</a>
    </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
