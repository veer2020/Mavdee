<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';
if (file_exists(__DIR__ . '/includes/email.php')) require_once __DIR__ . '/includes/email.php';
if (file_exists(__DIR__ . '/includes/cache.php')) require_once __DIR__ . '/includes/cache.php';

// ── Guest checkout gateway ────────────────────────────────────────────────────
// Allow non-logged-in users to checkout as guest.
// If not logged in AND no guest session → show the "choose" page.
$isGuestCheckout = !isLoggedIn() && !empty($_SESSION['guest_checkout']);

if (!isLoggedIn() && !$isGuestCheckout) {
  // Handle the "continue as guest" form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_email'])) {
    $guestEmail = trim(filter_var($_POST['guest_email'] ?? '', FILTER_SANITIZE_EMAIL));
    $guestName  = sanitizeInput($_POST['guest_name'] ?? '');
    if ($guestEmail && $guestName) {
      $_SESSION['guest_checkout']       = true;
      $_SESSION['guest_checkout_email'] = $guestEmail;
      $_SESSION['guest_checkout_name']  = $guestName;
      header('Location: checkout.php');
      exit();
    }
  }
}
// ── For guest, use session-stored guest info; for logged-in users, read from DB ──
if ($isGuestCheckout) {
  $userId = 0;
  $cartItems = [];

  // Guest cart is stored in session
  $guestCart = $_SESSION['guest_cart'] ?? [];
  if (!empty($guestCart)) {
    $placeholders = implode(',', array_fill(0, count($guestCart), '?'));
    $productIds   = array_column($guestCart, 'product_id');

    $pCols = db_columns('products');
    $salePriceCol = in_array('sale_price', $pCols) ? 'sale_price' : 'NULL as sale_price';
    $stockCol = in_array('stock', $pCols) ? 'stock' : '100 as stock';
    $isActiveCond = in_array('is_active', $pCols) ? 'AND is_active=1' : '';

    $pStmt = db()->prepare(
      "SELECT id AS product_id, name, price, $salePriceCol, $stockCol, image_url FROM products WHERE id IN ($placeholders) $isActiveCond"
    );
    $pStmt->execute($productIds);
    $productMap = [];
    foreach ($pStmt->fetchAll() as $p) {
      $productMap[$p['product_id']] = $p;
    }

    foreach ($guestCart as $item) {
      $pid = (int)($item['product_id'] ?? 0);
      if (isset($productMap[$pid])) {
        $cartItems[] = array_merge($productMap[$pid], [
          'qty'   => (int)($item['qty']   ?? 1),
          'size'  => $item['size']  ?? '',
          'color' => $item['color'] ?? '',
        ]);
      }
    }
  }

  // If guest cart is empty, fall back to reading the regular session cart (in case
  // they added items while not logged in via the normal cart flow)
  if (empty($cartItems)) {
    // No items — redirect to shop
    header('Location: shop.php');
    exit;
  }

  $qtyCol  = 'qty';
  $userCol = 'customer_id'; // not used for guest

} else {
  $userId = getUserId();
  $cols = cart_schema_columns();
  $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
  $qtyCol = in_array('qty', $cols) ? 'qty' : 'quantity';

  $pCols = db_columns('products');
  $salePriceCol = in_array('sale_price', $pCols) ? 'p.sale_price' : 'NULL as sale_price';
  $stockCol = in_array('stock', $pCols) ? 'p.stock' : '100 as stock';

  $stmt = db()->prepare("SELECT c.*, p.name, p.price, $salePriceCol, $stockCol, p.image_url FROM cart c JOIN products p ON c.product_id = p.id WHERE c.$userCol = ?");
  $stmt->execute([$userId]);
  $cartItems = $stmt->fetchAll();

  // Prefill from customer info
  $customerStmt = db()->prepare("SELECT name, email FROM customers WHERE id = ? LIMIT 1");
  $customerStmt->execute([$userId]);
  $customerInfo = $customerStmt->fetch() ?: [];

  // Retrieve saved addresses for this user
  $savedAddresses = [];
  try {
    $addrStmt = db()->prepare(
      "SELECT id, label, name, phone, address, city, state, pincode, is_default
             FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC"
    );
    $addrStmt->execute([$userId]);
    $savedAddresses = $addrStmt->fetchAll();
  } catch (Throwable) {
    $savedAddresses = [];
  }
}

if (empty($cartItems)) {
  header('Location: shop.php');
  exit;
}

$subtotal = 0;
foreach ($cartItems as $item) {
  $unitPrice = (float)($item['sale_price'] ?: $item['price']);
  $subtotal += $unitPrice * $item[$qtyCol];
}
$freeShippingAbove = (float)(getSetting('free_shipping_above', 999) ?: 999);
$stdShippingCost   = (float)(getSetting('shipping_cost', 99) ?: 99);
$shipping = $subtotal >= $freeShippingAbove ? 0 : $stdShippingCost;
$total = $subtotal + $shipping;

// Retrieve payment settings
$rzp       = getPaymentSettings('razorpay');
$codCfg    = getPaymentSettings('cod');
$rzpKey    = $rzp['key_id'] ?? '';
$rzpEnabled = !empty($rzp['enabled']) && !empty($rzpKey);
$codEnabled = !empty($codCfg['enabled']);
$codFee    = (float)($codCfg['fee'] ?? 0);

