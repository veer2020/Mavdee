<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Coupons';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $type        = $_POST['type'] ?? 'percent';
        $value       = (float)($_POST['value'] ?? 0);
        $minOrder    = $_POST['min_order'] !== '' ? (float)$_POST['min_order'] : null;
        $maxDiscount = $_POST['max_discount'] !== '' ? (float)$_POST['max_discount'] : null;
        $usageLimit  = $_POST['usage_limit'] !== '' ? (int)$_POST['usage_limit'] : null;
        $expiresAt   = trim($_POST['expires_at'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '' || $value <= 0) {
            setFlash('danger', 'Code and value are required.');
        } else {
            try {
                db()->prepare(
                    "INSERT INTO coupons (code, description, type, value, min_order, max_discount,
                     usage_limit, used_count, expires_at, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())"
                )->execute([
                    $code,
                    $description,
                    $type,
                    $value,
                    $minOrder,
                    $maxDiscount,
                    $usageLimit,
                    $expiresAt ?: null,
                    $isActive
                ]);
                logAdminActivity('add_coupon', "Added coupon: $code");
                setFlash('success', 'Coupon "' . h($code) . '" created.');
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        $cid    = (int)($_POST['id'] ?? 0);
        $newVal = (int)($_POST['new_val'] ?? 0);
        if ($cid > 0) {
            try {
                db()->prepare("UPDATE coupons SET is_active=? WHERE id=?")->execute([$newVal, $cid]);
                logAdminActivity('toggle_coupon', "Coupon ID $cid active=$newVal");
            } catch (Throwable) {
            }
        }
    } elseif ($action === 'delete') {
        $cid = (int)($_POST['id'] ?? 0);
        if ($cid > 0) {
            try {
                $s = db()->prepare("SELECT code FROM coupons WHERE id=?");
                $s->execute([$cid]);
                $row = $s->fetch();
                db()->prepare("DELETE FROM coupons WHERE id=?")->execute([$cid]);
                logAdminActivity('delete_coupon', 'Deleted: ' . ($row['code'] ?? 'ID ' . $cid));
                setFlash('success', 'Coupon deleted.');
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
    }

    header('Location: index.php');
    exit;
}

try {
    $coupons = db()->query(
        "SELECT * FROM coupons ORDER BY created_at DESC"
    )->fetchAll();
} catch (Throwable) {
    $coupons = [];
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-ticket-perforated me-2" style="color:#f8c146"></i>Coupons</h1>
</div>

<div class="row g-3">
    <!-- Add coupon form -->
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-plus-circle me-2"></i>New Coupon</h6>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">

                <div class="mb-3">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="SUMMER20" style="text-transform:uppercase;" required>
                    <div class="form-text">Will be auto-uppercased.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="20% off on all orders">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="percent">Percent (%)</option>
                            <option value="fixed">Fixed (<?= CURRENCY ?>)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Value <span class="text-danger">*</span></label>
                        <input type="number" name="value" class="form-control form-control-sm" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Min Order (<?= CURRENCY ?>)</label>
                        <input type="number" name="min_order" class="form-control form-control-sm" step="0.01" min="0" placeholder="Optional">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Max Discount (<?= CURRENCY ?>)</label>
                        <input type="number" name="max_discount" class="form-control form-control-sm" step="0.01" min="0" placeholder="Optional">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Usage Limit</label>
                        <input type="number" name="usage_limit" class="form-control form-control-sm" min="0" placeholder="Unlimited">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Expires At</label>
                        <input type="date" name="expires_at" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="coup_active" checked>
                        <label class="form-check-label" for="coup_active">Active</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-accent btn-sm w-100">
                    <i class="bi bi-plus-circle me-1"></i>Create Coupon
                </button>
            </form>
        </div>
    </div>

    <!-- Coupons table -->
    <div class="col-lg-8">
        <div class="admin-card" style="padding:0; overflow:hidden;">
            <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6; font-weight:700;">
                <?= count($coupons) ?> Coupon<?= count($coupons) !== 1 ? 's' : '' ?>
            </div>
            <?php if (empty($coupons)): ?>
                <div class="text-center py-5 text-muted">No coupons yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Min Order</th>
                                <th>Usage</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): ?>
                                <?php
                                $expired = $coupon['expires_at'] && strtotime($coupon['expires_at']) < time();
                                $usageFull = $coupon['usage_limit'] && (int)$coupon['used_count'] >= (int)$coupon['usage_limit'];
                                ?>
                                <tr>
                                    <td>
                                        <code style="font-size:.85rem; font-weight:700; color:#1a1d23;"><?= h($coupon['code']) ?></code>
                                        <?php if ($coupon['description']): ?>
                                            <div class="text-muted" style="font-size:.75rem;"><?= h($coupon['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $coupon['type'] === 'percent' ? 'badge-percent' : 'badge-fixed' ?>">
                                            <?= h($coupon['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $coupon['type'] === 'percent' ? h((string)$coupon['value']) . '%' : CURRENCY . number_format((float)$coupon['value'], 0) ?>
                                    </td>
                                    <td><?= $coupon['min_order'] ? CURRENCY . number_format((float)$coupon['min_order'], 0) : '—' ?></td>
                                    <td>
                                        <?= (int)$coupon['used_count'] ?><?= $coupon['usage_limit'] ? ' / ' . (int)$coupon['usage_limit'] : '' ?>
                                    </td>
                                    <td style="font-size:.8rem; <?= $expired ? 'color:#dc3545;' : '' ?>">
                                        <?= $coupon['expires_at'] ? date('d M Y', strtotime($coupon['expires_at'])) : '∞' ?>
                                        <?php if ($expired): ?><span class="text-danger">(expired)</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $coupon['is_active'] && !$expired && !$usageFull ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $coupon['is_active'] && !$expired && !$usageFull ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int)$coupon['id'] ?>">
                                            <input type="hidden" name="new_val" value="<?= $coupon['is_active'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-sm <?= $coupon['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" style="font-size:.72rem; padding:2px 6px;" title="Toggle">
                                                <i class="bi bi-toggle-<?= $coupon['is_active'] ? 'on' : 'off' ?>"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-1" style="font-size:.72rem; padding:2px 6px;"
                                            onclick="deleteCoupon(<?= (int)$coupon['id'] ?>, '<?= h(addslashes($coupon['code'])) ?>')">
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
<div class="modal fade" id="deleteCouponModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-danger d-block mb-2" style="font-size:2rem;"></i>
                <h6 class="mb-1">Delete Coupon?</h6>
                <p class="text-muted mb-3" id="deleteCouponCode" style="font-size:.875rem;"></p>
                <form method="POST" id="deleteCouponForm">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteCouponId">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    function deleteCoupon(id, code) {
        document.getElementById('deleteCouponId').value = id;
        document.getElementById('deleteCouponCode').textContent = code;
        new bootstrap.Modal(document.getElementById('deleteCouponModal')).show();
    }
</script>

<?php include __DIR__ . '/../_layout_foot.php'; ?>