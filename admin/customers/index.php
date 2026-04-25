<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Customers';

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    csrf_check();
    $cid    = (int)($_POST['customer_id'] ?? 0);
    $newVal = (int)($_POST['new_val'] ?? 0);
    if ($cid > 0) {
        try {
            db()->prepare("UPDATE customers SET is_active=? WHERE id=?")->execute([$newVal, $cid]);
            logAdminActivity('toggle_customer', "Customer ID $cid active=$newVal");
        } catch (Throwable) {
        }
    }
    header('Location: index.php' . ($search ? '?search=' . urlencode($search) : ''));
    exit;
}

try {
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = db()->prepare("SELECT COUNT(*) FROM customers $whereSQL");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    try {
        $stmt = db()->prepare(
            "SELECT id, name, email, phone, is_active,
                    COALESCE(total_orders,0) AS total_orders,
                    COALESCE(total_spent,0) AS total_spent, created_at,
                    (SELECT city FROM customer_addresses WHERE customer_id = customers.id ORDER BY is_default DESC, id DESC LIMIT 1) AS city,
                    (SELECT state FROM customer_addresses WHERE customer_id = customers.id ORDER BY is_default DESC, id DESC LIMIT 1) AS state
             FROM customers $whereSQL
             ORDER BY created_at DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
    } catch (Throwable $e1) {
        error_log('Customers query (with aggregates) failed: ' . $e1->getMessage());
        // Fallback: query without aggregate columns
        try {
            $stmt2 = db()->prepare(
                "SELECT id, name, email, phone, is_active,
                        0 AS total_orders, 0 AS total_spent, created_at,
                        (SELECT city FROM customer_addresses WHERE customer_id = customers.id ORDER BY is_default DESC, id DESC LIMIT 1) AS city,
                        (SELECT state FROM customer_addresses WHERE customer_id = customers.id ORDER BY is_default DESC, id DESC LIMIT 1) AS state
                 FROM customers $whereSQL
                 ORDER BY created_at DESC
                 LIMIT $perPage OFFSET $offset"
            );
            $stmt2->execute($params);
            $customers = $stmt2->fetchAll();
        } catch (Throwable $e2) {
            error_log('Customers fallback query also failed: ' . $e2->getMessage());
            $customers = [];
        }
    }
} catch (Throwable $e) {
    $customers = [];
    $total = 0;
    $totalPages = 1;
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-people me-2" style="color:#f8c146"></i>Customers</h1>
</div>

<div class="admin-card mb-3" style="padding:16px 20px;">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-5">
            <label class="form-label mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, email, phone…" value="<?= h($search) ?>">
        </div>
        <div class="col-sm-auto">
            <button type="submit" class="btn btn-accent btn-sm">Search</button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
        </div>
    </form>
</div>

<div class="admin-card" style="padding:0; overflow:hidden;">
    <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6;">
        <span class="text-muted" style="font-size:.85rem;"><?= number_format($total) ?> customer<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($customers)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-people d-block mb-2" style="font-size:2rem;"></i>
            No customers found.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Orders</th>
                        <th>Spent</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td style="font-weight:600;"><?= h($c['name']) ?></td>
                            <td><?= h($c['email']) ?></td>
                            <td><?= h($c['phone'] ?? '—') ?></td>
                            <td>
                                <?php $loc = array_filter([$c['city'] ?? '', $c['state'] ?? '']); ?>
                                <?= $loc ? h(implode(', ', $loc)) : '—' ?>
                            </td>
                            <td><?= (int)($c['total_orders'] ?? 0) ?></td>
                            <td><?= CURRENCY ?><?= number_format((float)($c['total_spent'] ?? 0), 0) ?></td>
                            <td>
                                <span class="badge-status <?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:.8rem;"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="toggle_active" value="1">
                                    <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="new_val" value="<?= $c['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= $c['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" style="font-size:.72rem; padding:2px 8px;" title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="bi bi-<?= $c['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                                        <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center py-3">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../_layout_foot.php'; ?>