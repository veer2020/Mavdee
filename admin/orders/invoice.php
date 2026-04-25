<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    echo '<p>Invalid order ID.</p>';
    exit;
}

try {
    $stmt = db()->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo '<p>Order not found.</p>';
        exit;
    }

    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>Error loading order.</p>';
    exit;
}

// Decode shipping address
$shipping = [];
if (!empty($order['shipping_address'])) {
    $decoded = json_decode($order['shipping_address'], true);
    $shipping = is_array($decoded) ? $decoded : ['address' => $order['shipping_address']];
}

$siteName = getSetting('site_name', defined('SITE_NAME') ? SITE_NAME : 'Mavdee');
$siteUrl  = defined('SITE_URL') ? SITE_URL : '';
$currency = defined('CURRENCY') ? CURRENCY : '₹';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= h($order['order_number']) ?> | <?= h($siteName) ?></title>
    <style>
        /* ── Reset & Base ─────────────────────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 14px;
        }

        body {
            font-family: 'Georgia', serif;
            color: #1a1a1a;
            background: #f5f5f5;
            line-height: 1.6;
        }

        /* ── Page Wrapper ─────────────────────────────────────────────────── */
        .invoice-page {
            max-width: 860px;
            margin: 40px auto;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 56px 60px;
        }

        /* ── Screen-only controls ─────────────────────────────────────────── */
        .screen-controls {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
        }

        .btn-print {
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 24px;
            font-family: inherit;
            font-size: 0.9rem;
            cursor: pointer;
            letter-spacing: 0.04em;
        }

        .btn-print:hover {
            background: #c9a96e;
        }

        .btn-back {
            color: #555;
            text-decoration: none;
            padding: 10px 0;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            color: #1a1a1a;
        }

        /* ── Header ───────────────────────────────────────────────────────── */
        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 28px;
            border-bottom: 2px solid #1a1a1a;
        }

        .inv-brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .inv-logo {
            font-family: 'Georgia', serif;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: #1a1a1a;
        }

        .inv-tagline {
            font-size: 0.8rem;
            color: #777;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .inv-label-block {
            text-align: right;
        }

        .inv-label {
            font-size: 2.4rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #c9a96e;
            text-transform: uppercase;
            line-height: 1;
            margin-bottom: 8px;
        }

        .inv-meta {
            font-size: 0.8rem;
            color: #555;
        }

        .inv-meta span {
            display: block;
        }

        /* ── Addresses ────────────────────────────────────────────────────── */
        .inv-addresses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 36px;
        }

        .addr-block h4 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #999;
            margin-bottom: 8px;
            font-family: 'Arial', sans-serif;
        }

        .addr-block address {
            font-style: normal;
            font-size: 0.875rem;
            line-height: 1.7;
            color: #333;
        }

        /* ── Items Table ──────────────────────────────────────────────────── */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .inv-table thead tr {
            background: #1a1a1a;
            color: #fff;
        }

        .inv-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            font-family: 'Arial', sans-serif;
        }

        .inv-table thead th:last-child,
        .inv-table tbody td:last-child,
        .inv-table tfoot td:last-child {
            text-align: right;
        }

        .inv-table thead th:nth-child(4),
        .inv-table tbody td:nth-child(4),
        .inv-table thead th:nth-child(5),
        .inv-table tbody td:nth-child(5) {
            text-align: center;
        }

        .inv-table tbody tr {
            border-bottom: 1px solid #eee;
        }

        .inv-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .inv-table tbody td {
            padding: 10px 14px;
            font-size: 0.875rem;
            color: #333;
        }

        .inv-table tfoot tr {
            border-top: 1px solid #ddd;
        }

        .inv-table tfoot td {
            padding: 6px 14px;
            font-size: 0.875rem;
            color: #555;
        }

        .inv-table tfoot tr.grand-total td {
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a1a;
            border-top: 2px solid #1a1a1a;
            padding-top: 10px;
        }

        /* ── Payment Info ─────────────────────────────────────────────────── */
        .inv-payment {
            display: flex;
            gap: 32px;
            margin-bottom: 40px;
            padding: 16px 20px;
            background: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            font-size: 0.8rem;
        }

        .inv-payment span {
            color: #999;
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 2px;
        }

        /* ── Footer ───────────────────────────────────────────────────────── */
        .inv-footer {
            text-align: center;
            padding-top: 28px;
            border-top: 1px solid #eee;
            font-size: 0.8rem;
            color: #888;
            line-height: 1.8;
        }

        .inv-footer a {
            color: #c9a96e;
            text-decoration: none;
        }

        /* ── Print Styles ─────────────────────────────────────────────────── */
        @media print {
            body {
                background: #fff;
                font-size: 12px;
            }

            .screen-controls {
                display: none !important;
            }

            .invoice-page {
                margin: 0;
                max-width: 100%;
                box-shadow: none;
                padding: 20mm 16mm;
                border-radius: 0;
            }

            .inv-table thead tr {
                background: #1a1a1a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .inv-table tbody tr:nth-child(even) {
                background: #fafafa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .inv-label {
                color: #c9a96e !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            a {
                color: inherit !important;
                text-decoration: none !important;
            }

            @page {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-page">

        <!-- Screen-only controls (hidden on print) -->
        <div class="screen-controls">
            <button class="btn-print" onclick="window.print()">🖨 Print Invoice</button>
            <a href="view.php?id=<?= (int)$order['id'] ?>" class="btn-back">← Back to Order</a>
        </div>

        <!-- Invoice Header -->
        <div class="inv-header">
            <div class="inv-brand">
                <span class="inv-logo"><?= h($siteName) ?></span>
                <span class="inv-tagline">Premium Occasionwear</span>
            </div>
            <div class="inv-label-block">
                <div class="inv-label">Invoice</div>
                <div class="inv-meta">
                    <span><strong>Invoice #:</strong> <?= h($order['order_number']) ?></span>
                    <span><strong>Date:</strong> <?= date('d M Y', strtotime($order['created_at'])) ?></span>
                    <span><strong>Order #:</strong> <?= h($order['order_number']) ?></span>
                </div>
            </div>
        </div>

        <!-- Addresses -->
        <div class="inv-addresses">
            <div class="addr-block">
                <h4>Bill To</h4>
                <address>
                    <strong><?= h($order['customer_name']) ?></strong><br>
                    <?= h($order['customer_email']) ?><br>
                    <?php if (!empty($order['customer_phone'])): ?>
                        <?= h($order['customer_phone']) ?><br>
                    <?php endif; ?>
                    <?php foreach ($shipping as $key => $val):
                        if (!$val) continue;
                        $display = is_array($val) ? implode(', ', $val) : (string)$val;
                    ?>
                        <?= h($display) ?><br>
                    <?php endforeach; ?>
                </address>
            </div>
            <div class="addr-block">
                <h4>Ship To</h4>
                <address>
                    <strong><?= h($order['customer_name']) ?></strong><br>
                    <?php foreach ($shipping as $key => $val):
                        if (!$val) continue;
                        $display = is_array($val) ? implode(', ', $val) : (string)$val;
                    ?>
                        <?= h($display) ?><br>
                    <?php endforeach; ?>
                </address>
            </div>
        </div>

        <!-- Items Table -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Size / Color</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#999; padding:20px;">No items found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h($item['product_name']) ?></td>
                            <td style="color:#888; font-size:0.8rem;"><?= h($item['sku'] ?? '—') ?></td>
                            <td>
                                <?php
                                $variants = array_filter([$item['size'] ?? '', $item['color'] ?? '']);
                                echo $variants ? h(implode(' / ', $variants)) : '—';
                                ?>
                            </td>
                            <td style="text-align:center;"><?= (int)$item['qty'] ?></td>
                            <td style="text-align:right;"><?= h($currency) ?><?= number_format((float)($item['unit_price'] ?? $item['price'] ?? 0), 2) ?></td>
                            <td style="text-align:right;"><strong><?= h($currency) ?><?= number_format((float)$item['total_price'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right; color:#555;">Subtotal</td>
                    <td><?= h($currency) ?><?= number_format((float)$order['subtotal'], 2) ?></td>
                </tr>
                <?php
                $shippingCost = (float)($order['shipping_amount'] ?? $order['shipping_cost'] ?? 0);
                if ($shippingCost > 0): ?>
                    <tr>
                        <td colspan="5" style="text-align:right; color:#555;">Shipping</td>
                        <td><?= h($currency) ?><?= number_format($shippingCost, 2) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ((float)($order['tax_amount'] ?? 0) > 0): ?>
                    <tr>
                        <td colspan="5" style="text-align:right; color:#555;">Tax</td>
                        <td><?= h($currency) ?><?= number_format((float)$order['tax_amount'], 2) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
                    <tr>
                        <td colspan="5" style="text-align:right; color:#888;">Discount</td>
                        <td style="color:#228b22;">−<?= h($currency) ?><?= number_format((float)$order['discount_amount'], 2) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td colspan="5" style="text-align:right;">Grand Total</td>
                    <td><?= h($currency) ?><?= number_format((float)$order['total'], 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Payment Info -->
        <div class="inv-payment">
            <div>
                <span>Payment Method</span>
                <?= h(ucfirst($order['payment_method'] ?? '—')) ?>
            </div>
            <div>
                <span>Payment Status</span>
                <?= h(ucfirst($order['payment_status'] ?? 'Unpaid')) ?>
            </div>
            <div>
                <span>Order Status</span>
                <?= h(ucfirst($order['status'] ?? '—')) ?>
            </div>
            <?php if (!empty($order['tracking_number'])): ?>
                <div>
                    <span>Tracking</span>
                    <?= h($order['tracking_number']) ?><?= !empty($order['courier']) ? ' (' . h($order['courier']) . ')' : '' ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="inv-footer">
            <p>Thank you for shopping with <?= h($siteName) ?>! We hope you love your order.</p>
            <p>
                <a href="<?= h($siteUrl) ?>/returns.php">Returns &amp; Exchanges</a>
                &nbsp;·&nbsp;
                <a href="<?= h($siteUrl) ?>/shipping.php">Shipping Policy</a>
                &nbsp;·&nbsp;
                <?= h($siteUrl) ?>
            </p>
            <p style="margin-top:8px; font-size:0.75rem; color:#bbb;">This is a system-generated invoice and does not require a signature.</p>
        </div>

    </div>
</body>

</html>