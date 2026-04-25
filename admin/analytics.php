<?php

/**
 * admin/analytics.php
 * Sales & operational analytics dashboard.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_auth.php';
requireAdminLogin();

$pageTitle = 'Analytics';

// ── Date range ────────────────────────────────────────────────────────────────
$range   = $_GET['range'] ?? '30';
$range   = in_array($range, ['7', '30', '90', '365'], true) ? (int)$range : 30;
$dateFrom = date('Y-m-d', strtotime("-{$range} days"));
$dateTo   = date('Y-m-d');

// ── KPI stats ─────────────────────────────────────────────────────────────────
try {
    $totalRevenue = (float)db()->query(
        "SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'"
    )->fetchColumn();

    $prStmt = db()->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?");
    $prStmt->execute([$dateFrom, $dateTo]);
    $periodRevenue = (float)$prStmt->fetchColumn();

    $poStmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
    $poStmt->execute([$dateFrom, $dateTo]);
    $periodOrders = (int)$poStmt->fetchColumn();

    $ncStmt = db()->prepare("SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN ? AND ?");
    $ncStmt->execute([$dateFrom, $dateTo]);
    $newCustomers = (int)$ncStmt->fetchColumn();

    $totalProducts = (int)db()->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    $lowStock      = (int)db()->query("SELECT COUNT(*) FROM products WHERE stock <= low_stock_alert AND is_active=1")->fetchColumn();
    $outOfStock    = (int)db()->query("SELECT COUNT(*) FROM products WHERE stock = 0 AND is_active=1")->fetchColumn();

    // Revenue by day for chart
    $revByDayStmt = db()->prepare(
        "SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS rev
         FROM orders WHERE status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY day ORDER BY day ASC"
    );
    $revByDayStmt->execute([$dateFrom, $dateTo]);
    $revByDay = $revByDayStmt->fetchAll();

    // Orders by day
    $ordByDayStmt = db()->prepare(
        "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
         FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY day ORDER BY day ASC"
    );
    $ordByDayStmt->execute([$dateFrom, $dateTo]);
    $ordByDay = $ordByDayStmt->fetchAll();

    // Top products
    $topProducts = db()->prepare(
        "SELECT p.name, p.sku, SUM(oi.qty) AS sold, SUM(oi.qty * oi.price) AS revenue
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
         WHERE DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY p.id ORDER BY sold DESC LIMIT 10"
    );
    $topProducts->execute([$dateFrom, $dateTo]);
    $topProducts = $topProducts->fetchAll();

    // Top customers
    $topCustomers = db()->prepare(
        "SELECT c.name, c.email, COUNT(o.id) AS orders, SUM(o.total) AS spent
         FROM orders o JOIN customers c ON c.id = o.customer_id
         WHERE o.status != 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY o.customer_id ORDER BY spent DESC LIMIT 10"
    );
    $topCustomers->execute([$dateFrom, $dateTo]);
    $topCustomers = $topCustomers->fetchAll();

    // Status breakdown
    $statusStmt = db()->prepare(
        "SELECT status, COUNT(*) AS cnt FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status"
    );
    $statusStmt->execute([$dateFrom, $dateTo]);
    $statusBreakdown = $statusStmt->fetchAll();

    // Payment methods
    $pmStmt = db()->prepare(
        "SELECT payment_method, COUNT(*) AS cnt FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method"
    );
    $pmStmt->execute([$dateFrom, $dateTo]);
    $pmBreakdown = $pmStmt->fetchAll();
} catch (Throwable $e) {
    $totalRevenue = $periodRevenue = 0.0;
    $periodOrders = $newCustomers = $totalProducts = $lowStock = $outOfStock = 0;
    $revByDay = $ordByDay = $topProducts = $topCustomers = $statusBreakdown = $pmBreakdown = [];
}

// Prepare chart data (JSON)
$chartDays   = array_column($revByDay, 'day');
$chartRevs   = array_column($revByDay, 'rev');
$chartOrds   = [];
$ordMap      = array_column($ordByDay, 'cnt', 'day');
foreach ($chartDays as $d) {
    $chartOrds[] = $ordMap[$d] ?? 0;
}

require __DIR__ . '/_layout_head.php';
?>

<style>
    .analytics-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .kpi-card {
        background: #fff;
        border-radius: 10px;
        padding: 18px 20px;
        border: 1px solid #e8ecf0;
    }

    .kpi-label {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #111827;
    }

    .kpi-sub {
        font-size: .75rem;
        color: #9ca3af;
        margin-top: 3px;
    }

    .kpi-icon {
        float: right;
        font-size: 1.8rem;
        opacity: .15;
    }

    .chart-wrap {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e8ecf0;
        padding: 20px;
        margin-bottom: 24px;
    }

    .chart-title {
        font-size: .85rem;
        font-weight: 700;
        color: #374151;
        margin-bottom: 16px;
    }

    .section-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    @media (max-width: 768px) {
        .section-row {
            grid-template-columns: 1fr;
        }
    }

    .table-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e8ecf0;
        padding: 16px;
    }

    .table-card h3 {
        font-size: .83rem;
        font-weight: 700;
        color: #374151;
        margin: 0 0 12px;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .table-card table {
        width: 100%;
        border-collapse: collapse;
        font-size: .82rem;
    }

    .table-card th {
        text-align: left;
        padding: 6px 10px;
        border-bottom: 2px solid #f3f4f6;
        color: #6b7280;
        font-weight: 600;
        font-size: .72rem;
        text-transform: uppercase;
    }

    .table-card td {
        padding: 8px 10px;
        border-bottom: 1px solid #f9fafb;
        color: #374151;
    }

    .table-card tr:last-child td {
        border-bottom: none;
    }

    .badge-status {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: .7rem;
        font-weight: 600;
    }

    .badge-delivered {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-processing {
        background: #dbeafe;
        color: #1e40af;
    }

    .range-btn {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 5px 12px;
        font-size: .8rem;
        cursor: pointer;
        background: #fff;
        color: #374151;
        transition: all .15s;
    }

    .range-btn.active,
    .range-btn:hover {
        background: var(--accent, #ff3f6c);
        color: #fff;
        border-color: var(--accent, #ff3f6c);
    }

    .csv-btn {
        float: right;
        background: var(--accent, #ff3f6c);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 6px 14px;
        font-size: .8rem;
        cursor: pointer;
    }

    .csv-btn:hover {
        opacity: .9;
    }

    .alert-row {
        background: #fff7ed;
        border-left: 3px solid #f97316;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: .82rem;
        color: #7c2d12;
        margin-bottom: 16px;
    }
</style>

<!-- Range picker -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:8px;">
        <?php foreach (['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 3 months', '365' => 'Last year'] as $v => $lbl): ?>
            <a href="?range=<?= $v ?>" class="range-btn <?= $range == $v ? 'active' : '' ?>"><?= h($lbl) ?></a>
        <?php endforeach; ?>
    </div>
    <a href="?range=<?= $range ?>&export=csv" class="csv-btn">⬇ Export CSV</a>
</div>

<?php if ($lowStock > 0): ?>
    <div class="alert-row">⚠️ <strong><?= $lowStock ?> product<?= $lowStock > 1 ? 's' : '' ?></strong> with low stock. <?= $outOfStock ?> out of stock.</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="analytics-kpi-grid">
    <div class="kpi-card">
        <i class="bi bi-currency-rupee kpi-icon"></i>
        <div class="kpi-label">Period Revenue</div>
        <div class="kpi-value"><?= CURRENCY . number_format($periodRevenue) ?></div>
        <div class="kpi-sub">Last <?= $range ?> days</div>
    </div>
    <div class="kpi-card">
        <i class="bi bi-bag-check kpi-icon"></i>
        <div class="kpi-label">Orders</div>
        <div class="kpi-value"><?= number_format($periodOrders) ?></div>
        <div class="kpi-sub">Last <?= $range ?> days</div>
    </div>
    <div class="kpi-card">
        <i class="bi bi-person-plus kpi-icon"></i>
        <div class="kpi-label">New Customers</div>
        <div class="kpi-value"><?= number_format($newCustomers) ?></div>
        <div class="kpi-sub">Registrations this period</div>
    </div>
    <div class="kpi-card">
        <i class="bi bi-box-seam kpi-icon"></i>
        <div class="kpi-label">Active Products</div>
        <div class="kpi-value"><?= number_format($totalProducts) ?></div>
        <div class="kpi-sub"><?= $lowStock ?> low · <?= $outOfStock ?> out of stock</div>
    </div>
    <div class="kpi-card">
        <i class="bi bi-graph-up kpi-icon"></i>
        <div class="kpi-label">Avg Order Value</div>
        <div class="kpi-value"><?= $periodOrders > 0 ? CURRENCY . number_format($periodRevenue / $periodOrders) : '—' ?></div>
        <div class="kpi-sub">Period average</div>
    </div>
    <div class="kpi-card">
        <i class="bi bi-bank kpi-icon"></i>
        <div class="kpi-label">All-time Revenue</div>
        <div class="kpi-value"><?= CURRENCY . number_format($totalRevenue) ?></div>
        <div class="kpi-sub">All orders (excl. cancelled)</div>
    </div>
</div>

<!-- Revenue Chart -->
<div class="chart-wrap">
    <div class="chart-title">Revenue & Orders — Last <?= $range ?> days</div>
    <canvas id="revenueChart" height="80"></canvas>
</div>

<div class="section-row">
    <!-- Top Products -->
    <div class="table-card">
        <h3>Top Products by Sales</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Units Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProducts as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= h($p['name']) ?></td>
                        <td><?= number_format((int)$p['sold']) ?></td>
                        <td><?= CURRENCY . number_format((float)$p['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topProducts)): ?>
                    <tr>
                        <td colspan="4" style="color:#9ca3af;text-align:center;padding:20px;">No data for this period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Customers -->
    <div class="table-card">
        <h3>Top Customers by Spend</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Orders</th>
                    <th>Spent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topCustomers as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <div><?= h($c['name']) ?></div>
                            <div style="font-size:.72rem;color:#9ca3af;"><?= h($c['email']) ?></div>
                        </td>
                        <td><?= (int)$c['orders'] ?></td>
                        <td><?= CURRENCY . number_format((float)$c['spent']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topCustomers)): ?>
                    <tr>
                        <td colspan="4" style="color:#9ca3af;text-align:center;padding:20px;">No data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="section-row">
    <!-- Order Status Breakdown -->
    <div class="table-card">
        <h3>Order Status Breakdown</h3>
        <canvas id="statusChart" height="120"></canvas>
    </div>
    <!-- Payment Methods -->
    <div class="table-card">
        <h3>Payment Methods</h3>
        <canvas id="pmChart" height="120"></canvas>
    </div>
</div>

<!-- Low Stock Table -->
<?php if ($lowStock > 0): ?>
    <?php
    $lowStockItems = db()->query(
        "SELECT name, sku, stock, low_stock_alert FROM products WHERE stock <= low_stock_alert AND is_active=1 ORDER BY stock ASC LIMIT 20"
    )->fetchAll();
    ?>
    <div class="table-card" style="margin-bottom:24px;">
        <h3>⚠️ Low Stock Alert</h3>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Alert At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockItems as $item): ?>
                    <tr>
                        <td><?= h($item['name']) ?></td>
                        <td><?= h($item['sku'] ?? '—') ?></td>
                        <td style="color:<?= $item['stock'] == 0 ? '#dc2626' : '#d97706' ?>;font-weight:700;"><?= (int)$item['stock'] ?></td>
                        <td><?= (int)$item['low_stock_alert'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    // Revenue chart
    (function() {
        var ctx = document.getElementById('revenueChart');
        if (!ctx) return;
        var days = <?= json_encode($chartDays) ?>;
        var revs = <?= json_encode(array_map('floatval', $chartRevs)) ?>;
        var ords = <?= json_encode(array_map('intval', $chartOrds)) ?>;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: days,
                datasets: [{
                        label: 'Revenue (₹)',
                        data: revs,
                        backgroundColor: 'rgba(255,63,108,0.18)',
                        borderColor: '#ff3f6c',
                        borderWidth: 2,
                        type: 'line',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: ords,
                        backgroundColor: 'rgba(59,130,246,0.15)',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ₹'
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    })();

    // Status pie chart
    (function() {
        var ctx = document.getElementById('statusChart');
        if (!ctx) return;
        var data = <?= json_encode($statusBreakdown) ?>;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(function(d) {
                    return d.status;
                }),
                datasets: [{
                    data: data.map(function(d) {
                        return parseInt(d.cnt);
                    }),
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#6b7280']
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    })();

    // Payment methods pie chart
    (function() {
        var ctx = document.getElementById('pmChart');
        if (!ctx) return;
        var data = <?= json_encode($pmBreakdown) ?>;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(function(d) {
                    return d.payment_method || 'Unknown';
                }),
                datasets: [{
                    data: data.map(function(d) {
                        return parseInt(d.cnt);
                    }),
                    backgroundColor: ['#6366f1', '#ec4899', '#14b8a6', '#f59e0b', '#84cc16']
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    })();
</script>

<?php
// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Orders', 'Revenue (₹)']);
    foreach ($revByDay as $row) {
        fputcsv($out, [$row['day'], $ordMap[$row['day']] ?? 0, number_format((float)$row['rev'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}
?>

<?php require __DIR__ . '/_layout_foot.php'; ?>