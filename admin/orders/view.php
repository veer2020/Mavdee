<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../api/shipping/delhivery.php';
requireAdminLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Invalid order ID.');
    header('Location: index.php');
    exit;
}

try {
    $order = db()->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $order->execute([$id]);
    $order = $order->fetch();

    if (!$order) {
        setFlash('danger', 'Order not found.');
        header('Location: index.php');
        exit;
    }

    $items = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items->execute([$id]);
    $items = $items->fetchAll();

    $history = db()->prepare(
        "SELECT osh.*, au.name AS admin_name
         FROM order_status_history osh
         LEFT JOIN admin_users au ON au.id = osh.created_by
         WHERE osh.order_id = ?
         ORDER BY osh.created_at ASC"
    );
    $history->execute([$id]);
    $history = $history->fetchAll();
} catch (Throwable $e) {
    setFlash('danger', 'Error loading order.');
    header('Location: index.php');
    exit;
}

// Decode shipping address
$shipping = [];
if (!empty($order['shipping_address'])) {
    $decoded = json_decode($order['shipping_address'], true);
    $shipping = is_array($decoded) ? $decoded : ['address' => $order['shipping_address']];
}

$pageTitle     = 'Order #' . $order['order_number'];
$statusOptions = ['pending', 'processing', 'dispatched', 'shipped', 'delivered', 'cancelled'];
$dlvSettings   = getPaymentSettings('delhivery');
$dlvEnabled    = !empty($dlvSettings['enabled']) && !empty($dlvSettings['token']);
$hasWaybill    = !empty($order['tracking_number']) && ($order['courier'] ?? '') === 'Delhivery';

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt me-2" style="color:#f8c146"></i>Order #<?= h($order['order_number']) ?></h1>
        <span class="text-muted" style="font-size:.85rem;">
            Placed <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
        </span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="invoice.php?id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print Invoice
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Orders
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Left column: order details -->
    <div class="col-lg-8">
        <!-- Order items -->
        <div class="admin-card mb-3" style="padding:0; overflow:hidden;">
            <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6; font-weight:700;">
                <i class="bi bi-bag me-2"></i>Order Items
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No items found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td style="font-weight:500;"><?= h($item['product_name']) ?></td>
                                    <td class="text-muted" style="font-size:.8rem;"><?= h($item['sku'] ?? '—') ?></td>
                                    <td><?= h($item['size'] ?? '—') ?></td>
                                    <td><?= h($item['color'] ?? '—') ?></td>
                                    <td><?= (int)$item['qty'] ?></td>
                                    <td><?= CURRENCY ?><?= number_format((float)$item['unit_price'], 2) ?></td>
                                    <td><strong><?= CURRENCY ?><?= number_format((float)$item['total_price'], 2) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="px-4 py-3" style="border-top:1px solid #f3f4f6;">
                <div class="d-flex justify-content-end">
                    <table style="width:240px; font-size:.875rem;">
                        <tr>
                            <td class="py-1 text-muted">Subtotal</td>
                            <td class="py-1 text-end"><?= CURRENCY ?><?= number_format((float)$order['subtotal'], 2) ?></td>
                        </tr>
                        <?php
                        $shippingCost = (float)($order['shipping_amount'] ?? $order['shipping_cost'] ?? 0);
                        if ($shippingCost > 0): ?>
                            <tr>
                                <td class="py-1 text-muted">Shipping</td>
                                <td class="py-1 text-end"><?= CURRENCY ?><?= number_format($shippingCost, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((float)($order['tax'] ?? 0) > 0): ?>
                            <tr>
                                <td class="py-1 text-muted">Tax</td>
                                <td class="py-1 text-end"><?= CURRENCY ?><?= number_format((float)$order['tax'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((float)($order['discount'] ?? 0) > 0): ?>
                            <tr>
                                <td class="py-1 text-muted">Discount</td>
                                <td class="py-1 text-end text-success">−<?= CURRENCY ?><?= number_format((float)$order['discount'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr style="border-top:2px solid #e5e7eb;">
                            <td class="pt-2" style="font-weight:700;">Total</td>
                            <td class="pt-2 text-end" style="font-weight:700; font-size:1rem;"><?= CURRENCY ?><?= number_format((float)$order['total'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Status history -->
        <div class="admin-card" style="padding:0; overflow:hidden;">
            <div class="px-4 py-3" style="border-bottom:1px solid #f3f4f6; font-weight:700;">
                <i class="bi bi-clock-history me-2"></i>Status History
            </div>
            <div class="p-4">
                <?php if (empty($history)): ?>
                    <p class="text-muted mb-0">No status history yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($history as $h_item): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div>
                                    <span class="badge-status badge-<?= h($h_item['status']) ?>"><?= h($h_item['status']) ?></span>
                                    <?php if ($h_item['admin_name']): ?>
                                        <span class="text-muted" style="font-size:.8rem;"> by <?= h($h_item['admin_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($h_item['note']): ?>
                                    <div class="timeline-note"><?= h($h_item['note']) ?></div>
                                <?php endif; ?>
                                <div class="timeline-time"><?= date('d M Y, H:i', strtotime($h_item['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right column -->
    <div class="col-lg-4">
        <!-- Order info -->
        <div class="admin-card mb-3">
            <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-info-circle me-2"></i>Order Info</h6>
            <dl class="row mb-0" style="font-size:.875rem; row-gap:.25rem;">
                <dt class="col-5 text-muted fw-normal">Order #</dt>
                <dd class="col-7 mb-0 fw-600" style="font-weight:600;"><?= h($order['order_number']) ?></dd>
                <dt class="col-5 text-muted fw-normal">Date</dt>
                <dd class="col-7 mb-0"><?= date('d M Y', strtotime($order['created_at'])) ?></dd>
                <dt class="col-5 text-muted fw-normal">Payment</dt>
                <dd class="col-7 mb-0"><?= h($order['payment_method'] ?? '—') ?></dd>
                <dt class="col-5 text-muted fw-normal">Pay Status</dt>
                <dd class="col-7 mb-0">
                    <?php $ps = $order['payment_status'] ?? 'unpaid'; ?>
                    <span class="badge-status badge-<?= h($ps) ?>"><?= h($ps) ?></span>
                </dd>
                <dt class="col-5 text-muted fw-normal">Status</dt>
                <dd class="col-7 mb-0">
                    <span class="badge-status badge-<?= h($order['status']) ?>"><?= h($order['status']) ?></span>
                </dd>
                <?php if ($order['tracking_number']): ?>
                    <dt class="col-5 text-muted fw-normal">Tracking</dt>
                    <dd class="col-7 mb-0">
                        <?php if ($hasWaybill): ?>
                            <a href="<?= h('https://www.delhivery.com/track/package/' . rawurlencode($order['tracking_number'])) ?>" target="_blank" rel="noopener">
                                <?= h($order['tracking_number']) ?>
                            </a>
                        <?php else: ?>
                            <?= h($order['tracking_number']) ?>
                        <?php endif; ?>
                    </dd>
                <?php endif; ?>
                <?php if ($order['courier']): ?>
                    <dt class="col-5 text-muted fw-normal">Courier</dt>
                    <dd class="col-7 mb-0"><?= h($order['courier']) ?></dd>
                <?php endif; ?>
            </dl>
            <?php if ($dlvEnabled && !$hasWaybill && in_array($order['status'], ['pending', 'processing', 'dispatched', 'shipped'])): ?>
                <div class="mt-3 pt-3 border-top">
                    <form method="POST" action="ship_delhivery.php">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                        <button type="submit" class="btn btn-accent btn-sm w-100">
                            <i class="bi bi-truck me-2"></i>Ship via Delhivery
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if ($hasWaybill): ?>
                <div class="mt-3 pt-3 border-top d-flex flex-column gap-2">
                    <a href="label_delhivery.php?id=<?= (int)$order['id'] ?>&csrf_token=<?= h(csrf_token()) ?>"
                        target="_blank" rel="noopener"
                        class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-printer me-2"></i>Print Shipping Label
                    </a>
                    <?php if (!in_array($order['status'], ['delivered', 'cancelled'])): ?>
                        <form method="POST" action="cancel_delhivery.php"
                            onsubmit="return confirm('Cancel this Delhivery shipment? Waybill: <?= h($order['tracking_number']) ?>')">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-x-circle me-2"></i>Cancel Delhivery Shipment
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customer info -->
        <div class="admin-card mb-3">
            <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-person me-2"></i>Customer</h6>
            <div style="font-size:.875rem;">
                <div class="fw-600" style="font-weight:600;"><?= h($order['customer_name']) ?></div>
                <div><a href="mailto:<?= h($order['customer_email']) ?>"><?= h($order['customer_email']) ?></a></div>
                <?php if ($order['customer_phone']): ?>
                    <div><?= h($order['customer_phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shipping address -->
        <div class="admin-card mb-3">
            <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-geo-alt me-2"></i>Shipping Address</h6>
            <div style="font-size:.875rem; color:#374151;">
                <?php if (!empty($shipping)): ?>
                    <?php foreach ($shipping as $key => $val):
                        if (!$val) continue;
                        $display = is_array($val) ? implode(', ', $val) : (string)$val;
                    ?>
                        <div><?= h($display) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted">No address on file.</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Admin notes -->
        <?php if ($order['admin_notes']): ?>
            <div class="admin-card mb-3">
                <h6 class="mb-2" style="font-weight:700;"><i class="bi bi-sticky me-2"></i>Admin Notes</h6>
                <p class="mb-0" style="font-size:.875rem; color:#374151;"><?= h($order['admin_notes']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Update status form -->
        <div class="admin-card">
            <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-pencil-square me-2"></i>Update Status</h6>
            <form method="POST" action="update_status.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach ($statusOptions as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tracking Number</label>
                    <input type="text" name="tracking_number" class="form-control form-control-sm" value="<?= h($order['tracking_number'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Courier</label>
                    <input type="text" name="courier" class="form-control form-control-sm" value="<?= h($order['courier'] ?? '') ?>" placeholder="e.g. Delhivery, Blue Dart">
                </div>
                <div class="mb-3">
                    <label class="form-label">Note (optional)</label>
                    <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Add a note…"></textarea>
                </div>
                <button type="submit" class="btn btn-accent btn-sm w-100">
                    <i class="bi bi-save me-2"></i>Update Order
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_layout_foot.php'; ?>