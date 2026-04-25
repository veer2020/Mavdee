<?php

/**
 * admin/returns.php
 * Admin panel for managing customer return requests.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_auth.php';
requireAdminLogin();

// csrf_field helper – defined here so it is available throughout this file
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

$pageTitle = 'Returns';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action']    ?? '';
    $returnId = (int)($_POST['return_id'] ?? 0);
    $note     = trim(strip_tags($_POST['admin_note'] ?? ''));

    $allowed = ['approve', 'reject', 'picked_up', 'refund_initiated', 'complete'];
    if ($returnId > 0 && in_array($action, $allowed, true)) {
        $statusMap = [
            'approve'          => 'approved',
            'reject'           => 'rejected',
            'picked_up'        => 'picked_up',
            'refund_initiated' => 'refund_initiated',
            'complete'         => 'completed',
        ];
        $newStatus = $statusMap[$action];
        try {
            $stmt = db()->prepare(
                "UPDATE return_requests SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$newStatus, $note ?: null, $returnId]);
            if (function_exists('logAdminActivity')) {
                logAdminActivity('update_return', "Return #$returnId → $newStatus");
            }
            setFlash('success', "Return #$returnId updated to " . $newStatus . ".");
        } catch (Throwable $e) {
            setFlash('danger', 'Could not update return: ' . $e->getMessage());
        }
    }
    header('Location: returns.php');
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($statusFilter !== '') {
    $where[]  = "rr.status = ?";
    $params[] = $statusFilter;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = db()->prepare("SELECT COUNT(*) FROM return_requests rr $whereSQL");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $rows = db()->prepare(
        "SELECT rr.*, o.order_number, c.name AS customer_name, c.email AS customer_email
         FROM return_requests rr
         LEFT JOIN orders o ON o.id = rr.order_id
         LEFT JOIN customers c ON c.id = rr.customer_id
         $whereSQL
         ORDER BY rr.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $rows->execute($params);
    $returns = $rows->fetchAll();
} catch (Throwable $e) {
    $returns = [];
    $total = $totalPages = 0;
    setFlash('warning', 'Returns table may not exist yet. Run database_updates.sql first.');
}

$statuses = ['requested', 'approved', 'picked_up', 'refund_initiated', 'completed', 'rejected'];

require __DIR__ . '/_layout_head.php';
?>

<style>
    .return-status-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 99px;
        font-size: .72rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .status-requested {
        background: #fef3c7;
        color: #92400e;
    }

    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-picked_up {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-refund_initiated {
        background: #ede9fe;
        color: #5b21b6;
    }

    .status-completed {
        background: #d1fae5;
        color: #065f46;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .filter-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .filter-tab {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: .8rem;
        cursor: pointer;
        text-decoration: none;
        color: #374151;
    }

    .filter-tab.active,
    .filter-tab:hover {
        background: var(--accent, #ff3f6c);
        color: #fff;
        border-color: var(--accent, #ff3f6c);
    }
</style>

<!-- Filter tabs -->
<div class="filter-tabs">
    <a href="returns.php" class="filter-tab <?= $statusFilter === '' ? 'active' : '' ?>">All (<?= $total ?>)</a>
    <?php foreach ($statuses as $s): ?>
        <a href="returns.php?status=<?= urlencode($s) ?>" class="filter-tab <?= $statusFilter === $s ? 'active' : '' ?>">
            <?= ucwords(str_replace('_', ' ', $s)) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returns)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No return requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($returns as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['id'] ?></td>
                            <td>
                                <a href="orders/view.php?id=<?= (int)$r['order_id'] ?>" class="text-decoration-none">
                                    <?= h($r['order_number'] ?? '#' . $r['order_id']) ?>
                                </a>
                            </td>
                            <td>
                                <?= h($r['customer_name'] ?? '—') ?>
                                <?php if (!empty($r['customer_email'])): ?>
                                    <br><small class="text-muted"><?= h($r['customer_email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= h($r['reason']) ?>
                                <?php if (!empty($r['description'])): ?>
                                    <br><small class="text-muted"><?= h(mb_strimwidth($r['description'], 0, 60, '…')) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($r['photo_url'])): ?>
                                    <br><a href="<?= h($r['photo_url']) ?>" target="_blank" rel="noopener" class="small">View photo</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="return-status-badge status-<?= h($r['status']) ?>">
                                    <?= ucwords(str_replace('_', ' ', h($r['status']))) ?>
                                </span>
                            </td>
                            <td><?= h(date('d M Y', strtotime($r['created_at']))) ?></td>
                            <td>
                                <button class="btn btn-xs btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#returnModal"
                                    data-id="<?= (int)$r['id'] ?>"
                                    data-status="<?= h($r['status']) ?>"
                                    data-note="<?= h($r['admin_note'] ?? '') ?>">
                                    Update
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?status=<?= urlencode($statusFilter) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Update Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel">
    <div class="modal-dialog">
        <form method="post" action="returns.php">
            <?= csrf_field() ?>
            <input type="hidden" name="return_id" id="modalReturnId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="returnModalLabel">Update Return #<span id="modalIdLabel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Status</label>
                        <select name="action" class="form-select" required>
                            <option value="approve">Approve</option>
                            <option value="reject">Reject</option>
                            <option value="picked_up">Picked Up</option>
                            <option value="refund_initiated">Refund Initiated</option>
                            <option value="complete">Mark Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin Note (optional)</label>
                        <textarea name="admin_note" id="modalAdminNote" class="form-control" rows="3" placeholder="Internal note or customer message…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    var returnModal = document.getElementById('returnModal');
    if (returnModal) {
        returnModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            document.getElementById('modalReturnId').value = btn.dataset.id || '';
            document.getElementById('modalIdLabel').textContent = btn.dataset.id || '';
            document.getElementById('modalAdminNote').value = btn.dataset.note || '';
        });
    }
</script>

<?php require __DIR__ . '/_layout_foot.php'; ?>