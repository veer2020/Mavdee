<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Invalid product ID.');
    header('Location: index.php'); exit;
}

$errors  = [];
$product = null;

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    return trim($text, '-');
}

try {
    $categories = db()->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
    $stmt = db()->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
} catch (Throwable $e) {
    $categories = [];
}

if (!$product) {
    setFlash('danger', 'Product not found.');
    header('Location: index.php'); exit;
}

$pageTitle = 'Edit: ' . $product['name'];
$input     = $product; // Pre-fill from DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input = [
        'name'              => trim($_POST['name'] ?? ''),
        'category_id'       => (int)($_POST['category_id'] ?? 0),
        'price'             => trim($_POST['price'] ?? ''),
        'sale_price'        => trim($_POST['sale_price'] ?? ''),
        'original_price'    => trim($_POST['original_price'] ?? ''),
        'sku'               => trim($_POST['sku'] ?? ''),
        'stock'             => trim($_POST['stock'] ?? '0'),
        'low_stock_alert'   => trim($_POST['low_stock_alert'] ?? '5'),
        'description'       => trim($_POST['description'] ?? ''),
        'short_description' => trim($_POST['short_description'] ?? ''),
        'fabric'            => trim($_POST['fabric'] ?? ''),
        'care'              => trim($_POST['care'] ?? ''),
        'image_url'         => trim($_POST['image_url'] ?? ''),
        'image_url_2'       => trim($_POST['image_url_2'] ?? ''),
        'image_url_3'       => trim($_POST['image_url_3'] ?? ''),
        'image_url_4'       => trim($_POST['image_url_4'] ?? ''),
        'sizes'             => trim($_POST['sizes'] ?? ''),
        'colors'            => trim($_POST['colors'] ?? ''),
        'badge'             => trim($_POST['badge'] ?? ''),
        'badge_type'        => trim($_POST['badge_type'] ?? ''),
        'is_featured'       => isset($_POST['is_featured']) ? 1 : 0,
        'is_new_arrival'    => isset($_POST['is_new_arrival']) ? 1 : 0,
        'is_bestseller'     => isset($_POST['is_bestseller']) ? 1 : 0,
        'is_active'         => isset($_POST['is_active']) ? 1 : 0,
        'occasion'          => trim($_POST['occasion'] ?? ''),
        'weight_grams'      => trim($_POST['weight_grams'] ?? ''),
        'meta_title'        => trim($_POST['meta_title'] ?? ''),
        'meta_description'  => trim($_POST['meta_description'] ?? ''),
    ];

    if ($input['name'] === '')          $errors[] = 'Product name is required.';
    if (!is_numeric($input['price']) || (float)$input['price'] < 0) $errors[] = 'Valid price is required.';
    if ($input['sku'] === '')           $errors[] = 'SKU is required.';
    if (!is_numeric($input['stock']))   $errors[] = 'Stock must be a number.';

    if (empty($errors)) {
        try {
            $slug = slugify($input['name']);
            // Check slug uniqueness (excluding current product)
            $checkSlug = db()->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
            $checkSlug->execute([$slug, $id]);
            if ((int)$checkSlug->fetchColumn() > 0) {
                $slug .= '-' . $id;
            }

            $stmt = db()->prepare(
                "UPDATE products SET
                  name=?, slug=?, category_id=?, price=?, sale_price=?, original_price=?, sku=?, stock=?, low_stock_alert=?,
                  description=?, short_description=?, fabric=?, care=?,
                  image_url=?, image_url_2=?, image_url_3=?, image_url_4=?,
                  sizes=?, colors=?, badge=?, badge_type=?,
                  is_featured=?, is_new_arrival=?, is_bestseller=?, is_active=?,
                  occasion=?, weight_grams=?, meta_title=?, meta_description=?
                 WHERE id=?"
            );

            $stmt->execute([
                $input['name'], $slug, $input['category_id'] ?: null,
                (float)$input['price'],
                $input['sale_price'] !== '' ? (float)$input['sale_price'] : null,
                $input['original_price'] !== '' ? (float)$input['original_price'] : null,
                $input['sku'], (int)$input['stock'], (int)$input['low_stock_alert'],
                $input['description'], $input['short_description'],
                $input['fabric'], $input['care'],
                $input['image_url'], $input['image_url_2'], $input['image_url_3'], $input['image_url_4'],
                $input['sizes'], $input['colors'],
                $input['badge'], $input['badge_type'],
                $input['is_featured'], $input['is_new_arrival'], $input['is_bestseller'], $input['is_active'],
                $input['occasion'],
                $input['weight_grams'] !== '' ? (int)$input['weight_grams'] : null,
                $input['meta_title'], $input['meta_description'],
                $id,
            ]);

            logAdminActivity('edit_product', "Updated product ID $id: {$input['name']}");
            setFlash('success', 'Product "' . h($input['name']) . '" updated successfully.');
            header("Location: edit.php?id=$id");
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-pencil-square me-2" style="color:#f8c146"></i><?= h($pageTitle) ?></h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Products
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger flash-alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1">
    <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Basic Information</h6>
                <div class="mb-3">
                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= h($input['name'] ?? '') ?>" required>
                </div>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)($input['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">SKU <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control" value="<?= h($input['sku'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Occasion</label>
                        <input type="text" name="occasion" class="form-control" value="<?= h($input['occasion'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Descriptions</h6>
                <div class="mb-3">
                    <label class="form-label">Short Description</label>
                    <textarea name="short_description" class="form-control" rows="2"><?= h($input['short_description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Description</label>
                    <textarea name="description" class="form-control" rows="5"><?= h($input['description'] ?? '') ?></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Fabric</label>
                        <input type="text" name="fabric" class="form-control" value="<?= h($input['fabric'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Care Instructions</label>
                        <input type="text" name="care" class="form-control" value="<?= h($input['care'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Product Images</h6>
                <?php
                $imageSlots = [
                    1 => ['field' => 'image_url',   'label' => 'Primary Image', 'required' => true],
                    2 => ['field' => 'image_url_2', 'label' => 'Image 2 (optional)', 'required' => false],
                    3 => ['field' => 'image_url_3', 'label' => 'Image 3 (optional)', 'required' => false],
                    4 => ['field' => 'image_url_4', 'label' => 'Image 4 (optional)', 'required' => false],
                ];
                foreach ($imageSlots as $idx => $slot):
                    $field   = $slot['field'];
                    $val     = h($input[$field] ?? '');
                    $hasImg  = !empty($input[$field]);
                ?>
                <div class="mb-4">
                    <label class="form-label">
                        <?= $slot['label'] ?>
                        <?php if ($slot['required']): ?><span class="text-danger">*</span><?php endif; ?>
                    </label>
                    <input type="hidden" name="<?= $field ?>" id="imageUrl<?= $idx ?>" value="<?= $val ?>">
                    <div id="dropArea<?= $idx ?>" class="img-drop-zone" style="display:<?= $hasImg ? 'none' : 'flex' ?>;">
                        <input type="file" class="file-input" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                        <i class="bi bi-cloud-upload fs-2 mb-2 text-muted"></i>
                        <p class="text-muted mb-1">Drag &amp; drop an image here, or click to browse</p>
                        <p class="text-muted small mb-0">JPG, PNG, WebP, GIF &middot; Max 5 MB</p>
                    </div>
                    <div id="preview<?= $idx ?>" class="img-upload-preview" style="display:<?= $hasImg ? 'block' : 'none' ?>;">
                        <img class="preview-img" src="<?= $val ?>" alt="">
                        <button type="button" class="remove-img btn btn-danger btn-sm">
                            <i class="bi bi-x-lg"></i> Remove
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">SEO</h6>
                <div class="mb-3">
                    <label class="form-label">Meta Title</label>
                    <input type="text" name="meta_title" class="form-control" value="<?= h($input['meta_title'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Meta Description</label>
                    <textarea name="meta_description" class="form-control" rows="2"><?= h($input['meta_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Pricing & Stock</h6>
                <div class="mb-3">
                    <label class="form-label">Regular Price (<?= CURRENCY ?>) <span class="text-danger">*</span></label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= h($input['price'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sale Price (<?= CURRENCY ?>)</label>
                    <input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?= h($input['sale_price'] ?? '') ?>">
                    <div class="form-text">Leave blank if no sale.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">MRP / Original Price (<?= CURRENCY ?>)</label>
                    <input type="number" name="original_price" class="form-control" step="0.01" min="0" value="<?= h($input['original_price'] ?? '') ?>">
                    <div class="form-text">Crossed-out price shown to shoppers. Leave blank if same as Regular Price.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="stock" class="form-control" min="0" value="<?= h($input['stock'] ?? '0') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Low Stock Alert At</label>
                    <input type="number" name="low_stock_alert" class="form-control" min="0" value="<?= h($input['low_stock_alert'] ?? '5') ?>">
                </div>
                <div>
                    <label class="form-label">Weight (grams)</label>
                    <input type="number" name="weight_grams" class="form-control" min="0" value="<?= h($input['weight_grams'] ?? '') ?>">
                </div>
            </div>

            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Variants</h6>
                <div class="mb-3">
                    <label class="form-label">Sizes</label>
                    <input type="text" name="sizes" class="form-control" value="<?= h($input['sizes'] ?? '') ?>" placeholder="XS,S,M,L,XL">
                    <div class="form-text">Comma-separated.</div>
                </div>
                <div>
                    <label class="form-label">Colors</label>
                    <input type="text" name="colors" class="form-control" value="<?= h($input['colors'] ?? '') ?>" placeholder="Red,Blue,Green">
                </div>
            </div>

            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Badge</h6>
                <div class="mb-3">
                    <label class="form-label">Badge Text</label>
                    <input type="text" name="badge" class="form-control" value="<?= h($input['badge'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Badge Type</label>
                    <select name="badge_type" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach (['new','sale','hot','trending','bestseller'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= ($input['badge_type'] ?? '') === $bt ? 'selected' : '' ?>><?= ucfirst($bt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="admin-card mb-3">
                <h6 class="mb-3" style="font-weight:700;">Product Flags</h6>
                <?php foreach ([
                    'is_active'      => 'Active (visible on site)',
                    'is_featured'    => 'Featured',
                    'is_new_arrival' => 'New Arrival',
                    'is_bestseller'  => 'Bestseller',
                ] as $field => $label): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="<?= $field ?>" id="<?= $field ?>"
                        <?= !empty($input[$field]) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= $field ?>"><?= $label ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-accent w-100">
                <i class="bi bi-save me-2"></i>Save Changes
            </button>
        </div>
    </div>
</form>

<script src="../assets/js/image-upload.js"></script>

<?php include __DIR__ . '/../_layout_foot.php'; ?>
