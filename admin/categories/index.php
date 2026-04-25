<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Categories';

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    return trim($text, '-');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name      = trim($_POST['name'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $imageUrl  = trim($_POST['image_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($name !== '') {
            $slug = slugify($name);
            // Ensure unique slug
            $check = db()->prepare("SELECT COUNT(*) FROM categories WHERE slug=?");
            $check->execute([$slug]);
            if ((int)$check->fetchColumn() > 0) $slug .= '-' . time();

            try {
                db()->prepare(
                    "INSERT INTO categories (name, slug, description, image_url, sort_order, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())"
                )->execute([$name, $slug, $desc, $imageUrl ?: null, $sortOrder, $isActive]);
                logAdminActivity('add_category', "Added: $name");
                setFlash('success', 'Category "' . h($name) . '" added.');
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', 'Category name is required.');
        }
    } elseif ($action === 'edit') {
        $editId    = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $imageUrl  = trim($_POST['image_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($editId > 0 && $name !== '') {
            $slug = slugify($name);
            $check = db()->prepare("SELECT COUNT(*) FROM categories WHERE slug=? AND id!=?");
            $check->execute([$slug, $editId]);
            if ((int)$check->fetchColumn() > 0) $slug .= '-' . $editId;

            try {
                db()->prepare(
                    "UPDATE categories SET name=?, slug=?, description=?, image_url=?, sort_order=?, is_active=? WHERE id=?"
                )->execute([$name, $slug, $desc, $imageUrl ?: null, $sortOrder, $isActive, $editId]);
                logAdminActivity('edit_category', "Updated ID $editId: $name");
                setFlash('success', 'Category "' . h($name) . '" updated.');
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            try {
                $s = db()->prepare("SELECT name FROM categories WHERE id=?");
                $s->execute([$deleteId]);
                $row = $s->fetch();
                db()->prepare("DELETE FROM categories WHERE id=?")->execute([$deleteId]);
                logAdminActivity('delete_category', 'Deleted: ' . ($row['name'] ?? 'ID ' . $deleteId));
                setFlash('success', 'Category deleted.');
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
    }

    header('Location: index.php');
    exit;
}

// Load categories
try {
    $categories = db()->query(
        "SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count
         FROM categories c ORDER BY c.sort_order ASC, c.name ASC"
    )->fetchAll();
} catch (Throwable) {
    $categories = [];
}

// Load category for editing
$editCat = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $editId) {
            $editCat = $cat;
            break;
        }
    }
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-grid-3x3-gap me-2" style="color:#f8c146"></i>Categories</h1>
</div>

<div class="row g-3">
    <!-- Add / Edit form -->
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="mb-3" style="font-weight:700;">
                <?= $editCat ? '<i class="bi bi-pencil me-2"></i>Edit Category' : '<i class="bi bi-plus-circle me-2"></i>Add Category' ?>
            </h6>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editCat ? 'edit' : 'add' ?>">
                <?php if ($editCat): ?>
                    <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= h($editCat['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control form-control-sm" rows="2"><?= h($editCat['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category Image</label>
                    <input type="hidden" name="image_url" id="catImageUrl" value="<?= h($editCat['image_url'] ?? '') ?>">
                    <div id="catDropArea" class="img-drop-zone" style="display:<?= !empty($editCat['image_url']) ? 'none' : 'flex' ?>;">
                        <input type="file" class="file-input" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                        <i class="bi bi-cloud-upload fs-2 mb-2 text-muted"></i>
                        <p class="text-muted mb-1">Drag &amp; drop an image here, or click to browse</p>
                        <p class="text-muted small mb-0">JPG, PNG, WebP, GIF &middot; Max 5 MB</p>
                    </div>
                    <div id="catImgPreview" class="img-upload-preview" style="display:<?= !empty($editCat['image_url']) ? 'block' : 'none' ?>;">
                        <img class="preview-img" src="<?= h($editCat['image_url'] ?? '') ?>" alt="">
                        <button type="button" class="remove-img btn btn-danger btn-sm">
                            <i class="bi bi-x-lg"></i> Remove
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= h((string)($editCat['sort_order'] ?? 0)) ?>" min="0">
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="cat_active"
                            <?= !$editCat || $editCat['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cat_active">Active</label>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-accent btn-sm flex-fill">
                        <i class="bi bi-<?= $editCat ? 'save' : 'plus-circle' ?> me-1"></i>
                        <?= $editCat ? 'Save Changes' : 'Add Category' ?>
                    </button>
                    <?php if ($editCat): ?>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories table -->
    <div class="col-lg-8">
        <div class="admin-card" style="padding:0; overflow:hidden;">
            <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6; font-weight:700;">
                <?= count($categories) ?> <?= count($categories) === 1 ? 'Category' : 'Categories' ?>
            </div>
            <?php if (empty($categories)): ?>
                <div class="text-center py-5 text-muted">No categories yet. Add one!</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Products</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr <?= $editCat && (int)$editCat['id'] === (int)$cat['id'] ? 'style="background:#fffbeb;"' : '' ?>>
                                    <td>
                                        <div style="font-weight:600;"><?= h($cat['name']) ?></div>
                                        <?php if ($cat['image_url']): ?>
                                            <img src="<?= h($cat['image_url']) ?>" style="width:40px;height:30px;object-fit:cover;border-radius:4px;margin-top:4px;" alt="">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted" style="font-size:.8rem;"><?= h($cat['slug']) ?></td>
                                    <td><?= (int)$cat['product_count'] ?></td>
                                    <td><?= (int)$cat['sort_order'] ?></td>
                                    <td>
                                        <span class="badge-status <?= $cat['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= (int)$cat['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:.72rem; padding:2px 8px;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-1" style="font-size:.72rem; padding:2px 8px;"
                                            onclick="deleteCat(<?= (int)$cat['id'] ?>, '<?= h(addslashes($cat['name'])) ?>')">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete modal -->
<div class="modal fade" id="deleteCatModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-danger d-block mb-2" style="font-size:2rem;"></i>
                <h6 class="mb-1">Delete Category?</h6>
                <p class="text-muted mb-1" id="deleteCatName" style="font-size:.875rem;"></p>
                <p class="text-warning mb-3" style="font-size:.8rem;">Products in this category will not be deleted but will become uncategorised.</p>
                <form method="POST" id="deleteCatForm">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteCatId">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    function deleteCat(id, name) {
        document.getElementById('deleteCatId').value = id;
        document.getElementById('deleteCatName').textContent = name;
        new bootstrap.Modal(document.getElementById('deleteCatModal')).show();
    }

    // Category image drag-drop uploader
    (function() {
        var zone = document.getElementById('catDropArea');
        var preview = document.getElementById('catImgPreview');
        if (!zone || !preview) return;

        var fileInput = zone.querySelector('.file-input');
        var previewImg = preview.querySelector('.preview-img');
        var hiddenInput = document.getElementById('catImageUrl');
        var removeBtn = preview.querySelector('.remove-img');

        zone.addEventListener('click', function(e) {
            if (e.target !== fileInput) fileInput.click();
        });
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            var file = e.dataTransfer.files[0];
            if (file) uploadCatImage(file);
        });
        fileInput.addEventListener('change', function() {
            var file = fileInput.files[0];
            if (file) uploadCatImage(file);
        });
        removeBtn.addEventListener('click', function() {
            hiddenInput.value = '';
            previewImg.src = '';
            preview.style.display = 'none';
            zone.style.display = 'flex';
            fileInput.value = '';
        });

        async function uploadCatImage(file) {
            var originalHtml = zone.innerHTML;
            zone.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Uploading…</span></div>' +
                '<p class="mt-2 text-muted small">Uploading…</p>';

            var csrfInput = document.querySelector('input[name="csrf_token"]');
            var formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', csrfInput ? csrfInput.value : '');

            try {
                var response = await fetch('upload_image.php', {
                    method: 'POST',
                    body: formData
                });
                var data = await response.json();
                if (data.success) {
                    hiddenInput.value = data.url;
                    previewImg.src = data.url;
                    preview.style.display = 'block';
                    zone.style.display = 'none';
                    zone.innerHTML = originalHtml;
                } else {
                    zone.innerHTML = originalHtml;
                    var errP = document.createElement('p');
                    errP.className = 'text-danger small mt-2';
                    errP.textContent = data.error || 'Upload failed.';
                    zone.appendChild(errP);
                }
            } catch (err) {
                zone.innerHTML = originalHtml;
                var errP = document.createElement('p');
                errP.className = 'text-danger small mt-2';
                errP.textContent = 'Upload failed. Please try again.';
                zone.appendChild(errP);
            }
        }
    })();
</script>

<?php include __DIR__ . '/../_layout_foot.php'; ?>