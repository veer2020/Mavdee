<?php
/**
 * includes/crm.php
 * CRMMailer — Lifecycle email automation using EmailHandler.
 * Branded HTML email templates with luxury aesthetic (maroon/gold).
 */
declare(strict_types=1);

require_once __DIR__ . '/email.php';

class CRMMailer
{
    private EmailHandler $mailer;

    private string $siteName;
    private string $siteUrl;

    public function __construct()
    {
        $this->mailer   = new EmailHandler();
        $this->siteName = defined('SITE_NAME') ? SITE_NAME : 'Mavdee';
        $this->siteUrl  = defined('SITE_URL')  ? SITE_URL  : '';
    }

    // ── Branded email wrapper ─────────────────────────────────────────────────
    private function wrap(string $bodyHtml): string
    {
        $name = htmlspecialchars($this->siteName);
        $year = date('Y');
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
        </head>
        <body style="margin:0;padding:0;background:#f3efea;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td align="center" style="padding:32px 16px;">
              <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:4px;overflow:hidden;">
                <!-- Header -->
                <tr><td style="background:#14100d;padding:28px 40px;text-align:center;">
                  <span style="font-family:Georgia,'Times New Roman',serif;font-size:2rem;color:#c9a96e;letter-spacing:.06em;">{$name}</span>
                </td></tr>
                <!-- Body -->
                <tr><td style="padding:40px;">
                  {$bodyHtml}
                </td></tr>
                <!-- Footer -->
                <tr><td style="background:#f3efea;padding:24px 40px;text-align:center;font-size:.78rem;color:#8a7b6f;">
                  &copy; {$year} {$name}. All rights reserved.<br>
                  <a href="{$this->siteUrl}" style="color:#8b1a2e;text-decoration:none;">{$this->siteUrl}</a>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }

    private function btn(string $url, string $label): string
    {
        return "<div style='text-align:center;margin:28px 0;'>"
             . "<a href='" . htmlspecialchars($url) . "' "
             . "style='display:inline-block;padding:14px 36px;background:#14100d;color:#fff;"
             . "text-decoration:none;font-weight:700;font-size:.88rem;text-transform:uppercase;"
             . "letter-spacing:.1em;border-radius:99px;'>{$label}</a></div>";
    }

    private function heading(string $text): string
    {
        return "<h2 style='font-family:Georgia,serif;font-size:1.8rem;color:#14100d;margin:0 0 16px;font-weight:500;'>{$text}</h2>";
    }

    private function para(string $text): string
    {
        return "<p style='margin:0 0 16px;font-size:.95rem;color:#3a3a3a;line-height:1.7;'>{$text}</p>";
    }

    // ── 1. Welcome email ─────────────────────────────────────────────────────
    /**
     * Triggered on new customer registration.
     */
    public function sendWelcomeEmail(string $email, string $name): bool
    {
        $firstName = htmlspecialchars(explode(' ', trim($name))[0]);
        $code      = 'WELCOME10';
        $shopUrl   = $this->siteUrl . '/shop.php';

        $body = $this->heading("Welcome to {$this->siteName}, {$firstName}!")
              . $this->para("We're thrilled to have you with us. As a thank you for joining our family, enjoy <strong>10% off</strong> your first order with the code below.")
              . "<div style='text-align:center;margin:24px 0;'>"
              .   "<span style='display:inline-block;padding:14px 32px;background:#f3efea;border:2px dashed #c9a96e;"
              .           "font-family:Georgia,serif;font-size:1.3rem;letter-spacing:.16em;color:#8b1a2e;font-weight:700;'>"
              .     htmlspecialchars($code)
              .   "</span>"
              . "</div>"
              . $this->para("Shop our latest arrivals and discover pieces crafted for the modern woman.")
              . $this->btn($shopUrl, 'Shop the Collection');

        return $this->mailer->sendEmail($email, "Welcome to " . $this->siteName . " 🎉", $this->wrap($body));
    }

    // ── 2. Abandoned cart email ───────────────────────────────────────────────
    /**
     * Sent to users with items in cart not ordered in 24 h.
     */
    public function sendAbandonedCartEmail(
        string $email,
        string $name,
        array $cartItems,
        string $coupon = ''
    ): bool {
        $firstName = htmlspecialchars(explode(' ', trim($name))[0]);
        $cartUrl   = $this->siteUrl . '/checkout.php';

        $itemsHtml = '';
        $total     = 0.0;
        foreach ($cartItems as $item) {
            $itemName  = htmlspecialchars($item['name'] ?? 'Product');
            $qty       = (int)($item['qty'] ?? $item['quantity'] ?? 1);
            $price     = (float)($item['price'] ?? 0);
            $lineTotal = $price * $qty;
            $total    += $lineTotal;
            $imgSrc    = htmlspecialchars(img_url($item['image_url'] ?? ''));

            $itemsHtml .= "<tr>"
                . "<td style='padding:12px 0;border-bottom:1px solid #f0ebe5;'>"
                . ($imgSrc ? "<img src='{$imgSrc}' width='60' height='75' style='object-fit:cover;display:block;' alt=''>" : '')
                . "</td>"
                . "<td style='padding:12px 16px;border-bottom:1px solid #f0ebe5;font-size:.9rem;color:#3a3a3a;'>"
                . "<strong>{$itemName}</strong><br>Qty: {$qty}"
                . "</td>"
                . "<td style='padding:12px 0;border-bottom:1px solid #f0ebe5;font-size:.9rem;text-align:right;color:#14100d;font-weight:600;'>"
                . htmlspecialchars(CURRENCY . number_format($lineTotal, 0))
                . "</td></tr>";
        }

        $couponHtml = '';
        if ($coupon !== '') {
            $couponHtml = "<div style='text-align:center;margin:20px 0;'>"
                       .  "<p style='font-size:.88rem;color:#8a7b6f;margin:0 0 8px;'>Use code for an extra discount:</p>"
                       .  "<span style='display:inline-block;padding:10px 24px;background:#f3efea;border:2px dashed #c9a96e;"
                       .          "font-family:Georgia,serif;font-size:1.1rem;letter-spacing:.12em;color:#8b1a2e;font-weight:700;'>"
                       .     htmlspecialchars($coupon)
                       .  "</span></div>";
        }

        $body = $this->heading("You left something behind, {$firstName}!")
              . $this->para("Your cart is waiting. Limited stock — don't miss out on the pieces you loved.")
              . "<table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:20px;'>{$itemsHtml}</table>"
              . "<p style='text-align:right;font-size:1rem;font-weight:700;color:#14100d;margin:0 0 20px;'>"
              . "Total: " . htmlspecialchars(CURRENCY . number_format($total, 0))
              . "</p>"
              . $couponHtml
              . $this->btn($cartUrl, 'Complete Your Purchase');

        return $this->mailer->sendEmail($email, "Your cart is waiting — items are running low 🛒", $this->wrap($body));
    }

