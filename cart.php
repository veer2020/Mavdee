<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/image_helper.php';

$cartItems = [];
$subtotal = 0;
$isGuest = false;

if (isLoggedIn()) {
  $uid = getUserId();
  try {
    $cols = cart_schema_columns();
    $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
    $stmt = db()->prepare(
      "SELECT c.*, p.name, p.price, p.original_price, p.image_url, p.slug
             FROM cart c JOIN products p ON c.product_id = p.id
             WHERE c.$userCol = ? AND p.is_active = 1"
    );
    $stmt->execute([$uid]);
    $cartItems = $stmt->fetchAll();
    foreach ($cartItems as $item) {
      $subtotal += $item['price'] * ($item['qty'] ?? 1);
    }
  } catch (Throwable $e) {
    $cartItems = [];
  }
} else {
  $isGuest = true;
  $guestCart = $_SESSION['guest_cart'] ?? [];
  if (!empty($guestCart)) {
    $ids = array_keys($guestCart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
      $stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
      $stmt->execute($ids);
      $products = $stmt->fetchAll(PDO::FETCH_UNIQUE);
      foreach ($guestCart as $pid => $qty) {
        if (isset($products[$pid])) {
          $item = $products[$pid];
          $item['qty'] = (int)$qty;
          $cartItems[] = $item;
          $subtotal += $item['price'] * $item['qty'];
        }
      }
    } catch (Throwable $e) {
      $cartItems = [];
    }
  }
}

// If empty, redirect to shop
if (empty($cartItems)) {
  header('Location: /shop.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/includes/head-favicon.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>My Cart — <?= h(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/global.css">
  <style>
    :root {
      --mavdee-pink: #ff3f6c;
      --mavdee-green: #03a685;
      --mavdee-dark: #1c1c1c;
      --mavdee-grey: #f4f4f5;
      --mavdee-border: #eaeaec;
      --mavdee-muted: #94969f;
      --mavdee-text: #3e4152;
      --font-sans: 'DM Sans', sans-serif;
    }

    body {
      font-family: var(--font-sans);
      background: #f9f9f9;
    }

    .cart-page-wrap {
      max-width: 1000px;
      margin: 0 auto;
      padding: 24px 16px 80px;
    }

    .cart-page-title {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--mavdee-dark);
    }

    .cart-item-row {
      background: #fff;
      border-radius: 10px;
      padding: 16px;
      display: flex;
      gap: 14px;
      margin-bottom: 12px;
      border: 1px solid var(--mavdee-border);
    }

    .cart-item-img {
      width: 80px;
      height: 100px;
      object-fit: cover;
      border-radius: 6px;
      flex-shrink: 0;
    }

    .cart-item-info {
      flex: 1;
      min-width: 0;
    }

    .cart-item-name {
      font-weight: 600;
      font-size: 14px;
      color: var(--mavdee-dark);
      margin-bottom: 4px;
    }

    .cart-item-price {
      font-size: 15px;
      font-weight: 700;
      color: var(--mavdee-dark);
    }

    .cart-item-qty {
      font-size: 13px;
      color: var(--mavdee-muted);
    }

    .cart-summary {
      background: #fff;
      border-radius: 10px;
      padding: 20px;
      border: 1px solid var(--mavdee-border);
      margin-top: 20px;
    }

    .cart-summary-row {
      display: flex;
      justify-content: space-between;
      font-size: 14px;
      color: var(--mavdee-text);
      margin-bottom: 10px;
    }

    .cart-summary-row.total {
      font-weight: 700;
      font-size: 16px;
      color: var(--mavdee-dark);
      border-top: 1px solid var(--mavdee-border);
      padding-top: 12px;
      margin-top: 4px;
    }

    .btn-checkout-full {
      display: block;
      width: 100%;
      padding: 14px;
      background: var(--mavdee-pink);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 700;
      text-align: center;
      text-decoration: none;
      margin-top: 14px;
      cursor: pointer;
    }

    .btn-continue {
      display: block;
      width: 100%;
      padding: 12px;
      background: #fff;
      color: var(--mavdee-dark);
      border: 1.5px solid var(--mavdee-border);
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      text-align: center;
      text-decoration: none;
      margin-top: 8px;
    }
  </style>
</head>

<body>
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main id="main-content" class="cart-page-wrap">
    <h1 class="cart-page-title">My Cart (<?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?>)</h1>
    <?php foreach ($cartItems as $item):
      $imgSrc = img_url($item['image_url'] ?? '');
      if (empty($imgSrc)) $imgSrc = '/assets/img/placeholder.svg';
    ?>
      <div class="cart-item-row">
        <img src="<?= h($imgSrc) ?>" alt="<?= h($item['name']) ?>" class="cart-item-img" onerror="this.src='/assets/img/placeholder.svg'">
        <div class="cart-item-info">
          <div class="cart-item-name"><?= h($item['name']) ?></div>
          <?php if (!empty($item['size'])): ?>
            <div class="cart-item-qty">Size: <?= h($item['size']) ?></div>
          <?php endif; ?>
          <div class="cart-item-qty">Qty: <?= (int)($item['qty'] ?? 1) ?></div>
          <div class="cart-item-price"><?= CURRENCY ?><?= number_format($item['price'] * ($item['qty'] ?? 1), 0) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="cart-summary">
      <div class="cart-summary-row"><span>Subtotal</span><span><?= CURRENCY ?><?= number_format($subtotal, 0) ?></span></div>
      <div class="cart-summary-row"><span>Shipping</span><span style="color:var(--mavdee-green)">FREE</span></div>
      <div class="cart-summary-row total"><span>Total</span><span><?= CURRENCY ?><?= number_format($subtotal, 0) ?></span></div>
      <a href="/checkout.php" class="btn-checkout-full">PROCEED TO CHECKOUT</a>
      <a href="/shop.php" class="btn-continue">Continue Shopping</a>
    </div>
  </main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
</body>

</html>