// $customerInfo is already set above (either from DB or guest session)

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $state = trim($_POST['state'] ?? '');
  $pincode = trim($_POST['pincode'] ?? '');
  $payment_method  = $_POST['payment_method'] ?? 'COD';
  $rzp_payment_id  = $_POST['razorpay_payment_id']  ?? null;
  $rzp_order_id    = $_POST['razorpay_order_id']    ?? null;
  $rzp_signature   = $_POST['razorpay_signature']   ?? null;

  if (!$name || !$phone || !$address || !$city || !$pincode) {
    $error = 'Please fill in all required shipping details.';
  }

  // Server-side Razorpay signature verification
  if (!$error && $payment_method === 'Razorpay') {
    require_once __DIR__ . '/includes/payment.php';
    if (!$rzp_payment_id || !$rzp_order_id || !$rzp_signature) {
      $error = 'Payment information is incomplete. Please try again.';
    } elseif (!PaymentVerifier::verifyRazorpaySignature(
      (string)$rzp_order_id,
      (string)$rzp_payment_id,
      (string)$rzp_signature,
      $rzp['key_secret'] ?? ''
    )) {
      $error = 'Payment verification failed. Please contact support.';
    }
  }

  if (!$error) {
    // Validate stock availability before placing order
    foreach ($cartItems as $item) {
      $requestedQty = (int)$item[$qtyCol];
      $availableStock = (int)$item['stock'];
      if ($requestedQty > $availableStock) {
        $error = 'One or more items in your cart are out of stock. Please update your cart and try again.';
        break;
      }
    }
  }

  // Server-side coupon validation and discount calculation
  $couponDiscount = 0.0;
  $appliedCoupon  = null;
  if (!$error) {
    $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    if ($couponCode !== '') {
      try {
        $cpStmt = db()->prepare(
          "SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1"
        );
        $cpStmt->execute([$couponCode]);
        $coupon = $cpStmt->fetch();

        if (!$coupon) {
          $error = 'The coupon code you entered is invalid or has expired.';
        } elseif (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) {
          $error = 'This coupon has expired.';
        } elseif ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
          $error = 'This coupon has reached its usage limit.';
        } elseif ($coupon['min_order'] > 0 && $subtotal < (float)$coupon['min_order']) {
          $error = 'Minimum order of ' . CURRENCY . number_format($coupon['min_order'], 0) . ' required for this coupon.';
        } else {
          if ($coupon['type'] === 'percent') {
            $couponDiscount = round($subtotal * ((float)$coupon['value'] / 100), 2);
            if ($coupon['max_discount'] > 0 && $couponDiscount > (float)$coupon['max_discount']) {
              $couponDiscount = (float)$coupon['max_discount'];
            }
          } else {
            $couponDiscount = min((float)$coupon['value'], $subtotal);
          }
          $appliedCoupon = $couponCode;
        }
      } catch (Throwable) {
        // Coupon validation DB error is non-fatal — proceed without discount
      }
    }
  }

  // Recalculate total with coupon discount applied server-side
  $totalWithDiscount = max(0.0, round($total - $couponDiscount, 2));

  if (!$error) {
    try {
      db()->beginTransaction();

      $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(8)));
      $payStatus = ($payment_method === 'Razorpay' && $rzp_payment_id) ? 'paid' : 'pending';

      // Create Main Order (customer_id = NULL for guests)
      $shippingAddressJson = json_encode([
        'address' => $address,
        'city'    => $city,
        'state'   => $state,
        'pincode' => $pincode,
      ]);
      $orderCols = db_columns('orders');
      $hasSubtotal = in_array('subtotal', $orderCols);
      $hasShippingAmt = in_array('shipping_amount', $orderCols);
      $hasDiscountAmt = in_array('discount_amount', $orderCols);
      $hasCouponCode  = in_array('coupon_code', $orderCols);

      $insertCols = 'order_number, customer_id, is_guest, customer_name, customer_email, customer_phone, shipping_address, city, state, pincode, total, payment_method, status, payment_status';
      $insertVals = [$orderNumber, $userId > 0 ? $userId : null, $isGuestCheckout ? 1 : 0, $name, $email, $phone, $shippingAddressJson, $city, $state, $pincode, $totalWithDiscount, $payment_method, 'pending', $payStatus];

      if ($hasSubtotal) {
        $insertCols .= ', subtotal';
        $insertVals[] = round($subtotal, 2);
      }
      if ($hasShippingAmt) {
        $insertCols .= ', shipping_amount';
        $insertVals[] = round($shipping, 2);
      }
      if ($hasDiscountAmt) {
        $insertCols .= ', discount_amount';
        $insertVals[] = round($couponDiscount, 2);
      }
      if ($hasCouponCode && $appliedCoupon !== null) {
        $insertCols .= ', coupon_code';
        $insertVals[] = $appliedCoupon;
      }

      $insertPlaceholders = implode(',', array_fill(0, count($insertVals), '?'));
      $stmt = db()->prepare("INSERT INTO orders ($insertCols) VALUES ($insertPlaceholders)");
      $stmt->execute($insertVals);
      $orderId = db()->lastInsertId();

      $oiCols = db_columns('order_items');

      $oiInsertCols = ['order_id', 'product_id', 'product_name', 'qty', 'unit_price', 'total_price'];
      if (in_array('size', $oiCols)) $oiInsertCols[] = 'size';
      if (in_array('color', $oiCols)) $oiInsertCols[] = 'color';

      $phStr = implode(',', array_fill(0, count($oiInsertCols), '?'));
      $oiColStr = implode(',', $oiInsertCols);

      $itemStmt = db()->prepare("INSERT INTO order_items ($oiColStr) VALUES ($phStr)");

      foreach ($cartItems as $item) {
        $unitPrice = (float)($item['sale_price'] ?: $item['price']);
        $itemTotal = $unitPrice * $item[$qtyCol];
        $vals = [$orderId, $item['product_id'], $item['name'], $item[$qtyCol], $unitPrice, $itemTotal];
        if (in_array('size', $oiCols)) $vals[] = $item['size'] ?? '';
        if (in_array('color', $oiCols)) $vals[] = $item['color'] ?? '';
        $itemStmt->execute($vals);
      }

      // Decrement stock for each item (enforced: stock cannot go below 0)
      if (in_array('stock', db_columns('products'))) {
        // The quantity is bound twice: once for the SET clause (decrement) and once for the WHERE clause (guard)
        $stmtStock = db()->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        foreach ($cartItems as $item) {
          $stmtStock->execute([$item[$qtyCol], $item['product_id'], $item[$qtyCol]]);
          if ($stmtStock->rowCount() === 0) {
            throw new RuntimeException("Insufficient stock for product ID {$item['product_id']}");
          }
        }
      }

      // Increment coupon usage count if a valid coupon was applied server-side
      if ($appliedCoupon !== null) {
        db()->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?")
          ->execute([$appliedCoupon]);
      }

      // Clear Cart (for logged-in users only)
      if ($userId > 0) {
        db()->prepare("DELETE FROM cart WHERE $userCol = ?")->execute([$userId]);
      } else {
        // Clear guest cart from session
        unset($_SESSION['guest_cart']);
      }

      // Save the address for future use (if "save_address" checked or first order)
      if ($userId > 0 && (!empty($_POST['save_address']) || empty($savedAddresses))) {
        try {
          // Set all existing as non-default if this is being set as default
          db()->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")
            ->execute([$userId]);
          db()->prepare(
            "INSERT INTO customer_addresses (customer_id, label, name, phone, address, city, state, pincode, is_default)
                         VALUES (?, 'Home', ?, ?, ?, ?, ?, ?, 1)"
          )->execute([$userId, $name, $phone, $address, $city, $state, $pincode]);
        } catch (Throwable) {
          // Non-fatal: address saving failed but order placed
        }
      }

      db()->commit();

      // Send Automated Invoice Email
      try {
        if (class_exists('EmailHandler')) {
          $emailHandler = new EmailHandler();
          $emailHandler->sendOrderInvoice($email, [
            'id'    => $orderNumber,
            'total' => $totalWithDiscount,
          ], $cartItems);
        }
      } catch (Throwable $e) {
        // Ignore email errors to prevent order abort
      }

      // Auto-redirect to the thank-you / order confirmation screen
      // Clear guest checkout session after successful order
      if ($isGuestCheckout) {
        unset($_SESSION['guest_checkout'], $_SESSION['guest_checkout_email'], $_SESSION['guest_checkout_name']);
      }
      header("Location: thankyou.php?order=" . urlencode($orderNumber) . ($isGuestCheckout ? '&guest=1' : ''));
      exit;
    } catch (Exception $e) {
      if (db()->inTransaction()) {
        db()->rollBack();
      }
      $error = str_starts_with($e->getMessage(), 'Insufficient stock')
        ? 'One or more items in your cart are out of stock. Please update your cart and try again.'
        : 'Failed to place order. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/includes/head-favicon.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Review Order - <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/global.css">
  <style>
    /* ── Mavdee Checkout Page Styles ─────────────────────────── */
    :root {
      --mavdee-pink: #ff3f6c;
      --mavdee-pink-light: #fff0f3;
      --mavdee-green: #03a685;
      --mavdee-dark: #1c1c1c;
      --mavdee-grey: #f4f4f5;
      --mavdee-border: #e5e7eb;
      --mavdee-muted: #6b7280;
      --mavdee-text: #111827;
      --white: #ffffff;
      --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
      --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
      --font-sans: 'DM Sans', sans-serif;

      /* Alias uppercase variants → correct tokens (fixes broken var() refs) */
      --Mavdee-pink: var(--mavdee-pink);
      --Mavdee-pink-light: var(--mavdee-pink-light);
      --Mavdee-green: var(--mavdee-green);
      --Mavdee-dark: var(--mavdee-dark);
      --Mavdee-grey: var(--mavdee-grey);
      --Mavdee-border: var(--mavdee-border);
      --Mavdee-muted: var(--mavdee-muted);
      --Mavdee-text: var(--mavdee-text);
    }

    .co-card {
      box-shadow: var(--shadow-sm);
    }

    .co-card:hover {
      box-shadow: var(--shadow-md);
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: var(--font-sans);
      font-size: 14px;
      background: var(--mavdee-grey);
      color: var(--mavdee-text);
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
      padding-bottom: calc(var(--bottom-nav-height, 60px) + 80px + env(safe-area-inset-bottom));
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ── Checkout Header ──────────────────────────────────────── */
    .co-header {
      background: #fff;
      border-bottom: 1px solid var(--mavdee-border);
      position: sticky;
      top: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
    }

    .co-back-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.3rem;
      color: var(--mavdee-dark);
      display: flex;
      align-items: center;
      padding: 0;
      line-height: 1;
    }

    .co-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--mavdee-dark);
      margin: 0;
      flex: 1;
    }

    .co-savings-badge {
      font-size: 12px;
      font-weight: 600;
      color: var(--mavdee-green);
    }

    /* ── Card sections ─────────────────────────────────────────── */
    .co-card {
      background: #fff;
      margin: 8px 0;
      padding: 14px 16px;
      border: 1px solid var(--mavdee-border);
      border-radius: 10px;
    }

    .co-card-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--mavdee-dark);
      margin: 0 0 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .co-card-link {
      font-size: 13px;
      font-weight: 600;
      color: var(--mavdee-pink);
      cursor: pointer;
    }

    /* ── Order Items ─────────────────────────────────────────── */
    .co-order-item {
      display: flex;
      gap: 12px;
      margin-bottom: 14px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--mavdee-border);
    }

    .co-order-item:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .co-item-img {
      width: 72px;
      height: 90px;
      object-fit: cover;
      border-radius: 4px;
      background: var(--Mavdee-grey);
      flex-shrink: 0;
    }

    .co-item-details {
      flex: 1;
    }

    .co-item-brand {
      font-size: 13px;
      font-weight: 700;
      color: var(--Mavdee-dark);
    }

    .co-item-name {
      font-size: 12px;
      color: var(--Mavdee-muted);
      margin: 2px 0 4px;
    }

    .co-item-size {
      font-size: 12px;
      color: var(--Mavdee-text);
      margin-bottom: 4px;
    }

    .co-item-delivery {
      font-size: 12px;
      color: var(--Mavdee-green);
      font-weight: 600;
    }

    .co-item-price {
      font-size: 13px;
      font-weight: 700;
      color: var(--Mavdee-dark);
      margin-top: 6px;
    }

    .co-item-qty-ctrl {
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--Mavdee-border);
      border-radius: 4px;
      margin-top: 6px;
    }

    .co-item-qty-ctrl button {
      background: none;
      border: none;
      padding: 3px 10px;
      cursor: pointer;
      font-size: 15px;
      color: var(--Mavdee-dark);
    }

    .qty-input {
      width: 36px;
      text-align: center;
      border: none;
      outline: none;
      font-size: 13px;
      font-weight: 600;
      color: var(--Mavdee-dark);
    }

    .co-item-remove {
      font-size: 11px;
      color: var(--Mavdee-pink);
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      margin-top: 4px;
      display: block;
    }

    /* ── Coupons section ─────────────────────────────────────── */
    .co-coupon-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--Mavdee-border);
    }

    .co-coupon-row:last-child {
      border-bottom: none;
    }

    .co-coupon-tag {
      width: 32px;
      height: 32px;
      background: var(--mavdee-pink-light, #fff0f3);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .co-coupon-text {
      flex: 1;
    }

    .co-coupon-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--mavdee-dark);
    }

    .co-coupon-sub {
      font-size: 12px;
      color: var(--Mavdee-muted);
    }

    .co-apply-btn {
      border: 1.5px solid var(--Mavdee-pink);
      color: var(--Mavdee-pink);
      background: #fff;
      border-radius: 4px;
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      flex-shrink: 0;
    }

    .co-all-offers {
      font-size: 13px;
      font-weight: 600;
      color: var(--Mavdee-pink);
      display: block;
      margin-top: 8px;
    }

    /* ── Price Details ───────────────────────────────────────── */
    .co-price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 7px 0;
      font-size: 13px;
      color: var(--Mavdee-text);
    }

    .co-price-row.total {
      font-weight: 700;
      font-size: 15px;
      color: var(--Mavdee-dark);
      padding-top: 10px;
      border-top: 1px solid var(--Mavdee-border);
      margin-top: 4px;
    }

    .co-price-green {
      color: var(--Mavdee-green);
      font-weight: 700;
    }

    .co-price-free {
      color: var(--Mavdee-green);
      font-weight: 700;
    }

    .co-price-info {
      color: var(--Mavdee-muted);
      font-size: 12px;
      margin-left: 3px;
    }

    .co-savings-strip {
      background: #e8f5e9;
      color: #2e7d32;
      font-size: 13px;
      font-weight: 600;
      text-align: center;
      padding: 8px 16px;
      margin-top: 10px;
      border-radius: 4px;
    }

    /* ── Payment Method ──────────────────────────────────────── */
    .co-payment-row {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .co-payment-icon {
      font-size: 1.4rem;
      flex-shrink: 0;
    }

    .co-payment-info {
      flex: 1;
    }

    .co-payment-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--Mavdee-dark);
    }

    .co-payment-sub {
      font-size: 12px;
      color: var(--Mavdee-muted);
    }

    .co-last-used {
      background: #e8f5e9;
      color: #2e7d32;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 3px;
      text-transform: uppercase;
    }

    /* ── Address Form ────────────────────────────────────────── */
    .co-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .co-form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .co-form-group.full {
      grid-column: 1/-1;
    }

    .co-form-group label {
      font-size: 12px;
      font-weight: 600;
      color: var(--Mavdee-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .co-form-control {
      padding: 0 14px;
      height: 48px;
      border: 1.5px solid var(--Mavdee-border);
      border-radius: 8px;
      font-size: 14px;
      font-family: var(--font-sans);
      color: var(--Mavdee-dark);
      background: #fff;
      outline: none;
      transition: border-color 0.2s;
      width: 100%;
      box-sizing: border-box;
    }

    .co-form-control:focus {
      border-color: var(--Mavdee-pink);
    }

    .field-error {
      color: #e53935;
      font-size: 12px;
      margin-top: 2px;
      display: block;
    }

    /* Saved address cards */
    .co-address-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 10px;
    }

    .co-address-card {
      border: 1.5px solid var(--Mavdee-border);
      border-radius: 8px;
      padding: 12px 14px;
      cursor: pointer;
      flex: 1;
      min-width: 150px;
      font-size: 13px;
      transition: border-color 0.2s;
    }

    .co-address-card.selected {
      border-color: var(--Mavdee-pink);
      background: var(--Mavdee-pink-light, #fff0f3);
    }

    .co-address-card .card-label {
      font-weight: 700;
      font-size: 12px;
      color: var(--Mavdee-muted);
      text-transform: uppercase;
      margin-bottom: 3px;
    }

    .co-address-card .card-name {
      font-weight: 700;
      color: var(--Mavdee-dark);
      margin-bottom: 2px;
    }

    .co-address-card .card-line {
      color: var(--Mavdee-muted);
      line-height: 1.4;
    }

    /* ── Payment options ─────────────────────────────────────── */
    .co-payment-opts {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 16px;
    }

    .co-payment-opt {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border: 1.5px solid var(--Mavdee-border);
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      transition: border-color 0.2s;
    }

    .co-payment-opt:has(input:checked) {
      border-color: var(--Mavdee-pink);
      background: var(--Mavdee-pink-light, #fff0f3);
    }

    .co-payment-opt input[type="radio"] {
      accent-color: var(--Mavdee-pink);
    }

    /* ── Sticky CTA ──────────────────────────────────────────── */
    .co-cta-wrap {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #fff;
      border-top: 1px solid var(--Mavdee-border);
      padding: 12px 16px;
      padding-bottom: calc(var(--bottom-nav-height, 60px) + 12px + env(safe-area-inset-bottom));
      z-index: 100;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .co-cta-btn {
      width: 100%;
      height: 52px;
      padding: 0 14px;
      background: var(--Mavdee-pink);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.04em;
      cursor: pointer;
      font-family: var(--font-sans);
      transition: background 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .co-cta-btn:hover {
      background: #e0325a;
    }

    .co-cta-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .co-secure-note {
      text-align: center;
      font-size: 12px;
      color: var(--Mavdee-muted);
    }

    /* ── Error ───────────────────────────────────────────────── */
    .alert-error {
      background: #fce4ec;
      border: 1px solid #f48fb1;
      color: #880e4f;
      padding: 10px 14px;
      border-radius: 6px;
      font-size: 13px;
      margin-bottom: 12px;
    }

    /* ── Desktop ─────────────────────────────────────────────── */
    @media (max-width: 767px) {
      .co-layout {
        padding: 8px 10px;
      }

      .co-card {
        border-radius: 12px;
        margin: 6px 0;
      }

      .co-form-grid {
        grid-template-columns: 1fr;
      }

      .co-form-group.full {
        grid-column: 1;
      }

      .co-address-cards {
        flex-direction: column;
      }

      .co-address-card {
        min-width: unset;
        width: 100%;
      }
    }

    @media (min-width: 768px) {
      body {
        padding-bottom: 0;
        background: var(--Mavdee-grey);
      }

      .co-layout {
        max-width: 1100px;
        margin: 24px auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 20px;
        align-items: start;
      }

      .co-header {
        display: flex;
      }

      .co-cta-wrap {
        position: static;
        border-top: none;
        padding: 0;
        background: transparent;
      }

      .co-cta-btn {
        border-radius: 8px;
      }

      /* Desktop: cards go back to flat/full-bleed style */
      .co-card {
        border-radius: 12px;
      }
    }

    @media (min-width: 1024px) {
      .co-layout {
        max-width: 1200px;
        grid-template-columns: 7fr 5fr;
        gap: 28px;
        padding: 0 24px;
      }
    }
  </style>
</head>

<body>

  <!-- Checkout Header -->
  <header class="co-header">
    <button class="co-back-btn" onclick="history.back()" aria-label="Go back">←</button>
    <h1 class="co-title">Review Order</h1>
    <?php
    $totalMrp = 0;
    foreach ($cartItems as $item) {
      $origPrice = (float)($item['original_price'] ?? $item['price']);
      $totalMrp += $origPrice * $item[$qtyCol];
    }
    $savings = $totalMrp - $subtotal;
    ?>
    <?php if ($savings > 0): ?>
      <span class="co-savings-badge">Saving <?= CURRENCY ?><?= number_format($savings, 0) ?> 🎉</span>
    <?php endif; ?>
  </header>

  <div class="co-layout">
    <!-- Left / Main column -->
    <div>

      <?php if ($error): ?>
        <div class="co-card">
          <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <!-- Order Items -->
      <div class="co-card">
        <div class="co-card-title">
          Order Summary
        </div>
        <?php foreach ($cartItems as $item):
          $unitPrice = (float)($item['sale_price'] ?: $item['price']);
        ?>
          <div class="co-order-item" data-product-id="<?= (int)$item['product_id'] ?>"
            data-size="<?= h($item['size'] ?? '') ?>" data-color="<?= h($item['color'] ?? '') ?>"
            data-price="<?= $unitPrice ?>">
            <img class="co-item-img" src="<?= h(img_url($item['image_url'])) ?>" alt="<?= h($item['name']) ?>" onerror="this.style.display='none'">
            <div class="co-item-details">
              <div class="co-item-brand"><?= htmlspecialchars($item['name']) ?></div>
              <?php if (!empty($item['size'])): ?>
                <div class="co-item-size">Size: <?= htmlspecialchars($item['size']) ?></div>
              <?php endif; ?>
              <div class="co-item-delivery">📦 Delivery in 2 days</div>
              <div class="co-item-price"><?= CURRENCY ?><span class="line-total"><?= number_format($unitPrice * $item[$qtyCol], 0) ?></span></div>
              <div class="co-item-qty-ctrl">
                <button type="button" onclick="changeQty(this,-1)">−</button>
                <input type="number" class="qty-input" value="<?= (int)$item[$qtyCol] ?>" min="1" onchange="qtyChanged(this)">
                <button type="button" onclick="changeQty(this,1)">+</button>
              </div>
              <button class="co-item-remove" type="button" onclick="removeItem(this)">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Coupons & Bank Offers -->
      <div class="co-card">
        <div class="co-card-title">Coupon Code</div>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" id="couponInput" placeholder="Enter coupon code" style="flex:1;padding:10px 12px;border:1px solid var(--Mavdee-border);border-radius:4px;font-size:14px;text-transform:uppercase;">
          <button type="button" id="applyCouponBtn" class="co-apply-btn" onclick="applyCoupon()">Apply</button>
          <button type="button" id="removeCouponBtn" class="co-apply-btn" style="display:none;background:#e0325a;" onclick="removeCoupon()">Remove</button>
        </div>
        <div id="couponMsg" style="margin-top:6px;font-size:13px;"></div>
        <input type="hidden" name="coupon_code" id="couponCodeHidden" value="">
      </div>

      <!-- Shipping / Address Form -->
      <div class="co-card">
        <div class="co-card-title">Delivery Address</div>
        <form id="checkoutForm" method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <?php if ($rzpEnabled): ?>
            <input type="hidden" name="razorpay_payment_id" id="rzpPaymentId" value="">
            <input type="hidden" name="razorpay_order_id" id="rzpOrderId" value="">
            <input type="hidden" name="razorpay_signature" id="rzpSignature" value="">
          <?php endif; ?>

          <?php if (!empty($savedAddresses)): ?>
            <div class="co-address-cards" id="addressCards">
              <?php foreach ($savedAddresses as $addr): ?>
                <div class="co-address-card <?= $addr['is_default'] ? 'selected' : '' ?>"
                  onclick="selectAddress(this,<?= (int)$addr['id'] ?>)"
                  data-id="<?= (int)$addr['id'] ?>"
                  data-name="<?= h($addr['name']) ?>"
                  data-phone="<?= h($addr['phone']) ?>"
                  data-address="<?= h($addr['address']) ?>"
                  data-city="<?= h($addr['city']) ?>"
                  data-state="<?= h($addr['state']) ?>"
                  data-pincode="<?= h($addr['pincode']) ?>">
                  <div class="card-label"><?= h($addr['label']) ?></div>
                  <div class="card-name"><?= h($addr['name']) ?></div>
                  <div class="card-line"><?= h($addr['address']) ?>, <?= h($addr['city']) ?></div>
                  <div class="card-line"><?= h($addr['state']) ?> – <?= h($addr['pincode']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
              <button type="button" onclick="showNewAddressForm()" style="padding:7px 14px;border:1.5px solid var(--Mavdee-border);border-radius:99px;font-size:13px;font-weight:600;background:#fff;cursor:pointer;">+ Add New</button>
              <button type="button" onclick="usePreviousAddress()" style="padding:7px 14px;border:1.5px solid var(--Mavdee-border);border-radius:99px;font-size:13px;font-weight:600;background:#fff;cursor:pointer;">↻ Previous</button>
            </div>
            <div id="newAddressSection" style="display:none;">
            <?php endif; ?>

            <div class="co-form-grid" id="addressFormGrid">
              <div class="co-form-group full">
                <label>Full Name *</label>
                <input type="text" name="name" id="field_name" class="co-form-control" required placeholder="Jane Doe" value="<?= h($customerInfo['name'] ?? '') ?>">
                <span class="field-error" id="error-field_name"></span>
              </div>
              <div class="co-form-group">
                <label>Email *</label>
                <input type="email" name="email" id="field_email" class="co-form-control" required placeholder="jane@example.com" value="<?= h($customerInfo['email'] ?? '') ?>">
                <span class="field-error" id="error-field_email"></span>
              </div>
              <div class="co-form-group">
                <label>Phone *</label>
                <input type="tel" name="phone" id="field_phone" class="co-form-control" required placeholder="+91 9876543210">
                <span class="field-error" id="error-field_phone"></span>
              </div>
              <div class="co-form-group full">
                <label>Delivery Address *</label>
                <input type="text" name="address" id="field_address" class="co-form-control" required placeholder="House/Flat No., Street, Landmark">
                <span class="field-error" id="error-field_address"></span>
              </div>
              <div class="co-form-group">
                <label>City *</label>
                <input type="text" name="city" id="field_city" class="co-form-control" required placeholder="Mumbai">
                <span class="field-error" id="error-field_city"></span>
              </div>
              <div class="co-form-group">
                <label>State</label>
                <input type="text" name="state" id="field_state" class="co-form-control" placeholder="Maharashtra">
              </div>
              <div class="co-form-group full">
                <label>PIN Code *</label>
                <input type="text" name="pincode" id="field_pincode" class="co-form-control" required placeholder="400001" pattern="[0-9]{6}" maxlength="6">
                <span class="field-error" id="error-field_pincode"></span>
              </div>
            </div>

            <?php if (!empty($savedAddresses)): ?>
              <div style="margin-bottom:8px;display:flex;align-items:center;gap:6px;font-size:13px;color:var(--Mavdee-muted);">
                <input type="checkbox" name="save_address" id="saveAddressChk" value="1">
                <label for="saveAddressChk">Save this address for future</label>
              </div>
            </div><!-- end newAddressSection -->
          <?php else: ?>
            <div style="margin-bottom:8px;display:flex;align-items:center;gap:6px;font-size:13px;color:var(--Mavdee-muted);">
              <input type="checkbox" name="save_address" id="saveAddressChk" value="1" checked>
              <label for="saveAddressChk">Save this address for future</label>
            </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- Payment Method -->
      <div class="co-card">
        <div class="co-card-title">
          Payment Method
          <?php if ($codEnabled): ?><span class="co-card-link" onclick="document.getElementById('paymentSection').scrollIntoView()">Change</span><?php endif; ?>
        </div>
        <div id="paymentSection" class="co-payment-opts">
          <?php if ($rzpEnabled): ?>
            <label class="co-payment-opt">
              <input type="radio" name="payment_method" form="checkoutForm" value="Razorpay" checked>
              <span class="co-payment-icon">💳</span>
              <div class="co-payment-info">
                <div class="co-payment-title">Pay Online (UPI / Cards / NetBanking)</div>
                <div class="co-payment-sub">Fast &amp; Secure · Multiple options</div>
              </div>
            </label>
          <?php endif; ?>
          <?php if ($codEnabled): ?>
            <label class="co-payment-opt">
              <input type="radio" name="payment_method" form="checkoutForm" value="COD" <?= !$rzpEnabled ? 'checked' : '' ?>>
              <span class="co-payment-icon">💵</span>
              <div class="co-payment-info">
                <div class="co-payment-title">Cash on Delivery (Cash / UPI)</div>
                <div class="co-payment-sub">
                  <span class="co-last-used">Last used</span>
                  <?= $codFee > 0 ? ' + ' . CURRENCY . number_format($codFee, 0) . ' handling fee' : ' · No extra charges' ?>
                </div>
              </div>
            </label>
          <?php endif; ?>
          <?php if (!$rzpEnabled && !$codEnabled): ?>
            <p style="color:#c0392b;font-size:13px;">No payment methods available. Contact support.</p>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- end left column -->

    <!-- Right / Summary column -->
    <div>
      <!-- Price Details -->
      <div class="co-card">
        <div class="co-card-title">Price Details</div>
        <div class="co-price-row">
          <span>Total MRP <span class="co-price-info">ⓘ</span></span>
          <span id="summaryMrp"><?= CURRENCY ?><?= number_format($totalMrp, 0) ?></span>
        </div>
        <?php if ($savings > 0): ?>
          <div class="co-price-row">
            <span>Discount on MRP <a href="#" onclick="openCheckoutInfoModal('discount');return false;" style="font-size:12px;color:var(--Mavdee-pink);margin-left:4px;">Know More</a></span>
            <span class="co-price-green" id="summaryDiscount">- <?= CURRENCY ?><?= number_format($savings, 0) ?></span>
          </div>
        <?php endif; ?>
        <div class="co-price-row">
          <span>Platform Fee <a href="#" onclick="openCheckoutInfoModal('platform');return false;" style="font-size:12px;color:var(--Mavdee-pink);margin-left:4px;">Know More</a></span>
          <span class="co-price-free">FREE</span>
        </div>
        <div class="co-price-row">
          <span>Shipping</span>
          <span id="summaryShipping" class="<?= $shipping == 0 ? 'co-price-green' : '' ?>"><?= $shipping == 0 ? 'FREE' : CURRENCY . number_format($shipping, 0) ?></span>
        </div>
        <div class="co-price-row total">
          <span>Total Amount</span>
          <span id="summaryTotal"><?= CURRENCY ?><?= number_format($total, 0) ?></span>
        </div>
        <?php if ($savings > 0): ?>
          <div class="co-savings-strip">
            🎉 You're Saving <?= CURRENCY ?><?= number_format($savings, 0) ?> on this order!
          </div>
        <?php endif; ?>
      </div>

      <!-- Confirm CTA for desktop -->
      <div class="co-cta-wrap">
        <button type="submit" form="checkoutForm" class="co-cta-btn" id="placeOrderBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg>
          Confirm &amp; Place Order <?= CURRENCY ?><span id="ctaTotal"><?= number_format($total, 0) ?></span>
        </button>
        <div class="co-secure-note">🔒 100% Secure &amp; Encrypted Checkout</div>
        <p id="orderMsg" style="margin:4px 0;font-weight:600;color:var(--Mavdee-green);text-align:center;display:none;font-size:13px;"></p>
      </div>
    </div><!-- end right column -->
  </div><!-- end co-layout -->

  <?php if ($rzpEnabled): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <?php endif; ?>
  <script src="/assets/js/checkout.js" defer></script>
  <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    const CURRENCY = '<?= addslashes(CURRENCY) ?>';
    const FREE_SHIPPING = <?= (int)$freeShippingAbove ?>;
    const SHIP_COST = <?= (int)$stdShippingCost ?>;
    const RZP_ENABLED = <?= $rzpEnabled ? 'true' : 'false' ?>;
    const RZP_KEY = '<?= addslashes($rzpKey) ?>';

    function formatPrice(n) {
      return Number(n).toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      });
    }

    function recalcTotals() {
      let subtotal = 0;
      document.querySelectorAll('.co-order-item').forEach(function(item) {
        const qty = parseInt(item.querySelector('.qty-input').value) || 0;
        const price = parseFloat(item.dataset.price) || 0;
        const lt = qty * price;
        const lt_el = item.querySelector('.line-total');
        if (lt_el) lt_el.textContent = formatPrice(lt);
        subtotal += lt;
      });
      const shipping = subtotal >= FREE_SHIPPING ? 0 : (subtotal === 0 ? 0 : SHIP_COST);
      const total = subtotal + shipping;
      const totalEl = document.getElementById('summaryTotal');
      if (totalEl) totalEl.textContent = CURRENCY + formatPrice(total);
      const shipEl = document.getElementById('summaryShipping');
      if (shipEl) {
        shipEl.textContent = shipping === 0 ? 'FREE' : CURRENCY + formatPrice(shipping);
        shipEl.className = shipping === 0 ? 'co-price-green' : '';
      }
      const ctaEl = document.getElementById('ctaTotal');
      if (ctaEl) ctaEl.textContent = formatPrice(total);
    }

    async function changeQty(btn, delta) {
      const item = btn.closest('.co-order-item');
      const qtyInput = item.querySelector('.qty-input');
      let qty = parseInt(qtyInput.value) + delta;
      if (qty <= 0) {
        removeItem(item.querySelector('.co-item-remove'));
        return;
      }
      qtyInput.value = qty;
      recalcTotals();

      // Guest-aware endpoint like shop.php/cart.js
      let endpoint = '/api/cart/update.php';
      try {
        const statusRes = await fetch('/api/auth/status.php');
        if (statusRes.ok) {
          const statusData = await statusRes.json();
          if (!statusData.logged_in) endpoint = '/api/cart/update_guest.php';
        }
      } catch {}

      const productId = item.dataset.productId;
      const size = item.dataset.size || '';
      const color = item.dataset.color || '';
      const fd = new URLSearchParams({
        product_id: productId,
        qty,
        size,
        color
      });
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) fd.set('csrf_token', csrfMeta.getAttribute('content'));
      try {
        await fetch(endpoint, {
          method: 'POST',
          body: fd
        });
        if (typeof loadCart === 'function') loadCart();
      } catch (e) {
        console.error('Update qty failed:', e);
      }
    }

    async function qtyChanged(input) {
      let v = parseInt(input.value);
      if (isNaN(v) || v < 1) {
        v = 1;
        input.value = 1;
      }
      recalcTotals();

      const item = input.closest('.co-order-item');
      const productId = item.dataset.productId;
      const size = item.dataset.size || '';
      const color = item.dataset.color || '';

      let endpoint = '/api/cart/update.php';
      try {
        const statusRes = await fetch('/api/auth/status.php');
        if (statusRes.ok) {
          const statusData = await statusRes.json();
          if (!statusData.logged_in) endpoint = '/api/cart/update_guest.php';
        }
      } catch {}

      const fd = new URLSearchParams({
        product_id: productId,
        qty: v,
        size,
        color
      });
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) fd.set('csrf_token', csrfMeta.getAttribute('content'));
      try {
        await fetch(endpoint, {
          method: 'POST',
          body: fd
        });
        if (typeof loadCart === 'function') loadCart();
      } catch (e) {
        console.error('Update qty failed:', e);
      }
    }

    async function removeItem(btn) {
      const item = btn.closest('.co-order-item');
      const productId = item.dataset.productId;
      const size = item.dataset.size || '';
      const color = item.dataset.color || '';

      let endpoint = '/api/cart/update.php';
      try {
        const statusRes = await fetch('/api/auth/status.php');
        if (statusRes.ok) {
          const statusData = await statusRes.json();
          if (!statusData.logged_in) endpoint = '/api/cart/update_guest.php';
        }
      } catch {}

      const fd = new URLSearchParams({
        product_id: productId,
        qty: 0,
        size,
        color
      });
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) fd.set('csrf_token', csrfMeta.getAttribute('content'));
      item.style.opacity = '0.4';
      try {
        await fetch(endpoint, {
          method: 'POST',
          body: fd
        });
        item.remove();
        recalcTotals();

        if (typeof loadCart === 'function') loadCart();

        const count = document.querySelectorAll('.co-order-item').length;
        if (typeof window.updateCartBadge === 'function') {
          window.updateCartBadge(count);
        } else {
          const badge = document.getElementById('cartBadge');
          if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
          }
        }
        if (count === 0) window.location = 'shop.php';
      } catch (e) {
        console.error('Remove failed:', e);
        item.style.opacity = '1';
      }
    }

    // Saved address selection
    function selectAddress(card, id) {
      document.querySelectorAll('.co-address-card').forEach(function(c) {
        c.classList.remove('selected');
      });
      card.classList.add('selected');
      document.getElementById('field_name').value = card.dataset.name || '';
      document.getElementById('field_phone').value = card.dataset.phone || '';
      document.getElementById('field_address').value = card.dataset.address || '';
      document.getElementById('field_city').value = card.dataset.city || '';
      document.getElementById('field_state').value = card.dataset.state || '';
      document.getElementById('field_pincode').value = card.dataset.pincode || '';
    }

    function showNewAddressForm() {
      var s = document.getElementById('newAddressSection');
      if (s) s.style.display = 'block';
    }

    function usePreviousAddress() {
      var first = document.querySelector('.co-address-card');
      if (first) selectAddress(first, first.dataset.id);
    }

    async function applyCoupon() {
      const input = document.getElementById('couponInput');
      const hidden = document.getElementById('couponCodeHidden');
      const msg = document.getElementById('couponMsg');
      const code = input.value.trim().toUpperCase();
      if (!code) {
        msg.style.color = 'red';
        msg.textContent = 'Please enter a coupon code.';
        return;
      }

      const subtotal = parseFloat(document.getElementById('co-subtotal-val')?.dataset.amount || '0');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      try {
        const body = JSON.stringify({
          code,
          subtotal,
          csrf_token: csrf
        });
        const res = await fetch('/api/coupons/validate.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body
        });
        const data = await res.json();
        if (data.success) {
          hidden.value = code;
          input.disabled = true;
          document.getElementById('applyCouponBtn').style.display = 'none';
          document.getElementById('removeCouponBtn').style.display = '';
          msg.style.color = 'green';
          msg.textContent = '✓ ' + data.message;
        } else {
          hidden.value = '';
          msg.style.color = 'red';
          msg.textContent = data.error || 'Invalid coupon code.';
        }
      } catch (e) {
        msg.style.color = 'red';
        msg.textContent = 'Could not validate coupon. Please try again.';
      }
    }

    function removeCoupon() {
      document.getElementById('couponCodeHidden').value = '';
      document.getElementById('couponInput').value = '';
      document.getElementById('couponInput').disabled = false;
      document.getElementById('applyCouponBtn').style.display = '';
      document.getElementById('removeCouponBtn').style.display = 'none';
      document.getElementById('couponMsg').textContent = '';
    }

    // Form submit handler — intercepts for Razorpay, passes through for COD
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
      // Field validation first
      var valid = true;
      ['field_name', 'field_phone', 'field_address', 'field_city', 'field_pincode'].forEach(function(id) {
        var el = document.getElementById(id);
        var err = document.getElementById('error-' + id);
        if (el && !el.value.trim()) {
          valid = false;
          el.style.borderColor = 'var(--Mavdee-pink)';
          if (err) err.textContent = 'This field is required';
        } else if (el) {
          el.style.borderColor = '';
          if (err) err.textContent = '';
        }
      });
      if (!valid) {
        e.preventDefault();
        return;
      }

      // Check if Razorpay is selected
      var payMethod = document.querySelector('input[name="payment_method"]:checked');
      if (RZP_ENABLED && payMethod && payMethod.value === 'Razorpay') {
        e.preventDefault();
        launchRazorpay();
        return;
      }

      // COD — disable button and submit normally
      var btn = document.getElementById('placeOrderBtn');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.7';
      }
    });

    async function launchRazorpay() {
      if (typeof Razorpay === 'undefined') {
        alert('Payment service failed to load. Please check your connection and try again.');
        var btn = document.getElementById('placeOrderBtn');
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Confirm & Place Order';
        }
        return;
      }
      var btn = document.getElementById('placeOrderBtn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing…';
      }

      var csrf = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
      try {
        var res = await fetch('/api/payment/create_order.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': csrf
          },
          body: 'csrf_token=' + encodeURIComponent(csrf)
        });
        var data = await res.json();
        if (!res.ok || data.error) {
          alert(data.error || 'Could not initiate payment. Please try again.');
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirm & Place Order';
          }
          return;
        }
      } catch (err) {
        alert('Network error. Please try again.');
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Confirm & Place Order';
        }
        return;
      }

      var nameEl = document.getElementById('field_name');
      var emailEl = document.getElementById('field_email');
      var phoneEl = document.getElementById('field_phone');
      var options = {
        key: RZP_KEY,
        amount: data.amount,
        currency: data.currency || 'INR',
        order_id: data.order_id,
        name: 'Mavdee',
        description: 'Order Payment',
        handler: function(response) {
          document.getElementById('rzpPaymentId').value = response.razorpay_payment_id;
          document.getElementById('rzpOrderId').value = response.razorpay_order_id;
          document.getElementById('rzpSignature').value = response.razorpay_signature;
          if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.7';
          }
          document.getElementById('checkoutForm').submit();
        },
        prefill: {
          name: nameEl ? nameEl.value : '',
          email: emailEl ? emailEl.value : '',
          contact: phoneEl ? phoneEl.value : ''
        },
        theme: {
          color: '#ff3f6c'
        },
        modal: {
          ondismiss: function() {
            if (btn) {
              btn.disabled = false;
              btn.textContent = 'Confirm & Place Order';
            }
          }
        }
      };
      var rzp1 = new Razorpay(options);
      rzp1.open();
    }

    // Auto-populate form fields from pre-selected address on page load
    document.addEventListener('DOMContentLoaded', function() {
      var selectedCard = document.querySelector('.co-address-card.selected');
      if (selectedCard) {
        var nameEl = document.getElementById('field_name');
        var phoneEl = document.getElementById('field_phone');
        var addressEl = document.getElementById('field_address');
        var cityEl = document.getElementById('field_city');
        var stateEl = document.getElementById('field_state');
        var pincodeEl = document.getElementById('field_pincode');
        if (nameEl && !nameEl.value) nameEl.value = selectedCard.dataset.name || '';
        if (phoneEl && !phoneEl.value) phoneEl.value = selectedCard.dataset.phone || '';
        if (addressEl && !addressEl.value) addressEl.value = selectedCard.dataset.address || '';
        if (cityEl && !cityEl.value) cityEl.value = selectedCard.dataset.city || '';
        if (stateEl && !stateEl.value) stateEl.value = selectedCard.dataset.state || '';
        if (pincodeEl && !pincodeEl.value) pincodeEl.value = selectedCard.dataset.pincode || '';
      }
    });
  </script>

  <!-- ── Price Info Modal ──────────────────────────────────────────────────────── -->
  <div id="checkoutInfoModal" role="dialog" aria-modal="true" style="display:none;position:fixed;inset:0;z-index:5000;">
    <div id="checkoutInfoBackdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.5);"></div>
    <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:center;min-height:100%;padding:16px;">
      <div style="background:#fff;border-radius:12px;padding:28px 24px 24px;max-width:400px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.18);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <h2 id="checkoutInfoTitle" style="font-family:var(--font-sans);font-size:1rem;font-weight:700;margin:0;color:#1c1c1c;"></h2>
          <button onclick="closeCheckoutInfoModal()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94969f;line-height:1;padding:0 4px;">&times;</button>
        </div>
        <p id="checkoutInfoBody" style="font-size:.88rem;color:#535766;margin:0;line-height:1.65;"></p>
      </div>
    </div>
  </div>
  <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    var _checkoutInfoContent = {
      discount: {
        title: 'Discount on MRP',
        body: 'This is the total saving you get on this order — the difference between the original MRP (Maximum Retail Price) of the items and the price you pay after all product-level discounts are applied.'
      },
      platform: {
        title: 'Platform Fee',
        body: 'We do not charge any platform or convenience fee. The amount you see is exactly what you pay — no hidden charges ever.'
      }
    };

    (function() {
      var backdrop = document.getElementById('checkoutInfoBackdrop');
      if (backdrop) backdrop.addEventListener('click', closeCheckoutInfoModal);
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          var m = document.getElementById('checkoutInfoModal');
          if (m && m.style.display !== 'none') closeCheckoutInfoModal();
        }
      });
    })();

    function openCheckoutInfoModal(type) {
      var content = _checkoutInfoContent[type];
      if (!content) return;
      document.getElementById('checkoutInfoTitle').textContent = content.title;
      document.getElementById('checkoutInfoBody').textContent = content.body;
      var m = document.getElementById('checkoutInfoModal');
      if (m) {
        m.style.display = '';
        document.body.style.overflow = 'hidden';
      }
    }

    function closeCheckoutInfoModal() {
      var m = document.getElementById('checkoutInfoModal');
      if (m) {
        m.style.display = 'none';
        document.body.style.overflow = '';
      }
    }
  </script>
<!-- Code injected by live-server -->
<script type="text/javascript">
	// <![CDATA[  <-- For SVG support
	if ('WebSocket' in window) {
		(function() {
			function refreshCSS() {
				var sheets = [].slice.call(document.getElementsByTagName("link"));
				var head = document.getElementsByTagName("head")[0];
				for (var i = 0; i < sheets.length; ++i) {
					var elem = sheets[i];
					head.removeChild(elem);
					var rel = elem.rel;
					if (elem.href && typeof rel != "string" || rel.length == 0 || rel.toLowerCase() == "stylesheet") {
						var url = elem.href.replace(/(&|\?)_cacheOverride=\d+/, '');
						elem.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_cacheOverride=' + (new Date().valueOf());
					}
					head.appendChild(elem);
				}
			}
			var protocol = window.location.protocol === 'http:' ? 'ws://' : 'wss://';
			var address = protocol + window.location.host + window.location.pathname + '/ws';
			var socket = new WebSocket(address);
			socket.onmessage = function(msg) {
				if (msg.data == 'reload') window.location.reload();
				else if (msg.data == 'refreshcss') refreshCSS();
			};
			console.log('Live reload enabled.');
		})();
	}
	// ]]>
</script>
</body>