    // ── 3. Order shipped email ────────────────────────────────────────────────
    public function sendOrderShippedEmail(
        string $email,
        string $name,
        string $orderNumber,
        string $trackingUrl
    ): bool {
        $firstName = htmlspecialchars(explode(' ', trim($name))[0]);
        $orderNum  = htmlspecialchars($orderNumber);

        $body = $this->heading("Your order is on its way, {$firstName}!")
              . $this->para("Great news — your order <strong>#{$orderNum}</strong> has been dispatched and is en route to you.")
              . $this->para("Track your shipment in real time using the button below.")
              . $this->btn($trackingUrl, 'Track My Order')
              . $this->para("If you have any questions, simply reply to this email and we'll be happy to help.");

        return $this->mailer->sendEmail($email, "Your order #{$orderNumber} is on its way! 📦", $this->wrap($body));
    }

    // ── 4. Review request email ───────────────────────────────────────────────
    /**
     * Sent ~7 days after delivery to request product reviews.
     */
    public function sendReviewRequestEmail(
        string $email,
        string $name,
        array $items,
        string $orderId
    ): bool {
        $firstName = htmlspecialchars(explode(' ', trim($name))[0]);
        $reviewUrl = $this->siteUrl . '/dashboard.php#orders';

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemName = htmlspecialchars($item['name'] ?? 'your purchase');
            $imgSrc   = htmlspecialchars(img_url($item['image_url'] ?? ''));
            $productId = (int)($item['product_id'] ?? 0);
            $rateUrl  = $this->siteUrl . '/product.php?id=' . $productId . '#reviews';

            $itemsHtml .= "<tr><td style='padding:10px 0;border-bottom:1px solid #f0ebe5;'>"
                       . ($imgSrc ? "<img src='{$imgSrc}' width='50' height='62' style='object-fit:cover;' alt=''>" : '')
                       . "</td><td style='padding:10px 16px;border-bottom:1px solid #f0ebe5;font-size:.9rem;color:#3a3a3a;'>"
                       . "<strong>{$itemName}</strong>"
                       . "</td><td style='padding:10px 0;border-bottom:1px solid #f0ebe5;text-align:right;'>"
                       . "<a href='" . htmlspecialchars($rateUrl) . "' style='font-size:.82rem;color:#8b1a2e;font-weight:600;text-decoration:none;'>Rate →</a>"
                       . "</td></tr>";
        }

        $body = $this->heading("How was your order, {$firstName}?")
              . $this->para("We hope you love your recent purchase! Your honest review helps other shoppers make the right choice.")
              . "<table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:24px;'>{$itemsHtml}</table>"
              . $this->btn($reviewUrl, 'Leave a Review');

        return $this->mailer->sendEmail($email, "Share your thoughts on your recent order ⭐", $this->wrap($body));
    }

    // ── 5. Back-in-stock notification ────────────────────────────────────────
    /**
     * Sent when a wishlisted product comes back into stock.
     */
    public function sendBackInStockNotification(string $email, string $name, array $products): bool
    {
        $firstName = htmlspecialchars(explode(' ', trim($name))[0]);
        $shopUrl   = $this->siteUrl . '/shop.php';

        $itemsHtml = '<ul style="padding:0;margin:0 0 20px;list-style:none;">';
        foreach ($products as $product) {
            $productName = htmlspecialchars($product['name'] ?? 'Product');
            $productUrl  = htmlspecialchars($this->siteUrl . '/product.php?id=' . (int)($product['id'] ?? 0));
            $itemsHtml  .= "<li style='padding:8px 0;border-bottom:1px solid #f0ebe5;'>"
                         . "<a href='{$productUrl}' style='color:#8b1a2e;text-decoration:none;font-weight:600;'>{$productName}</a>"
                         . "</li>";
        }
        $itemsHtml .= '</ul>';

        $body = $this->heading("Back in Stock: Items You Love, {$firstName}!")
              . $this->para("Good news! The following items from your wishlist are back in stock. Grab them before they sell out again.")
              . $itemsHtml
              . $this->btn($shopUrl, 'Shop Now');

        return $this->mailer->sendEmail(
            $email,
            "Back in Stock at {$this->siteName} 🎉",
            $this->wrap($body)
        );
    }
}
