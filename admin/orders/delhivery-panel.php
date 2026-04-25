<?php

declare(strict_types=1);

/**
 * admin/orders/delhivery-panel.php
 *
 * Unified Delhivery management panel for a single order.
 * Usage: /admin/orders/delhivery-panel.php?order_id=123
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../api/shipping/delhivery.php';
require_once __DIR__ . '/../../includes/email.php';

requireAdminLogin();

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
  setFlash('danger', 'Invalid order ID.');
  header('Location: index.php');
  exit;
}

// ── Load order ────────────────────────────────────────────────────────────────
$order = db_row("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
if (!$order) {
  setFlash('danger', 'Order not found.');
  header('Location: index.php');
  exit;
}

$waybill       = $order['tracking_number'] ?? '';
$returnWaybill = $order['return_waybill']  ?? '';
$dlv           = new Delhivery();

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  switch ($action) {

    case 'create_shipment': {
        $shipping = [];
        if (!empty($order['shipping_address'])) {
          $dec = json_decode($order['shipping_address'], true);
          $shipping = is_array($dec) ? $dec : ['address' => $order['shipping_address']];
        }

        $items = db_rows("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);

        $orderData = [
          'order_number'     => $order['order_number'] ?? '',
          'customer_name'    => $order['customer_name'] ?? '',
          'customer_phone'   => $order['customer_phone'] ?? '',
          'customer_email'   => $order['customer_email'] ?? '',
          'shipping_address' => $shipping,
          'total'            => (float)$order['total'],
          'payment_method'   => $order['payment_method'] ?? 'online',
          'items'            => array_map(fn($i) => [
            'product_name' => $i['product_name'],
            'qty'          => $i['qty'],
            'price'        => $i['unit_price'] ?? $i['price'] ?? 0,
          ], $items),
          'weight'           => (int)($_POST['weight'] ?? 500),
          'shipping_mode'    => $_POST['shipping_mode'] ?? 'Surface',
        ];

        $res = $dlv->createShipment($orderData);

        if ($res['success']) {
          db()->prepare(
            "UPDATE orders SET tracking_number=?, courier='Delhivery', status='shipped' WHERE id=?"
          )->execute([$res['waybill'], $orderId]);

          db()->prepare(
            "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
                     VALUES (?, 'shipped', ?, ?, NOW())"
          )->execute([$orderId, 'Delhivery shipment created. AWB: ' . $res['waybill'], getAdminId()]);

          $order['tracking_number'] = $res['waybill'];
          $order['courier'] = 'Delhivery';
          $mailer = new EmailHandler();
          if ($mailer->customerNotifyEnabled() && !empty($order['customer_email'])) {
            $mailer->sendOrderStatusUpdate($order['customer_email'], $order, 'shipped');
          }

          logAdminActivity('delhivery_create_shipment', 'Order #' . $order['order_number'] . ' AWB: ' . $res['waybill']);
          setFlash('success', 'Shipment created. AWB: ' . $res['waybill']);
          $waybill = $res['waybill'];
        } else {
          setFlash('danger', 'Shipment creation failed: ' . ($res['error'] ?? 'Unknown error.'));
        }
        break;
      }

    case 'cancel_shipment': {
        $awb = $waybill ?: trim($_POST['waybill'] ?? '');
        if (empty($awb)) {
          setFlash('danger', 'No waybill to cancel.');
          break;
        }
        $res = $dlv->cancelShipment($awb);
        if ($res['success']) {
          // FIX: also clear tracking_number and courier so the order does not
          // remain in a "shipped with no waybill" state after cancellation.
          db()->prepare(
            "UPDATE orders
                SET status          = 'cancelled',
                    tracking_number = NULL,
                    courier         = NULL,
                    cancelled_at    = NOW()
              WHERE id = ?"
          )->execute([$orderId]);

          db()->prepare(
            "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
                     VALUES (?, 'cancelled', ?, ?, NOW())"
          )->execute([$orderId, 'Delhivery shipment cancelled. Waybill was: ' . $awb, getAdminId()]);

          $mailer = new EmailHandler();
          if ($mailer->customerNotifyEnabled() && !empty($order['customer_email'])) {
            $mailer->sendOrderStatusUpdate($order['customer_email'], $order, 'cancelled');
          }

          logAdminActivity('delhivery_cancel_shipment', 'Order #' . $order['order_number'] . ' AWB: ' . $awb);
          setFlash('success', 'Shipment cancelled.');
          $waybill = ''; // clear local var so UI reflects no waybill
        } else {
          setFlash('danger', 'Cancellation failed: ' . ($res['error'] ?? 'Unknown error.'));
        }
        break;
      }

    case 'create_pickup': {
        $res = $dlv->createPickupRequest([
          'expected_package_count' => max(1, (int)($_POST['package_count'] ?? 1)),
          'pickup_date'            => $_POST['pickup_date'] ?? date('Y-m-d'),
        ]);
        if ($res['success']) {
          logAdminActivity('delhivery_pickup', 'Pickup ID: ' . ($res['pickup_id'] ?? ''));
          setFlash('success', 'Pickup request created. ID: ' . ($res['pickup_id'] ?? 'N/A'));
        } else {
          setFlash('danger', 'Pickup request failed: ' . ($res['error'] ?? 'Unknown error.'));
        }
        break;
      }

    case 'ndr_action': {
        $awb    = $waybill ?: trim($_POST['waybill'] ?? '');
        $ndrAct = trim($_POST['ndr_action'] ?? '');
        if (empty($awb) || empty($ndrAct)) {
          setFlash('danger', 'Waybill and NDR action are required.');
          break;
        }
        $res = $dlv->ndrAction($awb, $ndrAct, [
          'remarks' => trim($_POST['ndr_remarks'] ?? ''),
        ]);
        if ($res['success']) {
          db()->prepare(
            "INSERT IGNORE INTO delhivery_ndr (waybill, order_id, action, remarks, acted_at)
                     VALUES (?, ?, ?, ?, NOW())"
          )->execute([$awb, $orderId, $ndrAct, trim($_POST['ndr_remarks'] ?? '')]);
          logAdminActivity('delhivery_ndr', 'AWB: ' . $awb . ' Action: ' . $ndrAct);
          setFlash('success', 'NDR action submitted.');
        } else {
          setFlash('danger', 'NDR action failed: ' . ($res['error'] ?? 'Unknown error.'));
        }
        break;
      }

    case 'create_rvp': {
        $shipping = [];
        if (!empty($order['shipping_address'])) {
          $dec = json_decode($order['shipping_address'], true);
          $shipping = is_array($dec) ? $dec : ['address' => $order['shipping_address']];
        }

        $items = db_rows("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);

        $orderData = [
          'order_number'     => $order['order_number'] . '-RVP',
          'customer_name'    => $order['customer_name']  ?? '',
          'customer_phone'   => $order['customer_phone'] ?? '',
          'customer_email'   => $order['customer_email'] ?? '',
          'shipping_address' => $shipping,
          'total'            => (float)$order['total'],
          'payment_method'   => 'online',
          'items'            => array_map(fn($i) => [
            'product_name' => $i['product_name'],
            'qty'          => $i['qty'],
            'price'        => $i['unit_price'] ?? $i['price'] ?? 0,
          ], $items),
          'weight'           => (int)($_POST['weight'] ?? 500),
        ];

        $res = $dlv->createRvpShipment($orderData);
        if ($res['success']) {
          db()->prepare(
            "UPDATE orders SET return_waybill=? WHERE id=?"
          )->execute([$res['waybill'], $orderId]);
          logAdminActivity('delhivery_rvp', 'Order #' . $order['order_number'] . ' Return AWB: ' . $res['waybill']);
          setFlash('success', 'Return shipment created. AWB: ' . $res['waybill']);
          $returnWaybill = $res['waybill'];
        } else {
          setFlash('danger', 'Return shipment failed: ' . ($res['error'] ?? 'Unknown error.'));
        }
        break;
      }

    case 'update_ewaybill': {
        $awb        = $waybill ?: trim($_POST['waybill'] ?? '');
        $ewaybillNo = trim($_POST['ewaybill_no'] ?? '');
        $expiry     = trim($_POST['ewaybill_expiry'] ?? '');
        if (empty($awb) || empty($ewaybillNo)) {
          setFlash('danger', 'Waybill and e-waybill number are required.');
          break;
        }
        $res = $dlv->updateEwaybill($awb, $ewaybillNo, $expiry);
        if ($res['success']) {
          logAdminActivity('delhivery_ewaybill', 'AWB: ' . $awb . ' E-waybill: ' . $ewaybillNo);
          setFlash('success', 'E-waybill updated.');
        } else {
          setFlash('danger', 'E-waybill update failed: ' . ($res['error'] ?? 'Unknown error.'));
        }
        break;
      }
  }

  header('Location: delhivery-panel.php?order_id=' . $orderId);
  exit;
}

// ── Live tracking ─────────────────────────────────────────────────────────────
$tracking     = null;
$serviceCheck = null;

if (!empty($waybill)) {
  $tracking = $dlv->trackShipment($waybill);
}

// ── Serviceability check for destination pincode ──────────────────────────────
$shipping = [];
if (!empty($order['shipping_address'])) {
  $dec = json_decode($order['shipping_address'], true);
  $shipping = is_array($dec) ? $dec : [];
}
$destPin = $shipping['pincode'] ?? $order['shipping_pincode'] ?? '';
if (!empty($destPin)) {
  $serviceCheck = $dlv->checkServiceability($destPin);
}

$pageTitle = 'Delhivery — Order #' . ($order['order_number'] ?? '');
$csrfToken = csrf_token();

include __DIR__ . '/../_layout_head.php';
?>
<style>
  .panel-section {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
  }

  .panel-section h3 {
    margin-top: 0;
    font-size: 1rem;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: .5rem;
  }

  .timeline {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .timeline li {
    padding: .5rem 0 .5rem 1.5rem;
    border-left: 2px solid #dee2e6;
    position: relative;
    font-size: .875rem;
  }

  .timeline li::before {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0d6efd;
    position: absolute;
    left: -6px;
    top: .65rem;
  }

  .timeline li:last-child {
    border-left-color: transparent;
  }

  .badge-ok {
    background: #198754;
    color: #fff;
    padding: .2em .5em;
    border-radius: 4px;
    font-size: .75em;
  }

  .badge-err {
    background: #dc3545;
    color: #fff;
    padding: .2em .5em;
    border-radius: 4px;
    font-size: .75em;
  }

  .badge-warn {
    background: #ffc107;
    color: #212529;
    padding: .2em .5em;
    border-radius: 4px;
    font-size: .75em;
  }

  .action-form {
    display: inline;
  }

  .meta-table td:first-child {
    font-weight: 600;
    white-space: nowrap;
    padding-right: 1rem;
    color: #555;
  }

  .meta-table td {
    padding: .3rem .5rem;
    vertical-align: top;
  }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
  <h1 style="margin:0;font-size:1.4rem">
    Delhivery Panel &mdash; Order #<?= h($order['order_number'] ?? '') ?>
  </h1>
  <a href="view.php?id=<?= $orderId ?>" class="btn btn-sm btn-secondary">&larr; Back to Order</a>
</div>

<!-- ── Order Summary ────────────────────────────────────────────────────── -->
<div class="panel-section">
  <h3>Order Summary</h3>
  <table class="meta-table">
    <tr>
      <td>Order #</td>
      <td><?= h($order['order_number'] ?? '') ?></td>
    </tr>
    <tr>
      <td>Customer</td>
      <td><?= h($order['customer_name'] ?? '') ?> &mdash; <?= h($order['customer_phone'] ?? '') ?></td>
    </tr>
    <tr>
      <td>Status</td>
      <td><span class="badge badge-warn"><?= h($order['status'] ?? '') ?></span></td>
    </tr>
    <tr>
      <td>Payment</td>
      <td><?= h(strtoupper($order['payment_method'] ?? '')) ?></td>
    </tr>
    <tr>
      <td>Total</td>
      <td>&#8377;<?= h(number_format((float)$order['total'], 2)) ?></td>
    </tr>
    <tr>
      <td>Ship To</td>
      <td>
        <?php
        $addrParts = array_filter([
          $shipping['address'] ?? '',
          $shipping['city']    ?? '',
          $shipping['state']   ?? '',
          $destPin,
        ]);
        echo h(implode(', ', $addrParts));
        ?>
      </td>
    </tr>
    <?php if (!empty($waybill)): ?>
      <tr>
        <td>Forward AWB</td>
        <td><code><?= h($waybill) ?></code></td>
      </tr>
    <?php endif; ?>
    <?php if (!empty($returnWaybill)): ?>
      <tr>
        <td>Return AWB</td>
        <td><code><?= h($returnWaybill) ?></code></td>
      </tr>
    <?php endif; ?>
  </table>
</div>

<!-- ── Serviceability ───────────────────────────────────────────────────── -->
<?php if ($serviceCheck !== null): ?>
  <div class="panel-section">
    <h3>Destination Serviceability (<?= h($destPin) ?>)</h3>
    <?php
    $sInfo = $serviceCheck['results'][$destPin] ?? null;
    if ($sInfo):
    ?>
      <?php if ($sInfo['serviceable']): ?>
        <span class="badge-ok">&#10003; Serviceable</span>
        <?php if (!empty($sInfo['city']) || !empty($sInfo['state'])): ?>
          &mdash; <?= h($sInfo['city'] ?? '') ?><?= (!empty($sInfo['city']) && !empty($sInfo['state'])) ? ', ' : '' ?><?= h($sInfo['state'] ?? '') ?>
        <?php endif; ?>
        &nbsp;
        <span class="<?= $sInfo['cod'] ? 'badge-ok' : 'badge-err' ?>">COD: <?= $sInfo['cod'] ? 'Yes' : 'No' ?></span>
        <span class="<?= $sInfo['prepaid'] ? 'badge-ok' : 'badge-warn' ?>">Prepaid: <?= $sInfo['prepaid'] ? 'Yes' : 'No' ?></span>
      <?php else: ?>
        <span class="badge-err">&#10007; Not Serviceable</span>
      <?php endif; ?>
    <?php else: ?>
      <span class="badge-warn">Could not fetch serviceability info.</span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- ── Live Tracking ────────────────────────────────────────────────────── -->
<?php if (!empty($waybill)): ?>
  <div class="panel-section">
    <h3>Live Tracking — <?= h($waybill) ?></h3>
    <?php if ($tracking && $tracking['success']): ?>
      <p>
        <strong>Status:</strong> <?= h($tracking['status']) ?>
        <?php if (!empty($tracking['location'])): ?>
          &mdash; <?= h($tracking['location']) ?>
        <?php endif; ?>
        <?php if (!empty($tracking['expected_delivery'])): ?>
          &nbsp; | <strong>EDD:</strong> <?= h($tracking['expected_delivery']) ?>
        <?php endif; ?>
      </p>
      <?php if (!empty($tracking['events'])): ?>
        <ul class="timeline">
          <?php foreach (array_reverse($tracking['events']) as $ev): ?>
            <li>
              <strong><?= h($ev['timestamp'] ?? '') ?></strong>
              &mdash; <?= h($ev['status'] ?? '') ?>
              <?php if (!empty($ev['detail'])): ?> — <?= h($ev['detail']) ?><?php endif; ?>
                <?php if (!empty($ev['location'])): ?>
                  <em style="color:#777">(<?= h($ev['location']) ?>)</em>
                <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="color:#777">No scan events yet.</p>
      <?php endif; ?>
      <a href="<?= h($tracking['tracking_url'] ?? '') ?>" target="_blank" rel="noopener"
        class="btn btn-sm btn-outline-primary" style="margin-top:.75rem">
        Track on Delhivery &rarr;
      </a>
    <?php else: ?>
      <p class="text-muted">Tracking not available yet.
        <?= h($tracking['error'] ?? '') ?>
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- ── Quick Actions ────────────────────────────────────────────────────── -->
<div class="panel-section">
  <h3>Quick Actions</h3>
  <div style="display:flex;flex-wrap:wrap;gap:.5rem">

    <?php if (!empty($waybill)): ?>
      <!-- Download Label -->
      <a href="/api/shipping/label-proxy.php?waybills=<?= urlencode($waybill) ?>&csrf_token=<?= urlencode($csrfToken) ?>"
        class="btn btn-sm btn-primary">&#128196; Download Shipping Label</a>
    <?php endif; ?>

    <?php if (empty($waybill)): ?>
      <!-- Create Shipment form (inline quick) -->
      <button type="button" class="btn btn-sm btn-success"
        onclick="document.getElementById('form-create').style.display='block';this.style.display='none'">
        + Create Shipment
      </button>
    <?php else: ?>
      <!-- Cancel -->
      <form method="post" class="action-form"
        onsubmit="return confirm('Cancel shipment <?= h($waybill) ?>?')">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="cancel_shipment">
        <input type="hidden" name="waybill" value="<?= h($waybill) ?>">
        <button class="btn btn-sm btn-danger">&#10007; Cancel Shipment</button>
      </form>
    <?php endif; ?>

    <!-- Pickup Request -->
    <button type="button" class="btn btn-sm btn-secondary"
      onclick="document.getElementById('form-pickup').style.display='block';this.style.display='none'">
      &#128666; Schedule Pickup
    </button>

    <?php if (!empty($waybill)): ?>
      <!-- NDR Action -->
      <button type="button" class="btn btn-sm btn-warning"
        onclick="document.getElementById('form-ndr').style.display='block';this.style.display='none'">
        &#128196; NDR Action
      </button>

      <!-- Return Shipment -->
      <button type="button" class="btn btn-sm btn-outline-secondary"
        onclick="document.getElementById('form-rvp').style.display='block';this.style.display='none'">
        &#8635; Create Return Shipment
      </button>

      <!-- E-waybill -->
      <button type="button" class="btn btn-sm btn-outline-secondary"
        onclick="document.getElementById('form-ewaybill').style.display='block';this.style.display='none'">
        &#128196; Update E-waybill
      </button>
    <?php endif; ?>

  </div>

  <!-- Create Shipment -->
  <form id="form-create" method="post" style="display:none;margin-top:1rem;border-top:1px solid #eee;padding-top:1rem">
    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
    <input type="hidden" name="action" value="create_shipment">
    <h4 style="margin-top:0">Create Shipment</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;max-width:480px">
      <label>Weight (grams)
        <input type="number" name="weight" value="500" min="1" class="form-control form-control-sm">
      </label>
      <label>Mode
        <select name="shipping_mode" class="form-select form-select-sm">
          <option value="Surface">Surface</option>
          <option value="Express">Express</option>
        </select>
      </label>
    </div>
    <button class="btn btn-sm btn-success" style="margin-top:.75rem">Create</button>
  </form>

  <!-- Pickup Request -->
  <form id="form-pickup" method="post" style="display:none;margin-top:1rem;border-top:1px solid #eee;padding-top:1rem">
    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
    <input type="hidden" name="action" value="create_pickup">
    <h4 style="margin-top:0">Schedule Pickup</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;max-width:480px">
      <label>Pickup Date
        <input type="date" name="pickup_date" value="<?= h(date('Y-m-d')) ?>" class="form-control form-control-sm">
      </label>
      <label>Package Count
        <input type="number" name="package_count" value="1" min="1" class="form-control form-control-sm">
      </label>
    </div>
    <button class="btn btn-sm btn-secondary" style="margin-top:.75rem">Schedule</button>
  </form>

  <!-- NDR Action -->
  <?php if (!empty($waybill)): ?>
    <form id="form-ndr" method="post" style="display:none;margin-top:1rem;border-top:1px solid #eee;padding-top:1rem">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <input type="hidden" name="action" value="ndr_action">
      <input type="hidden" name="waybill" value="<?= h($waybill) ?>">
      <h4 style="margin-top:0">NDR Action</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;max-width:600px">
        <label>Action
          <select name="ndr_action" class="form-select form-select-sm">
            <option value="re-attempt">Re-attempt Delivery</option>
            <option value="rto">Return to Origin</option>
          </select>
        </label>
        <label>Remarks
          <input type="text" name="ndr_remarks" placeholder="Optional" class="form-control form-control-sm">
        </label>
      </div>
      <button class="btn btn-sm btn-warning" style="margin-top:.75rem">Submit NDR</button>
    </form>

    <!-- Return Shipment -->
    <form id="form-rvp" method="post" style="display:none;margin-top:1rem;border-top:1px solid #eee;padding-top:1rem">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <input type="hidden" name="action" value="create_rvp">
      <h4 style="margin-top:0">Create Return Shipment</h4>
      <div style="max-width:240px">
        <label>Weight (grams)
          <input type="number" name="weight" value="500" min="1" class="form-control form-control-sm">
        </label>
      </div>
      <button class="btn btn-sm btn-outline-secondary" style="margin-top:.75rem">Create Return</button>
    </form>

    <!-- E-waybill -->
    <form id="form-ewaybill" method="post" style="display:none;margin-top:1rem;border-top:1px solid #eee;padding-top:1rem">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <input type="hidden" name="action" value="update_ewaybill">
      <input type="hidden" name="waybill" value="<?= h($waybill) ?>">
      <h4 style="margin-top:0">Update E-waybill</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;max-width:480px">
        <label>E-waybill Number
          <input type="text" name="ewaybill_no" class="form-control form-control-sm" required>
        </label>
        <label>Expiry Date
          <input type="date" name="ewaybill_expiry" class="form-control form-control-sm">
        </label>
      </div>
      <button class="btn btn-sm btn-outline-secondary" style="margin-top:.75rem">Update</button>
    </form>
  <?php endif; ?>

</div><!-- /.panel-section quick actions -->

<?php include __DIR__ . '/../_layout_foot.php'; ?>