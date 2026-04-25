<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/includes/crm.php';

// Security measure: Only allow execution from CLI or with a secure token to prevent unauthorized triggers.
// If CRON_SECRET is empty (misconfigured), deny all HTTP access for safety.
if (php_sapi_name() !== 'cli') {
    if (CRON_SECRET === '' || ($_GET['token'] ?? '') !== CRON_SECRET) {
        http_response_code(403);
        die('Forbidden');
    }
}

try {
    $crmMailer = new CRMMailer();

    // Find customers who have items currently in their cart
    $cols    = cart_schema_columns();
    $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
    $qtyCol  = in_array('qty', $cols) ? 'qty' : 'quantity';

    // Only notify if cart was last updated more than 24 h ago
    // and we haven't sent an abandoned-cart email within the last 48 h
    // Falls back to created_at for deployments that haven't run the migration yet.
    // TODO: Remove the db_columns() check and always use 'updated_at' once all environments
    // have run the ALTER TABLE migration that adds the column to the cart table.
    $updatedCol = in_array('updated_at', db_columns('cart')) ? 'updated_at' : 'created_at';
    $stmt = db()->prepare("
        SELECT c.$userCol as user_id, cust.name, cust.email
        FROM cart c
        JOIN customers cust ON c.$userCol = cust.id
        LEFT JOIN abandoned_cart_logs acl ON c.$userCol = acl.customer_id
        WHERE (acl.email_sent_at IS NULL OR acl.email_sent_at < DATE_SUB(NOW(), INTERVAL 48 HOUR))
        GROUP BY c.$userCol
        HAVING MAX(c.$updatedCol) < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $abandonedCarts = $stmt->fetchAll();

    $count = 0;
    foreach ($abandonedCarts as $cart) {
        // Fetch cart items for this user
        $itemStmt = db()->prepare(
            "SELECT c.$qtyCol as qty, p.name, p.price, p.image_url, p.id as product_id
               FROM cart c
               JOIN products p ON c.product_id = p.id
              WHERE c.$userCol = ?"
        );
        $itemStmt->execute([$cart['user_id']]);
        $cartItems = $itemStmt->fetchAll();

        if (empty($cartItems)) continue;

        if ($crmMailer->sendAbandonedCartEmail($cart['email'], $cart['name'], $cartItems)) {
            $count++;

            // Log that we sent the email to prevent spamming
            db()->prepare("
                INSERT INTO abandoned_cart_logs (customer_id, recovery_email_sent, email_sent_at)
                VALUES (?, 1, NOW())
                ON DUPLICATE KEY UPDATE recovery_email_sent = 1, email_sent_at = NOW()
            ")->execute([$cart['user_id']]);
        }
    }
    echo "Successfully sent $count abandoned cart emails.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
