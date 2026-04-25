<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/email.php';

// Order calculation defaults (override in config if needed)
if (!defined('TAX_RATE'))                 define('TAX_RATE', 0.18);
if (!defined('FREE_SHIPPING_THRESHOLD'))  define('FREE_SHIPPING_THRESHOLD', 999);
if (!defined('SHIPPING_COST'))            define('SHIPPING_COST', 99);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json.']);
    exit;
}

csrf_check();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
        exit;
    }

    $name           = trim($input['name'] ?? '');
    $email          = trim($input['email'] ?? '');
    $phone          = trim($input['phone'] ?? '');
    $address        = trim($input['address'] ?? '');
    $city           = trim($input['city'] ?? '');
    $state          = trim($input['state'] ?? '');
    $pincode        = trim($input['pincode'] ?? '');
    $paymentMethod  = trim($input['payment_method'] ?? '');
    $items          = $input['items'] ?? [];
    $notes          = trim($input['notes'] ?? '');
    $paymentId      = trim($input['payment_id'] ?? '');
    $discountAmount = (float)($input['discount_amount'] ?? 0);

    // Validate required fields
    if ($name === '' || $email === '' || $phone === '' || $address === '' || $city === '' || $state === '' || $pincode === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All address fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid email is required.']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Order must contain at least one item.']);
        exit;
    }

    if ($paymentMethod === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment method is required.']);
        exit;
    }

    $pdo = db();
    $pdo->beginTransaction();

    // Fetch product details and calculate subtotal
    $subtotal = 0;
    $orderItems = [];

    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $qty       = max(1, (int)($item['qty'] ?? 1));
        $size      = trim($item['size'] ?? '');
        $color     = trim($item['color'] ?? '');

        $stmt = $pdo->prepare("SELECT id, name, sku, price, sale_price, stock FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Product ID $productId not found or unavailable."]);
            exit;
        }

        if ($product['stock'] < $qty) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Insufficient stock for \"{$product['name']}\"."]);
            exit;
        }

        $unitPrice = (float)($product['sale_price'] ?: $product['price']);
        $totalPrice = $unitPrice * $qty;
        $subtotal += $totalPrice;

        $orderItems[] = [
            'product_id'   => $product['id'],
            'product_name' => $product['name'],
            'sku'          => $product['sku'],
            'size'         => $size,
            'color'        => $color,
            'qty'          => $qty,
            'unit_price'   => $unitPrice,
            'total_price'  => $totalPrice,
        ];
    }

    // Calculate totals
    $taxAmount      = round($subtotal * TAX_RATE, 2);
    $shippingAmount = ($subtotal >= FREE_SHIPPING_THRESHOLD) ? 0 : SHIPPING_COST;
    $total          = round($subtotal + $taxAmount + $shippingAmount - $discountAmount, 2);

    // Generate order number
    $orderNumber = 'ORD-' . strtoupper(dechex(time())) . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $customerId = getUserId();
    $paymentStatus = ($paymentId !== '') ? 'paid' : 'pending';

    $stmt = $pdo->prepare("INSERT INTO orders
        (order_number, customer_id, customer_name, customer_email, customer_phone,
         shipping_address, city, state, pincode,
         subtotal, tax_amount, shipping_amount, discount_amount, total,
         payment_method, payment_status, payment_id, status, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
    $stmt->execute([
        $orderNumber,
        $customerId,
        $name,
        $email,
        $phone,
        $address,
        $city,
        $state,
        $pincode,
        $subtotal,
        $taxAmount,
        $shippingAmount,
        $discountAmount,
        $total,
        $paymentMethod,
        $paymentStatus,
        $paymentId ?: null,
        $notes,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // Insert order items and update stock atomically.
    // FIX: previously used SELECT-then-UPDATE — a race condition allowed two
    // simultaneous requests to both pass the stock check and oversell.
    // Now uses WHERE stock >= qty + rowCount() check, same as place.php.
    $stmtItem = $pdo->prepare("INSERT INTO order_items
        (order_id, product_id, product_name, sku, size, color, qty, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtStock = $pdo->prepare(
        "UPDATE products SET stock = stock - ?, sales_count = sales_count + ?
          WHERE id = ? AND stock >= ?"
    );

    foreach ($orderItems as $oi) {
        $stmtItem->execute([
            $orderId,
            $oi['product_id'],
            $oi['product_name'],
            $oi['sku'],
            $oi['size'],
            $oi['color'],
            $oi['qty'],
            $oi['unit_price'],
            $oi['total_price'],
        ]);
        $stmtStock->execute([$oi['qty'], $oi['qty'], $oi['product_id'], $oi['qty']]);
        if ($stmtStock->rowCount() === 0) {
            throw new RuntimeException("Insufficient stock for product ID {$oi['product_id']}");
        }
    }

    // Update customer stats and clear cart
    $pdo->prepare("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?")
        ->execute([$total, $customerId]);

    // Clear cart
    $cartCols    = cart_schema_columns();
    $cartUserCol = in_array('customer_id', $cartCols) ? 'customer_id' : 'user_id';
    $pdo->prepare("DELETE FROM cart WHERE $cartUserCol = ?")->execute([$customerId]);

    $pdo->commit();

    // Send order confirmation email
    try {
        $emailHandler = new EmailHandler();
        $orderData = [
            'order_number' => $orderNumber,
            'customer_name' => $name,
            'total' => $total,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'discount_amount' => $discountAmount,
            'payment_method' => $paymentMethod,
        ];
        $emailHandler->sendOrderInvoice($email, $orderData, $orderItems);
    } catch (Exception $e) {
        // Email failure should not fail the order
    }

    echo json_encode(['success' => true, 'order_number' => $orderNumber]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
