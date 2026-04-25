<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Customer Addresses';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_address'])) {
    csrf_check();
    $addrId = (int)($_POST['address_id'] ?? 0);
    if ($addrId > 0) {
        try {
            db()->prepare("DELETE FROM customer_addresses WHERE id = ?")->execute([$addrId]);
            logAdminActivity('delete_customer_address', "Address ID $addrId deleted");
        } catch (Throwable) {}
    }
    header('Location: addresses.php');
    exit;
}

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

try {
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "(ca.name LIKE ? OR c.email LIKE ? OR ca.city LIKE ? OR ca.pincode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSQL   = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt  = db()->prepare(
        "SELECT COUNT(*) FROM customer_addresses ca
         LEFT JOIN customers c ON ca.customer_id = c.id $whereSQL"
    );
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = db()->prepare(
        "SELECT ca.id, ca.label, ca.name, ca.phone, ca.address, ca.city, ca.state, ca.pincode,
                ca.is_default, ca.created_at,
                c.id AS customer_id, c.name AS customer_name, c.email AS customer_email
         FROM customer_addresses ca
         LEFT JOIN customers c ON ca.customer_id = c.id
         $whereSQL
         ORDER BY ca.created_at DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $addresses = $stmt->fetchAll();
} catch (Throwable $e) {
    $addresses  = [];
    $total      = 0;
    $totalPages = 1;
}

require_once __DIR__ . '/../_layout_head.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-bold">Customer Addresses</h4>
        <p class="text-muted mb-0 small">Manage all saved delivery addresses</p>
    </div>
</div>

<!-- Search -->
<form method="GET" class="mb-3">
    <div class="input-group" style="max-width:400px;">
        <input type="text" name="search" class="form-control" placeholder="Search by name, email, city, PIN…" value="<?= h($search) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search): ?><a href="addresses.php" class="btn btn-outline-danger"><i class="bi bi-x"></i></a><?php endif; ?>
    </div>
</form>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($addresses)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-geo-alt" style="font-size:2rem;"></i>
                <p class="mt-2">No addresses found.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Customer</th>
                        <th>Label</th>
                        <th>Recipient</th>
                        <th>Address</th>
                        <th>City / State</th>
                        <th>PIN</th>
                        <th>Default</th>
                        <th>Saved On</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($addresses as $addr): ?>
                    <tr>
                        <td>
                            <a href="../customers/index.php?search=<?= urlencode($addr['customer_email']) ?>" class="fw-semibold text-decoration-none">
                                <?= h($addr['customer_name']) ?>
                            </a>
                            <div class="text-muted small"><?= h($addr['customer_email']) ?></div>
                        </td>
                        <td><span class="badge bg-secondary"><?= h($addr['label']) ?></span></td>
                        <td><?= h($addr['name']) ?><?php if ($addr['phone']): ?><div class="text-muted small"><?= h($addr['phone']) ?></div><?php endif; ?></td>
                        <td><?= h($addr['address']) ?></td>
                        <td><?= h($addr['city']) ?><?php if ($addr['state']): ?>, <?= h($addr['state']) ?><?php endif; ?></td>
                        <td><?= h($addr['pincode']) ?></td>
                        <td>
                            <?php if ($addr['is_default']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-circle text-muted"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($addr['created_at'])) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this address?');" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="delete_address" value="1">
                                <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="p-3 d-flex justify-content-between align-items-center border-top">
            <small class="text-muted"><?= $total ?> total addresses</small>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../_layout_foot.php'; ?>
