<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Orders';

$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$statusOptions = ['pending','processing','dispatched','shipped','delivered','cancelled'];

try {
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($status !== '' && in_array($status, $statusOptions)) {
        $where[]  = "status = ?";
        $params[] = $status;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = db()->prepare("SELECT COUNT(*) FROM orders $whereSQL");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = db()->prepare(
        "SELECT id, order_number, customer_name, customer_email, total,
                payment_method, payment_status, status, created_at
         FROM orders $whereSQL
         ORDER BY created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $orders = []; $total = 0; $totalPages = 1;
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-cart-check me-2" style="color:#f8c146"></i>Orders</h1>
</div>

<!-- Filter bar -->
<div class="admin-card mb-3" style="padding:16px 20px;">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-5">
            <label class="form-label mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, name, email…" value="<?= h($search) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
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
    <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6;">
        <span class="text-muted" style="font-size:.85rem;"><?= number_format($total) ?> order<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($orders)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-cart-x d-block mb-2" style="font-size:2rem;"></i>
        No orders found.
        <?php if ($search || $status): ?><a href="index.php">Clear filters</a><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td>
                    <a href="view.php?id=<?= (int)$order['id'] ?>" class="text-decoration-none fw-600" style="font-weight:600;">
                        #<?= h($order['order_number']) ?>
                    </a>
                </td>
                <td>
                    <div><?= h($order['customer_name']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= h($order['customer_email']) ?></div>
                </td>
                <td><?= CURRENCY ?><?= number_format((float)$order['total'], 2) ?></td>
                <td>
                    <div style="font-size:.8rem;"><?= h($order['payment_method'] ?? '—') ?></div>
                    <?php $ps = $order['payment_status'] ?? 'unpaid'; ?>
                    <span class="badge-status badge-<?= h($ps) ?>"><?= h($ps) ?></span>
                </td>
                <td>
                    <span class="badge-status badge-<?= h($order['status']) ?>"><?= h($order['status']) ?></span>
                </td>
                <td class="text-muted" style="font-size:.8rem;">
                    <?= date('d M Y', strtotime($order['created_at'])) ?><br>
                    <?= date('H:i', strtotime($order['created_at'])) ?>
                </td>
                <td>
                    <a href="view.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:.75rem; padding:3px 9px;">
                        <i class="bi bi-eye me-1"></i>View
                    </a>
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
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../_layout_foot.php'; ?>
