<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Products';

// Filters & pagination
$search   = trim($_GET['search'] ?? '');
$catId    = (int)($_GET['category'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

try {
    // Categories for filter
    $categories = db()->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

    // Build query
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "(p.name LIKE ? OR p.sku LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($catId > 0) {
        $where[]  = "p.category_id = ?";
        $params[] = $catId;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = db()->prepare("SELECT COUNT(*) FROM products p $whereSQL");
    $countStmt->execute($params);
    $total     = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = db()->prepare(
        "SELECT p.id, p.name, p.sku, p.price, p.sale_price, p.stock, p.is_active, p.image_url, p.badge,
                c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         $whereSQL
         ORDER BY p.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $products = [];
    $categories = [];
    $total = 0;
    $totalPages = 1;
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-bag-heart me-2" style="color:#f8c146"></i>Products</h1>
    <a href="add.php" class="btn btn-accent btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Product
    </a>
</div>

<!-- Search & filter bar -->
<div class="admin-card mb-3" style="padding: 16px 20px;">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-5">
            <label class="form-label mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or SKU…" value="<?= h($search) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label mb-1">Category</label>
            <select name="category" class="form-select form-select-sm">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $catId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= h($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-auto">
            <button type="submit" class="btn btn-accent btn-sm">Filter</button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
        </div>
    </form>
</div>

<div class="admin-card" style="padding:0; overflow:hidden;">
    <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid #f3f4f6;">
        <span class="text-muted" style="font-size:.85rem;"><?= number_format($total) ?> product<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bag-x d-block mb-2" style="font-size:2rem;"></i>
            No products found.
            <?php if ($search || $catId): ?><a href="index.php">Clear filters</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['image_url']): ?>
                                    <img src="<?= h($p['image_url']) ?>" class="img-preview" alt="<?= h($p['name']) ?>">
                                <?php else: ?>
                                    <div class="img-preview d-flex align-items-center justify-content-center bg-light text-muted" style="width:60px;height:50px;border-radius:6px;font-size:.7rem;">No img</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= h($p['name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;">SKU: <?= h($p['sku'] ?? '—') ?></div>
                                <?php if ($p['badge']): ?>
                                    <span class="badge bg-warning text-dark" style="font-size:.65rem;"><?= h($p['badge']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($p['category_name'] ?? '—') ?></td>
                            <td>
                                <?php if ($p['sale_price'] && (float)$p['sale_price'] > 0): ?>
                                    <div><?= CURRENCY ?><?= number_format((float)$p['sale_price'], 0) ?></div>
                                    <del class="text-muted" style="font-size:.75rem;"><?= CURRENCY ?><?= number_format((float)$p['price'], 0) ?></del>
                                <?php else: ?>
                                    <?= CURRENCY ?><?= number_format((float)$p['price'], 0) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= (int)$p['stock'] === 0 ? 'bg-danger' : ((int)$p['stock'] <= 5 ? 'bg-warning text-dark' : 'bg-success') ?>">
                                    <?= (int)$p['stock'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?= $p['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:.75rem; padding:3px 9px;">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                    class="btn btn-sm btn-outline-danger ms-1"
                                    style="font-size:.75rem; padding:3px 9px;"
                                    onclick="confirmDelete(<?= (int)$p['id'] ?>, '<?= h(addslashes($p['name'])) ?>')">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center py-3">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $catId ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-danger d-block mb-2" style="font-size:2rem;"></i>
                <h6 class="mb-1">Delete Product?</h6>
                <p class="text-muted mb-3" id="deleteProductName" style="font-size:.875rem;"></p>
                <form method="POST" action="delete.php" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" id="deleteProductId">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    function confirmDelete(id, name) {
        document.getElementById('deleteProductId').value = id;
        document.getElementById('deleteProductName').textContent = name;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

<?php include __DIR__ . '/../_layout_foot.php'; ?>