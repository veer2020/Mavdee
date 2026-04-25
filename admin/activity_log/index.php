<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Activity Log';

$search  = trim($_GET['search'] ?? '');
$action  = trim($_GET['action'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

try {
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "(au.name LIKE ? OR al.detail LIKE ? OR al.ip LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($action !== '') {
        $where[]  = "al.action = ?";
        $params[] = $action;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = db()->prepare(
        "SELECT COUNT(*) FROM activity_log al LEFT JOIN admin_users au ON al.admin_id = au.id $whereSQL"
    );
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $stmt = db()->prepare(
        "SELECT al.id, al.action, al.detail, al.ip, al.created_at, au.name AS admin_name
         FROM activity_log al
         LEFT JOIN admin_users au ON al.admin_id = au.id
         $whereSQL
         ORDER BY al.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $logs = $stmt->fetchAll();

    // Distinct actions for filter dropdown
    $actions = db()->query("SELECT DISTINCT action FROM activity_log ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $logs = []; $total = 0; $totalPages = 1; $actions = [];
}

// Build query string helper
function buildQuery(array $override = []): string
{
    $base = [
        'search' => trim($_GET['search'] ?? ''),
        'action' => trim($_GET['action'] ?? ''),
    ];
    $merged = array_merge($base, $override);
    $filtered = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    if (isset($override['page'])) {
        if ($override['page'] > 1) {
            $filtered['page'] = $override['page'];
        } else {
            unset($filtered['page']);
        }
    }
    return $filtered ? '?' . http_build_query($filtered) : '';
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-clock-history me-2" style="color:#f8c146"></i>Activity Log</h1>
    <span class="text-muted" style="font-size:.875rem;"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
</div>

<!-- Filters -->
<div class="admin-card mb-3" style="padding:16px 20px;">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-5">
            <label class="form-label mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Admin name, detail, IP…" value="<?= h($search) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label mb-1">Action</label>
            <select name="action" class="form-select form-select-sm">
                <option value="">All actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?= h($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= h($a) ?></option>
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
    <?php if (empty($logs)): ?>
    <div class="p-4 text-center text-muted">No activity records found.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Detail</th>
                <th>IP Address</th>
                <th>Date &amp; Time</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="text-muted" style="font-size:.75rem;"><?= (int)$log['id'] ?></td>
                <td><?= h($log['admin_name'] ?? '—') ?></td>
                <td>
                    <span class="badge bg-secondary" style="font-size:.72rem;font-weight:600;text-transform:none;">
                        <?= h($log['action']) ?>
                    </span>
                </td>
                <td style="max-width:320px;word-break:break-word;"><?= h($log['detail'] ?? '—') ?></td>
                <td style="font-size:.8rem;font-family:monospace;"><?= h($log['ip'] ?? '—') ?></td>
                <td style="font-size:.8rem;white-space:nowrap;" title="<?= h($log['created_at']) ?>">
                    <?php
                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $log['created_at']);
                    echo h($dt ? $dt->format('d M Y, H:i') : $log['created_at']);
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex align-items-center justify-content-between px-3 py-3" style="border-top:1px solid #f3f4f6;">
        <small class="text-muted">
            Page <?= $page ?> of <?= $totalPages ?>
            &mdash; <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="index.php<?= buildQuery(['page' => $page - 1]) ?>">‹ Prev</a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="index.php<?= buildQuery(['page' => $i]) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="index.php<?= buildQuery(['page' => $page + 1]) ?>">Next ›</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../_layout_foot.php'; ?>
