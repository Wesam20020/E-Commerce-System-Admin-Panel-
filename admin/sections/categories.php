<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('categories');
$pdo = $ctx['pdo'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'category_create') {
            $name = admin_clean_text('name', 150);
            if ($name === '') { throw new RuntimeException('Category name is required.'); }
            $slug = admin_unique_slug($pdo, 'categories', admin_clean_text('slug', 150) ?: $name);
            $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)')->execute(['name' => $name, 'slug' => $slug]);
            flash_set('success', 'Category created.');
        } elseif ($action === 'category_update') {
            $id = admin_int_input('category_id');
            $name = admin_clean_text('name', 150);
            if ($name === '') { throw new RuntimeException('Category name is required.'); }
            $slug = admin_unique_slug($pdo, 'categories', admin_clean_text('slug', 150) ?: $name, $id);
            $pdo->prepare('UPDATE categories SET name = :name, slug = :slug WHERE id = :id LIMIT 1')->execute(['name' => $name, 'slug' => $slug, 'id' => $id]);
            flash_set('success', 'Category updated.');
        } elseif ($action === 'category_delete') {
            $id = admin_int_input('category_id');
            $count = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE category_id = :id', ['id' => $id]);
            if ($count > 0) { throw new RuntimeException('This category still has products. Move them first.'); }
            $pdo->prepare('DELETE FROM categories WHERE id = :id LIMIT 1')->execute(['id' => $id]);
            flash_set('success', 'Category deleted.');
        } else {
            throw new RuntimeException('Unknown category action.');
        }
    } catch (Throwable $e) { admin_flash_from_exception($e); }
    admin_redirect('categories');
}

$categories = admin_rows($pdo, 'SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name, c.slug, c.created_at, c.updated_at ORDER BY c.name ASC');
admin_header('Categories', 'Control storefront taxonomy and keep the catalog easy to browse.', 'categories');
?>
<section class="admin-two-col">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">New category</p><h2>Add category</h2></div></div>
        <form method="post" class="admin-form-stack">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="admin_action" value="category_create">
            <label class="admin-field"><span>Name</span><input name="name" required></label>
            <label class="admin-field"><span>Slug</span><input name="slug" placeholder="Auto-generated if empty"></label>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Create category</button></div>
        </form>
    </article>
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Signals</p><h2>Coverage</h2></div></div>
        <div class="admin-action-list">
            <a><span>Total categories</span><strong><?= count($categories) ?></strong></a>
            <a><span>Empty categories</span><strong><?= count(array_filter($categories, fn($c) => (int)$c['product_count'] === 0)) ?></strong></a>
        </div>
    </article>
</section>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Taxonomy table</p><h2>All categories</h2></div></div>
    <?php if (!$categories): ?><?php admin_empty_state('No categories yet', 'Create the first catalog category.'); ?><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Update</th><th>Delete</th></tr></thead><tbody>
    <?php foreach ($categories as $category): ?>
        <tr>
            <form method="post">
                <td><input class="admin-table-input" name="name" value="<?= e($category['name']) ?>" required></td>
                <td><input class="admin-table-input" name="slug" value="<?= e($category['slug']) ?>"></td>
                <td><?= (int) $category['product_count'] ?></td>
                <td><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="admin_action" value="category_update"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><button class="admin-table-btn" type="submit">Save</button></td>
            </form>
            <td><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="admin_action" value="category_delete"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><button class="admin-table-btn danger" type="submit" <?= (int)$category['product_count'] > 0 ? 'disabled' : '' ?>>Delete</button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
