<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_auth.php';
requireAdminLogin();

$pageTitle = 'Dashboard';

// ── Stats ──────────────────────────────────────────────────────────────
try {
    $totalProducts = (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalOrders   = (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalCustomers= (int)db()->query("SELECT COUNT(*) FROM customers")->fetchColumn();

    $revRow = db()->query(
        "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status NOT IN ('cancelled', 'refunded')"
    )->fetchColumn();
    $totalRevenue = (float)$revRow;

    // Recent orders (last 10)
    $recentOrders = db()->query(
        "SELECT id, order_number, customer_name, total, status, payment_status, created_at
         FROM orders ORDER BY created_at DESC LIMIT 10"
    )->fetchAll();

    // Low stock products
    $lowStock = db()->query(
        "SELECT id, name, sku, stock, low_stock_alert FROM products
         WHERE stock <= low_stock_alert AND is_active = 1
         ORDER BY stock ASC LIMIT 10"
    )->fetchAll();

    // Recent activity log
    $activityLog = db()->query(
        "SELECT al.action, al.detail, al.ip, al.created_at, au.name AS admin_name
         FROM activity_log al
         LEFT JOIN admin_users au ON al.admin_id = au.id
         ORDER BY al.created_at DESC LIMIT 15"
    )->fetchAll();

    // Pending orders count
    $pendingOrders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

} catch (Throwable $e) {
    $totalProducts = $totalOrders = $totalCustomers = 0;
    $totalRevenue  = 0;
    $recentOrders  = $lowStock = $activityLog = [];
    $pendingOrders = 0;
}

include __DIR__ . '/_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-speedometer2 me-2" style="color:#f8c146"></i>Dashboard</h1>
    <span class="text-muted small"><?= date('l, d F Y') ?></span>
</div>

<!-- ── Stat cards ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9ec;">
                <i class="bi bi-bag-heart" style="color:#f8c146"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalProducts) ?></div>
                <div class="stat-label">Total Products</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff;">
                <i class="bi bi-cart-check" style="color:#3b82f6"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalOrders) ?></div>
                <div class="stat-label">
                    Total Orders
                    <?php if ($pendingOrders > 0): ?>
                    <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;"><?= $pendingOrders ?> pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4;">
                <i class="bi bi-people" style="color:#22c55e"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalCustomers) ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fdf4ff;">
                <i class="bi bi-currency-rupee" style="color:#a855f7"></i>
            </div>
            <div>
                <div class="stat-value"><?= CURRENCY ?><?= number_format($totalRevenue, 0) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent orders + Low stock ──────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Recent Orders -->
    <div class="col-lg-8">
        <div class="admin-card card-flush">
            <div class="d-flex align-items-center justify-content-between p-4 pb-3 card-header-bar">
                <h6 class="mb-0 fw-700">Recent Orders</h6>
                <a href="orders/index.php" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>
            <?php if (empty($recentOrders)): ?>
            <div class="p-4 text-muted text-center">No orders yet.</div>
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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><a href="orders/view.php?id=<?= (int)$order['id'] ?>" class="fw-600 text-decoration-none">#<?= h($order['order_number']) ?></a></td>
                        <td><?= h($order['customer_name']) ?></td>
                        <td><?= CURRENCY ?><?= number_format((float)$order['total'], 2) ?></td>
                        <td>
                            <?php $ps = $order['payment_status'] ?? 'unpaid'; ?>
                            <span class="badge-status badge-<?= h($ps) ?>"><?= h($ps) ?></span>
                        </td>
                        <td>
                            <span class="badge-status badge-<?= h($order['status']) ?>"><?= h($order['status']) ?></span>
                        </td>
                        <td class="text-muted" style="font-size:.8rem;"><?= date('d M', strtotime($order['created_at'])) ?></td>
                        <td>
                            <a href="orders/view.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:.75rem; padding: 2px 8px;">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="col-lg-4">
        <div class="admin-card card-flush">
            <div class="d-flex align-items-center justify-content-between p-4 pb-3 card-header-bar">
                <h6 class="mb-0 fw-700"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Low Stock</h6>
                <a href="products/index.php" class="btn btn-sm btn-outline-secondary">Manage</a>
            </div>
            <?php if (empty($lowStock)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-check-circle text-success d-block mb-2" style="font-size:1.5rem;"></i>
                All products are well-stocked.
            </div>
            <?php else: ?>
            <ul class="list-unstyled mb-0">
            <?php foreach ($lowStock as $p): ?>
                <li class="stock-item">
                    <div>
                        <div class="fw-500"><?= h($p['name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;">SKU: <?= h($p['sku'] ?? '—') ?></div>
                    </div>
                    <span class="badge <?= (int)$p['stock'] === 0 ? 'bg-danger' : 'bg-warning text-dark' ?>" style="font-size:.75rem;">
                        <?= (int)$p['stock'] ?> left
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Activity Log ─────────────────────────────────────────────── -->
<div class="admin-card card-flush">
    <div class="p-4 pb-3 card-header-bar">
        <h6 class="mb-0 fw-700"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
    </div>
    <?php if (empty($activityLog)): ?>
    <div class="p-4 text-muted text-center">No activity recorded yet.</div>
    <?php else: ?>
    <div style="padding: 20px 24px;">
        <div class="timeline">
        <?php foreach ($activityLog as $log): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-text">
                    <strong><?= h($log['admin_name'] ?? 'Admin') ?></strong>
                    — <?= h($log['action']) ?>
                    <?php if ($log['detail']): ?>
                    <span class="text-muted">: <?= h($log['detail']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="timeline-time"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?> &bull; <?= h($log['ip'] ?? '') ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Revenue Trend Chart ──────────────────────────────────────── -->
<div class="admin-card card-flush mb-4">
    <div class="d-flex align-items-center justify-content-between p-4 pb-3 card-header-bar">
        <h6 class="mb-0 fw-700"><i class="bi bi-graph-up me-2" style="color:#3b82f6"></i>Revenue — Last 30 Days</h6>
    </div>
    <div style="padding:16px 24px 20px;">
        <canvas id="revenueChart" height="80"></canvas>
    </div>
</div>

<?php
// Fetch last 30 days revenue per day
$revenueTrend = [];
try {
    $trendStmt = db()->query(
        "SELECT DATE(created_at) as day, COALESCE(SUM(total),0) as revenue
         FROM orders
         WHERE status NOT IN ('cancelled','refunded')
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    );
    $rows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
    // Fill missing days with 0
    $filled = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $filled[$d] = 0;
    }
    foreach ($rows as $row) { $filled[$row['day']] = (float)$row['revenue']; }
    $revenueTrend = $filled;
} catch (Throwable $e) {
    $revenueTrend = [];
}
$chartLabels = json_encode(array_keys($revenueTrend));
$chartData   = json_encode(array_values($revenueTrend));
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
(function(){
  var ctx = document.getElementById('revenueChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Revenue (₹)',
        data: <?= $chartData ?>,
        borderColor: '#ff3f6c',
        backgroundColor: 'rgba(255,63,108,0.08)',
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: '#ff3f6c',
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false }, tooltip: { callbacks: {
        label: function(c){ return '₹' + c.parsed.y.toLocaleString('en-IN'); }
      }}},
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 11 } } },
        y: { grid: { color: '#f3f4f6' }, ticks: {
          callback: function(v){ return '₹' + v.toLocaleString('en-IN'); },
          font: { size: 11 }
        }}
      }
    }
  });
})();
</script>

<?php include __DIR__ . '/_layout_foot.php'; ?>
