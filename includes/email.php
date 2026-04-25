<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailHandler
{
    private string  $fromName;
    private string  $fromEmail;
    private string  $adminEmail;
    private array   $smtpSettings;
    private string  $logFile;
    private ?array  $cachedSettings = null;

    public function __construct()
    {
        $s = $this->loadSettings();

        $this->fromName   = $s['mail_from_name']  ?? (defined('SITE_NAME') ? SITE_NAME : 'Ecom Store');
        $this->fromEmail  = $s['mail_from_email'] ?? (getenv('MAIL_FROM') ?: 'noreply@example.com');
        $this->adminEmail = $s['mail_admin_email'] ?? $this->fromEmail;
        $this->smtpSettings = [
            'host'       => $s['smtp_host'] ?? '',
            'port'       => (int)($s['smtp_port'] ?? 587),
            'encryption' => strtolower($s['smtp_encryption'] ?? 'tls'),
            'username'   => $s['smtp_username'] ?? '',
            'password'   => $s['smtp_password'] ?? '',
        ];
        $this->logFile = sys_get_temp_dir() . '/ecom_email.log';
    }

    /** Load email-related settings from DB (silently ignore DB errors). Cached per instance. */
    private function loadSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }
        $keys = [
            'mail_driver',
            'mail_from_name',
            'mail_from_email',
            'mail_admin_email',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'mail_notify_admin',
            'mail_notify_customer',
        ];
        try {
            $in   = implode(',', array_fill(0, count($keys), '?'));
            $stmt = db()->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($in)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $this->cachedSettings = is_array($rows) ? $rows : [];
        } catch (Throwable) {
            $this->cachedSettings = [];
        }
        return $this->cachedSettings;
    }

    /** Returns true when admin-notification emails are enabled. */
    public function adminNotifyEnabled(): bool
    {
        $s = $this->loadSettings();
        return !empty($s['mail_notify_admin']) && $s['mail_notify_admin'] !== '0';
    }

    /** Returns true when customer-notification emails are enabled. */
    public function customerNotifyEnabled(): bool
    {
        $s = $this->loadSettings();
        return !empty($s['mail_notify_customer']) && $s['mail_notify_customer'] !== '0';
    }

    /**
     * Send a generic HTML email. Returns true on success, false on failure.
     */
    public function sendEmail(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Try SMTP if configured, else fallback to mail()
        if (!empty($this->smtpSettings['host']) && !empty($this->smtpSettings['username'])) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $this->smtpSettings['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $this->smtpSettings['username'];
                $mail->Password   = $this->smtpSettings['password'];
                $mail->SMTPSecure = $this->smtpSettings['encryption'];
                $mail->Port       = $this->smtpSettings['port'];
                $mail->setFrom($this->fromEmail, $this->fromName);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                return $mail->send();
            } catch (Exception $e) {
                error_log("PHPMailer error: " . $e->getMessage());
                // fall through to mail()
            }
        }

        // Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$this->fromName} <{$this->fromEmail}>";
        $result = @mail($to, $subject, $body, $headers);

        if (!$result) {
            $entry = date('[Y-m-d H:i:s]') . " TO: $to | SUBJECT: $subject\n";
            @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
        }
        return $result;
    }

    // ── High-level email builders ────────────────────────────────────────────

    /**
     * Send an order-confirmation / invoice email to a customer.
     *
     * @param string $to         Recipient email address
     * @param array  $order      Order row from the database (id, total, etc.)
     * @param array  $orderItems Array of cart / order-item rows
     * @return bool
     */
    public function sendOrderInvoice(string $to, array $order, array $orderItems = []): bool
    {
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Ecom Store';
        $currency = defined('CURRENCY') ? CURRENCY : '₹';
        $orderId  = $order['id'] ?? 'N/A';
        $total    = (float)($order['total'] ?? 0);
        $shipping = (float)($order['shipping_cost'] ?? 0);
        $subtotal = $total - $shipping;

        $itemRows = '';
        foreach ($orderItems as $item) {
            $name     = htmlspecialchars($item['name'] ?? $item['product_name'] ?? 'Item', ENT_QUOTES, 'UTF-8');
            $qty      = (int)($item['qty'] ?? $item['quantity'] ?? 1);
            $price    = (float)($item['price'] ?? $item['unit_price'] ?? 0);
            $size     = htmlspecialchars($item['size'] ?? '', ENT_QUOTES, 'UTF-8');
            $color    = htmlspecialchars($item['color'] ?? '', ENT_QUOTES, 'UTF-8');
            $meta     = array_filter([$size ? "Size: $size" : '', $color ? "Color: $color" : '']);
            $metaHtml = $meta ? '<br><small style="color:#888;">' . implode(' | ', $meta) . '</small>' : '';
            $itemRows .= "<tr>
                <td style='padding:8px 12px;border-bottom:1px solid #eee;'>{$name}{$metaHtml}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #eee;text-align:center;'>{$qty}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #eee;text-align:right;'>{$currency}" . number_format($price * $qty, 2) . "</td>
            </tr>";
        }

        $subject = "Order Confirmed — #{$orderId} | {$siteName}";

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#14100d;'>
            <div style='text-align:center;padding:20px;background:#fcfafc;border-bottom:2px solid #dda74f;'>
                <h2 style='margin:0;color:#dda74f;font-family:Georgia,serif;'>{$siteName}</h2>
            </div>
            <div style='padding:30px 20px;background:#ffffff;'>
                <h3 style='margin-top:0;'>Thank you for your order!</h3>
                <p>Your order <strong>#{$orderId}</strong> has been placed successfully.</p>
                " . ($itemRows ? "
                <table style='width:100%;border-collapse:collapse;margin-top:16px;'>
                    <thead>
                        <tr style='background:#f3efea;'>
                            <th style='padding:8px 12px;text-align:left;'>Item</th>
                            <th style='padding:8px 12px;text-align:center;'>Qty</th>
                            <th style='padding:8px 12px;text-align:right;'>Price</th>
                        </tr>
                    </thead>
                    <tbody>{$itemRows}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan='2' style='padding:8px 12px;text-align:right;color:#666;'>Subtotal</td>
                            <td style='padding:8px 12px;text-align:right;'>{$currency}" . number_format($subtotal, 2) . "</td>
                        </tr>
                        <tr>
                            <td colspan='2' style='padding:8px 12px;text-align:right;color:#666;'>Shipping</td>
                            <td style='padding:8px 12px;text-align:right;'>" . ($shipping > 0 ? $currency . number_format($shipping, 2) : 'FREE') . "</td>
                        </tr>
                        <tr style='background:#f3efea;font-weight:700;'>
                            <td colspan='2' style='padding:8px 12px;text-align:right;'>Total</td>
                            <td style='padding:8px 12px;text-align:right;'>{$currency}" . number_format($total, 2) . "</td>
                        </tr>
                    </tfoot>
                </table>" : "
                <p style='margin-top:16px;font-size:1.1em;'><strong>Total: {$currency}" . number_format($total, 2) . "</strong></p>") . "
                <p style='color:#666;line-height:1.6;margin-top:20px;'>We will notify you once your order is shipped.</p>
            </div>
            <div style='text-align:center;padding:20px;font-size:0.8em;color:#aaa;background:#fcfafc;'>
                &copy; " . date('Y') . " {$siteName}. All rights reserved.
            </div>
        </div>";

        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Send a new-order alert to the admin email address.
     *
     * @param array $order      Order row from the database
     * @param array $orderItems Order items
     * @return bool
     */
    public function sendAdminNewOrderAlert(array $order, array $orderItems = []): bool
    {
        if (!filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteName     = defined('SITE_NAME') ? SITE_NAME : 'Ecom Store';
        $currency     = defined('CURRENCY') ? CURRENCY : '₹';
        $orderId      = $order['id'] ?? 'N/A';
        $customerName = htmlspecialchars($order['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $total        = (float)($order['total'] ?? 0);

        $itemList = '';
        foreach ($orderItems as $item) {
            $name  = htmlspecialchars($item['name'] ?? $item['product_name'] ?? 'Item', ENT_QUOTES, 'UTF-8');
            $qty   = (int)($item['qty'] ?? 1);
            $price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
            $itemList .= "<li>{$name} × {$qty} — {$currency}" . number_format($price * $qty, 2) . "</li>";
        }

        $subject = "New Order #{$orderId} — {$siteName}";
        $siteUrl  = defined('SITE_URL') ? SITE_URL : '';

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#14100d;'>
            <div style='padding:20px;background:#fcfafc;border-bottom:2px solid #dda74f;'>
                <h2 style='margin:0;color:#dda74f;'>New Order Received</h2>
            </div>
            <div style='padding:24px 20px;background:#fff;'>
                <p><strong>Order:</strong> #{$orderId}</p>
                <p><strong>Customer:</strong> {$customerName}</p>
                <p><strong>Total:</strong> {$currency}" . number_format($total, 2) . "</p>
                " . ($itemList ? "<ul style='padding-left:18px;'>{$itemList}</ul>" : '') . "
                <p style='margin-top:20px;'><a href='{$siteUrl}/admin/orders/view.php?id={$orderId}' style='background:#dda74f;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;'>View Order</a></p>
            </div>
            <div style='padding:16px;font-size:0.8em;color:#aaa;background:#fcfafc;text-align:center;'>
                &copy; " . date('Y') . " {$siteName}
            </div>
        </div>";

        return $this->sendEmail($this->adminEmail, $subject, $body);
    }

    /**
     * Send a status-update notification email to the customer.
     *
     * @param string $to        Customer email address
     * @param array  $order     Order row (id, customer_name, tracking_number, courier …)
     * @param string $newStatus New order status
     * @return bool
     */
    public function sendOrderStatusUpdate(string $to, array $order, string $newStatus): bool
    {
        $siteName     = defined('SITE_NAME') ? SITE_NAME : 'Ecom Store';
        $currency     = defined('CURRENCY') ? CURRENCY : '₹';
        $orderId      = $order['id'] ?? 'N/A';
        $customerName = htmlspecialchars($order['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $statusLabel  = ucfirst($newStatus);

        $statusMessages = [
            'processing' => 'We have started processing your order.',
            'dispatched' => 'Great news! Your order has been dispatched and is on its way.',
            'shipped'    => 'Great news! Your order is on its way.',
            'delivered'  => 'Your order has been delivered. Enjoy your purchase!',
            'cancelled'  => 'Your order has been cancelled. Contact us if you have any questions.',
        ];
        $statusMessage = $statusMessages[$newStatus] ?? "Your order status has been updated to <strong>{$statusLabel}</strong>.";

        $trackingHtml = '';
        if (in_array($newStatus, ['shipped', 'dispatched'], true) && !empty($order['tracking_number'])) {
            $trackingNum = htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8');
            $courier     = htmlspecialchars($order['courier'] ?? '', ENT_QUOTES, 'UTF-8');
            $trackingHtml = "<p style='margin-top:16px;padding:12px;background:#f3efea;border-radius:6px;'>
                <strong>Tracking Number:</strong> {$trackingNum}" .
                ($courier ? "<br><strong>Courier:</strong> {$courier}" : '') .
                "</p>";
        }

        $subject = "Order #{$orderId} — {$statusLabel} | {$siteName}";

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#14100d;'>
            <div style='text-align:center;padding:20px;background:#fcfafc;border-bottom:2px solid #dda74f;'>
                <h2 style='margin:0;color:#dda74f;font-family:Georgia,serif;'>{$siteName}</h2>
            </div>
            <div style='padding:30px 20px;background:#ffffff;'>
                <h3 style='margin-top:0;'>Order Update — #{$orderId}</h3>
                <p>Hi {$customerName},</p>
                <p>{$statusMessage}</p>
                {$trackingHtml}
                <p style='color:#666;line-height:1.6;margin-top:20px;'>Thank you for shopping with us.</p>
            </div>
            <div style='text-align:center;padding:20px;font-size:0.8em;color:#aaa;background:#fcfafc;'>
                &copy; " . date('Y') . " {$siteName}. All rights reserved.
            </div>
        </div>";

        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Send an order-cancellation confirmation to the customer.
     *
     * @param array $order  Order row from the database
     * @return bool
     */
    public function sendOrderCancellationToCustomer(array $order): bool
    {
        $to = $order['customer_email'] ?? '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteName     = defined('SITE_NAME') ? SITE_NAME : 'Ecom Store';
        $currency     = defined('CURRENCY') ? CURRENCY : '₹';
        $orderId      = $order['id'] ?? 'N/A';
        $orderNum     = $order['order_number'] ?? "#{$orderId}";
        $customerName = htmlspecialchars($order['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $total        = number_format((float)($order['total'] ?? 0), 2);
        $reason       = !empty($order['cancel_reason'])
            ? '<p style="color:#666;">Reason: ' . htmlspecialchars($order['cancel_reason'], ENT_QUOTES, 'UTF-8') . '</p>'
            : '';
        $siteUrl      = defined('SITE_URL') ? SITE_URL : '';

        $subject = "Order {$orderNum} Cancelled — {$siteName}";
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#14100d;'>
            <div style='text-align:center;padding:20px;background:#fcfafc;border-bottom:2px solid #dda74f;'>
                <h2 style='margin:0;color:#dda74f;font-family:Georgia,serif;'>{$siteName}</h2>
            </div>
            <div style='padding:30px 20px;background:#fff;'>
                <h3 style='margin-top:0;'>Order Cancellation Confirmed</h3>
                <p>Hi {$customerName},</p>
                <p>Your order <strong>{$orderNum}</strong> (total: {$currency}{$total}) has been successfully cancelled.</p>
                {$reason}
                <p style='color:#666;'>If you did not request this cancellation or have any questions, please contact us.</p>
                <p style='margin-top:24px;'>
                    <a href='{$siteUrl}/shop.php'
                       style='background:#dda74f;color:#fff;padding:12px 24px;border-radius:99px;text-decoration:none;font-weight:600;font-size:0.85rem;'>
                        Continue Shopping
                    </a>
                </p>
            </div>
            <div style='text-align:center;padding:20px;font-size:0.8em;color:#aaa;background:#fcfafc;'>
                &copy; " . date('Y') . " {$siteName}. All rights reserved.
            </div>
        </div>";

        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Send an order-cancellation alert to the admin.
     *
     * @param array $order  Order row from the database
     * @return bool
     */
    public function sendOrderCancellationToAdmin(array $order): bool
    {
        if (!filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteName     = defined('SITE_NAME') ? SITE_NAME : 'Ecom Store';
        $currency     = defined('CURRENCY') ? CURRENCY : '₹';
        $orderId      = $order['id'] ?? 'N/A';
        $orderNum     = $order['order_number'] ?? "#{$orderId}";
        $customerName = htmlspecialchars($order['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $customerEmail = htmlspecialchars($order['customer_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $total        = number_format((float)($order['total'] ?? 0), 2);
        $reason       = !empty($order['cancel_reason'])
            ? '<p><strong>Reason:</strong> ' . htmlspecialchars($order['cancel_reason'], ENT_QUOTES, 'UTF-8') . '</p>'
            : '';
        $siteUrl      = defined('SITE_URL') ? SITE_URL : '';

        $subject = "Order {$orderNum} Cancelled by Customer — {$siteName}";
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#14100d;'>
            <div style='padding:20px;background:#fcfafc;border-bottom:2px solid #dda74f;'>
                <h2 style='margin:0;color:#dda74f;'>Order Cancelled by Customer</h2>
            </div>
            <div style='padding:24px 20px;background:#fff;'>
                <p><strong>Order:</strong> {$orderNum}</p>
                <p><strong>Customer:</strong> {$customerName} ({$customerEmail})</p>
                <p><strong>Total:</strong> {$currency}{$total}</p>
                {$reason}
                <p style='margin-top:20px;'>
                    <a href='{$siteUrl}/admin/orders/view.php?id={$orderId}'
                       style='background:#dda74f;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;'>
                        View Order
                    </a>
                </p>
            </div>
            <div style='padding:16px;font-size:0.8em;color:#aaa;background:#fcfafc;text-align:center;'>
                &copy; " . date('Y') . " {$siteName}
            </div>
        </div>";

        return $this->sendEmail($this->adminEmail, $subject, $body);
    }

    public function sendPasswordReset(string $to, string $name, string $resetLink): bool
    {
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Ecom Store';
        $subject  = 'Reset Your Password — ' . $siteName;
        $body     = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#14100d;'>
            <div style='text-align:center;padding:20px;background:#fcfafc;border-bottom:2px solid #dda74f;'>
                <h2 style='margin:0;color:#dda74f;font-family:Georgia,serif;'>{$siteName}</h2>
            </div>
            <div style='padding:30px 20px;background:#ffffff;'>
                <h3 style='margin-top:0;'>Password Reset Request</h3>
                <p>Hi " . htmlspecialchars($name) . ",</p>
                <p>We received a request to reset your password. Click the button below to choose a new password. This link expires in 1 hour.</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='" . htmlspecialchars($resetLink) . "'
                       style='display:inline-block;padding:14px 32px;background:#14100d;color:#fff;text-decoration:none;border-radius:99px;font-weight:600;letter-spacing:0.05em;'>
                        Reset Password
                    </a>
                </div>
                <p style='color:#666;font-size:0.9em;'>If you did not request a password reset, you can ignore this email.</p>
                <p style='color:#999;font-size:0.8em;word-break:break-all;'>Or paste this link in your browser:<br>" . htmlspecialchars($resetLink) . "</p>
            </div>
            <div style='text-align:center;padding:20px;font-size:0.8em;color:#aaa;background:#fcfafc;'>
                &copy; " . date('Y') . " {$siteName}. All rights reserved.
            </div>
        </div>";

        return $this->sendEmail($to, $subject, $body);
    }
}

// ─── Standalone convenience functions ────────────────────────────────────────

/**
 * Send an order-placed confirmation email to the customer.
 *
 * @param int    $orderId    The order's primary key.
 * @param string $userEmail  The customer's email address.
 * @return bool
 */
function sendOrderPlacedEmail(int $orderId, string $userEmail): bool
{
    try {
        $order = db_row("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
        if (!$order) {
            return false;
        }
        $items = db_select("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
        $mailer = new EmailHandler();
        return $mailer->sendOrderInvoice($userEmail, $order, $items);
    } catch (Throwable) {
        return false;
    }
}

/**
 * Send an order-cancelled notification email to the customer.
 *
 * @param int    $orderId    The order's primary key.
 * @param string $userEmail  The customer's email address.
 * @return bool
 */
function sendOrderCancelledEmail(int $orderId, string $userEmail): bool
{
    try {
        $order = db_row("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
        if (!$order) {
            return false;
        }
        $mailer = new EmailHandler();
        return $mailer->sendOrderCancellationToCustomer($order);
    } catch (Throwable) {
        return false;
    }
}
