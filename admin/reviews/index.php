<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Reviews';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action'] ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($reviewId > 0) {
        try {
            if ($action === 'approve') {
                db()->prepare("UPDATE product_reviews SET is_approved=1 WHERE id=?")->execute([$reviewId]);
                logAdminActivity('approve_review', "Review ID $reviewId approved");
                setFlash('success', 'Review approved.');
            } elseif ($action === 'reject') {
                db()->prepare("UPDATE product_reviews SET is_approved=0 WHERE id=?")->execute([$reviewId]);
                logAdminActivity('reject_review', "Review ID $reviewId rejected");
                setFlash('success', 'Review rejected.');
            } elseif ($action === 'delete') {
                db()->prepare("DELETE FROM product_reviews WHERE id=?")->execute([$reviewId]);
                logAdminActivity('delete_review', "Review ID $reviewId deleted");
                setFlash('success', 'Review deleted.');
            }
        } catch (Throwable $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
    header('Location: index.php'); exit;
}

$filter  = trim($_GET['filter'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

try {
    $where  = [];
    $params = [];

    if ($filter === 'pending') {
        $where[]  = "r.is_approved IS NULL";
    } elseif ($filter === 'approved') {
        $where[]  = "r.is_approved = 1";
    } elseif ($filter === 'rejected') {
        $where[]  = "r.is_approved = 0";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = db()->prepare("SELECT COUNT(*) FROM product_reviews r $whereSQL");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = db()->prepare(
        "SELECT r.*, p.name AS product_name
         FROM product_reviews r
         LEFT JOIN products p ON p.id = r.product_id
         $whereSQL
         ORDER BY r.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();

    // Counts for filter tabs
    $pendingCount  = (int)db()->query("SELECT COUNT(*) FROM product_reviews WHERE is_approved IS NULL")->fetchColumn();
    $approvedCount = (int)db()->query("SELECT COUNT(*) FROM product_reviews WHERE is_approved=1")->fetchColumn();
    $rejectedCount = (int)db()->query("SELECT COUNT(*) FROM product_reviews WHERE is_approved=0")->fetchColumn();

} catch (Throwable $e) {
    $reviews = []; $total = 0; $totalPages = 1;
    $pendingCount = $approvedCount = $rejectedCount = 0;
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-star-half me-2" style="color:#f8c146"></i>Product Reviews</h1>
</div>

<!-- Filter tabs -->
<div class="mb-3">
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php" class="btn btn-sm <?= $filter === '' ? 'btn-accent' : 'btn-outline-secondary' ?>">
            All <span class="badge bg-secondary ms-1"><?= $pendingCount + $approvedCount + $rejectedCount ?></span>
        </a>
        <a href="?filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-accent' : 'btn-outline-secondary' ?>">
            Pending <span class="badge bg-warning text-dark ms-1"><?= $pendingCount ?></span>
        </a>
        <a href="?filter=approved" class="btn btn-sm <?= $filter === 'approved' ? 'btn-accent' : 'btn-outline-secondary' ?>">
            Approved <span class="badge bg-success ms-1"><?= $approvedCount ?></span>
        </a>
        <a href="?filter=rejected" class="btn btn-sm <?= $filter === 'rejected' ? 'btn-accent' : 'btn-outline-secondary' ?>">
            Rejected <span class="badge bg-danger ms-1"><?= $rejectedCount ?></span>
        </a>
    </div>
</div>

<div class="admin-card" style="padding:0; overflow:hidden;">
    <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6;">
        <span class="text-muted" style="font-size:.85rem;"><?= number_format($total) ?> review<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($reviews)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-star d-block mb-2" style="font-size:2rem;"></i>
        No reviews found.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $review): ?>
            <?php
                $status = match($review['is_approved']) {
                    null    => 'pending',
                    '1', 1  => 'approved',
                    default => 'rejected',
                };
            ?>
            <tr>
                <td style="font-size:.85rem; font-weight:500; max-width:140px;">
                    <?= h($review['product_name'] ?? 'Unknown') ?>
                </td>
                <td style="font-size:.85rem;">
                    <div><?= h($review['name']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= h($review['email']) ?></div>
                </td>
                <td>
                    <div class="stars" style="font-size:.9rem;">
                        <?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?>
                    </div>
                    <div style="font-size:.72rem; color:#9ca3af;"><?= (int)$review['rating'] ?>/5</div>
                </td>
                <td style="max-width:200px;">
                    <?php if ($review['title']): ?>
                    <div style="font-weight:600; font-size:.85rem;"><?= h($review['title']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted" style="font-size:.8rem;">
                        <?= h(mb_strimwidth($review['body'] ?? '', 0, 100, '…')) ?>
                    </div>
                </td>
                <td>
                    <span class="badge-status badge-<?= $status === 'pending' ? 'pending-review' : $status ?>">
                        <?= ucfirst($status) ?>
                    </span>
                </td>
                <td class="text-muted" style="font-size:.8rem;"><?= date('d M Y', strtotime($review['created_at'])) ?></td>
                <td>
                    <?php if ($status !== 'approved'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success" style="font-size:.72rem; padding:2px 6px;" title="Approve">
                            <i class="bi bi-check-circle"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($status !== 'rejected'): ?>
                    <form method="POST" class="d-inline ms-1">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning" style="font-size:.72rem; padding:2px 6px;" title="Reject">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline ms-1">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:.72rem; padding:2px 6px;"
                            onclick="return confirm('Delete this review?')" title="Delete">
                            <i class="bi bi-trash3"></i>
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
                    <a class="page-link" href="?page=<?= $i ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../_layout_foot.php'; ?>
