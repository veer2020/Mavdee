<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

$pageTitle = 'Settings';

// Settings fields configuration
$settingsConfig = [
    'site_name'            => ['label' => 'Site Name',              'type' => 'text',   'placeholder' => 'Mavdee'],
    'site_tagline'         => ['label' => 'Site Tagline',           'type' => 'text',   'placeholder' => 'Premium Occasionwear'],
    'site_logo'            => ['label' => 'Site Logo URL',          'type' => 'text',   'placeholder' => 'https://example.com/logo.png'],
    'currency'             => ['label' => 'Currency Symbol',        'type' => 'text',   'placeholder' => '₹'],
    'tax_rate'             => ['label' => 'Tax Rate (%)',           'type' => 'number', 'placeholder' => '0', 'step' => '0.01'],
    'shipping_cost'        => ['label' => 'Shipping Cost',          'type' => 'number', 'placeholder' => '0', 'step' => '0.01'],
    'free_shipping_above'  => ['label' => 'Free Shipping Above',    'type' => 'number', 'placeholder' => '999', 'step' => '0.01'],
    'contact_email'        => ['label' => 'Contact Email',          'type' => 'email',  'placeholder' => 'support@example.com'],
    'contact_phone'        => ['label' => 'Contact Phone',          'type' => 'text',   'placeholder' => '+91 98765 43210'],
    'contact_whatsapp'     => ['label' => 'WhatsApp Number',        'type' => 'text',   'placeholder' => '+91 98765 43210 (leave blank to hide)'],
    'contact_address'      => ['label' => 'Contact Address',        'type' => 'textarea', 'placeholder' => '123 Fashion Street, Mumbai'],
];

// Banner / appearance keys to also save
$bannerKeys = [
    'home_banner_1_img',
    'home_banner_1_title',
    'home_banner_1_subtitle',
    'home_banner_1_link',
    'home_banner_2_img',
    'home_banner_2_title',
    'home_banner_2_subtitle',
    'home_banner_2_link',
    'home_banner_3_img',
    'home_banner_3_title',
    'home_banner_3_subtitle',
    'home_banner_3_link',
    'promo_strip_text',
    'promo_strip_code',
    'cashback_text',
];

// Payment gateway keys to also save
$paymentKeys = [
    'razorpay_enabled',
    'razorpay_key_id',
    'razorpay_key_secret',
    'cod_enabled',
    'cod_fee',
    'delhivery_enabled',
    'delhivery_token',
    'delhivery_warehouse_pin',
    'delhivery_client_name',
];

// Email / notification keys to also save
$emailKeys = [
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
    'forgot_password_enabled',
];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    try {
        $saved = 0;
        foreach (array_keys($settingsConfig) as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt = db()->prepare(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            );
            $stmt->execute([$key, $value]);
            $saved++;
        }
        // Save payment gateway settings
        foreach ($paymentKeys as $key) {
            // Checkboxes send nothing when unchecked — treat missing as '0'
            $value = isset($_POST[$key]) ? trim($_POST[$key]) : '0';
            $stmt = db()->prepare(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            );
            $stmt->execute([$key, $value]);
            $saved++;
        }
        // Save email / notification settings
        $emailCheckboxKeys = ['mail_notify_admin', 'mail_notify_customer', 'forgot_password_enabled'];
        foreach ($emailKeys as $key) {
            if (in_array($key, $emailCheckboxKeys, true)) {
                // Checkboxes send nothing when unchecked — treat missing as '0'
                $value = isset($_POST[$key]) ? trim($_POST[$key]) : '0';
            } else {
                $value = trim($_POST[$key] ?? '');
            }
            $stmt = db()->prepare(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            );
            $stmt->execute([$key, $value]);
            $saved++;
        }
        // Save banner / appearance settings
        foreach ($bannerKeys as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt = db()->prepare(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            );
            $stmt->execute([$key, $value]);
            $saved++;
        }
        logAdminActivity('update_settings', "Updated $saved setting(s)");
        setFlash('success', 'Settings saved successfully.');
    } catch (Throwable $e) {
        setFlash('danger', 'Failed to save settings: ' . $e->getMessage());
    }

    header('Location: index.php');
    exit;
}

// Load current settings from DB
$settings = [];
try {
    $rows = db()->query("SELECT `key`, `value` FROM settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Throwable) {
}

// Fallback defaults
$defaults = [
    'site_name'                 => defined('SITE_NAME') ? SITE_NAME : '',
    'site_tagline'              => defined('SITE_TAGLINE') ? SITE_TAGLINE : '',
    'site_logo'                 => '',
    'currency'                  => defined('CURRENCY') ? CURRENCY : '₹',
    'tax_rate'                  => '0',
    'shipping_cost'             => '0',
    'free_shipping_above'       => '999',
    'contact_email'             => '',
    'contact_phone'             => '',
    'contact_whatsapp'          => '',
    'contact_address'           => '',
    'razorpay_enabled'          => '0',
    'razorpay_key_id'           => '',
    'razorpay_key_secret'       => '',
    'cod_enabled'               => '1',
    'cod_fee'                   => '0',
    'delhivery_enabled'         => '0',
    'delhivery_token'           => '',
    'delhivery_warehouse_pin'   => '',
    'delhivery_client_name'     => '',
    // email
    'mail_driver'               => 'mail',
    'mail_from_name'            => defined('SITE_NAME') ? SITE_NAME : '',
    'mail_from_email'           => '',
    'mail_admin_email'          => '',
    'smtp_host'                 => '',
    'smtp_port'                 => '587',
    'smtp_encryption'           => 'tls',
    'smtp_username'             => '',
    'smtp_password'             => '',
    'mail_notify_admin'         => '1',
    'mail_notify_customer'      => '1',
    // banners
    'home_banner_1_img'     => '',
    'home_banner_1_title' => '',
    'home_banner_1_subtitle' => '',
    'home_banner_1_link' => '',
    'home_banner_2_img'     => '',
    'home_banner_2_title' => '',
    'home_banner_2_subtitle' => '',
    'home_banner_2_link' => '',
    'home_banner_3_img'     => '',
    'home_banner_3_title' => '',
    'home_banner_3_subtitle' => '',
    'home_banner_3_link' => '',
    'promo_strip_text'      => 'FLAT ₹300 OFF on orders above ₹1499 · USE CODE',
    'promo_strip_code'      => 'SAVE300',
    'cashback_text'         => 'Flat 7.5% Cashback with HDFC & SBI Credit Cards on orders above ₹999',
];
foreach ($defaults as $k => $v) {
    if (!isset($settings[$k])) $settings[$k] = $v;
}

include __DIR__ . '/../_layout_head.php';
?>

<div class="page-header">
    <h1><i class="bi bi-gear me-2" style="color:#f8c146"></i>Site Settings</h1>
</div>

<style>
    .dragover {
        border-color: #0d6efd !important;
        background-color: #f0f8ff !important;
    }
</style>

<form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="row g-3">
        <!-- General Settings -->
        <div class="col-lg-6">
            <div class="admin-card mb-3">
                <h6 class="mb-4" style="font-weight:700;"><i class="bi bi-shop me-2"></i>General</h6>

                <?php foreach (['site_name', 'site_tagline', 'currency'] as $key):
                    $cfg = $settingsConfig[$key];
                ?>
                    <div class="mb-3">
                        <label class="form-label"><?= h($cfg['label']) ?></label>
                        <input
                            type="<?= h($cfg['type']) ?>"
                            name="<?= h($key) ?>"
                            class="form-control"
                            value="<?= h($settings[$key] ?? '') ?>"
                            placeholder="<?= h($cfg['placeholder'] ?? '') ?>">
                    </div>
                <?php endforeach; ?>

                <!-- Site Logo URL -->
                <div class="mb-3">
                    <label class="form-label">Site Logo <small class="text-muted">(leave blank to use default "M" text)</small></label>
                    <?php $logoImg = $settings['site_logo'] ?? ''; ?>
                    <input type="hidden" id="imageUrl4" name="site_logo" value="<?= h($logoImg) ?>">
                    <div id="dropArea4" class="border rounded p-3 text-center" style="cursor:pointer; border-style:dashed !important; background:#fafafa; <?= $logoImg ? 'display:none;' : 'display:flex; flex-direction:column; align-items:center;' ?>">
                        <input type="file" class="file-input" accept="image/*" style="display:none;">
                        <i class="bi bi-cloud-arrow-up fs-4 text-muted mb-1"></i>
                        <span class="small text-muted">Click or drag logo here</span>
                    </div>
                    <div id="preview4" class="position-relative mt-2" style="display:inline-block; <?= $logoImg ? 'display:block;' : 'display:none;' ?>">
                        <img src="<?= h($logoImg) ?>" class="preview-img" style="height:40px;max-width:160px;object-fit:contain;border:1px solid #eaeaec;border-radius:4px;padding:4px;background:#fff;">
                        <button type="button" class="remove-img btn btn-sm btn-danger position-absolute top-0 end-0 m-1" style="padding: 2px 6px; line-height: 1;"><i class="bi bi-x"></i></button>
                    </div>
                </div>
            </div>

            <!-- Shipping & Tax -->
            <div class="admin-card mb-3">
                <h6 class="mb-4" style="font-weight:700;"><i class="bi bi-truck me-2"></i>Shipping & Tax</h6>

                <?php foreach (['tax_rate', 'shipping_cost', 'free_shipping_above'] as $key):
                    $cfg = $settingsConfig[$key];
                ?>
                    <div class="mb-3">
                        <label class="form-label"><?= h($cfg['label']) ?></label>
                        <div class="input-group">
                            <?php if (in_array($key, ['shipping_cost', 'free_shipping_above'])): ?>
                                <span class="input-group-text"><?= h(CURRENCY) ?></span>
                            <?php endif; ?>
                            <input
                                type="<?= h($cfg['type']) ?>"
                                name="<?= h($key) ?>"
                                class="form-control"
                                step="<?= h($cfg['step'] ?? '1') ?>"
                                min="0"
                                value="<?= h($settings[$key] ?? '') ?>"
                                placeholder="<?= h($cfg['placeholder'] ?? '') ?>">
                            <?php if ($key === 'tax_rate'): ?>
                                <span class="input-group-text">%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Contact Settings -->
        <div class="col-lg-6">
            <div class="admin-card mb-3">
                <h6 class="mb-4" style="font-weight:700;"><i class="bi bi-envelope me-2"></i>Contact Information</h6>

                <?php foreach (['contact_email', 'contact_phone', 'contact_whatsapp'] as $key):
                    $cfg = $settingsConfig[$key];
                ?>
                    <div class="mb-3">
                        <label class="form-label"><?= h($cfg['label']) ?></label>
                        <input
                            type="<?= h($cfg['type']) ?>"
                            name="<?= h($key) ?>"
                            class="form-control"
                            value="<?= h($settings[$key] ?? '') ?>"
                            placeholder="<?= h($cfg['placeholder'] ?? '') ?>">
                    </div>
                <?php endforeach; ?>

                <div class="mb-3">
                    <label class="form-label"><?= h($settingsConfig['contact_address']['label']) ?></label>
                    <textarea
                        name="contact_address"
                        class="form-control"
                        rows="3"
                        placeholder="<?= h($settingsConfig['contact_address']['placeholder']) ?>"><?= h($settings['contact_address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- System info card -->
            <div class="admin-card mb-3" style="background:#f9fafb;">
                <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-info-circle me-2"></i>System Info</h6>
                <dl class="row mb-0" style="font-size:.875rem; row-gap:.25rem;">
                    <dt class="col-6 text-muted fw-normal">PHP Version</dt>
                    <dd class="col-6 mb-0"><?= h(PHP_VERSION) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Server</dt>
                    <dd class="col-6 mb-0"><?= h($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></dd>
                    <dt class="col-6 text-muted fw-normal">Admin</dt>
                    <dd class="col-6 mb-0"><?= h(getAdminName()) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Role</dt>
                    <dd class="col-6 mb-0"><?= h(getAdminRole()) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Payment Gateways -->
        <div class="col-12">
            <div class="admin-card mb-3">
                <h6 class="mb-4" style="font-weight:700;"><i class="bi bi-image me-2"></i>Home Page Banners &amp; Appearance</h6>
                <div class="row g-3">
                    <!-- Promo Strip -->
                    <div class="col-12">
                        <div class="p-3 border rounded">
                            <p class="fw-semibold mb-2">Promo Strip (top bar on home page)</p>
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label form-label-sm">Text</label>
                                    <input type="text" name="promo_strip_text" class="form-control form-control-sm" value="<?= h($settings['promo_strip_text'] ?? '') ?>" placeholder="FLAT ₹300 OFF on orders above ₹1499">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label form-label-sm">Coupon Code</label>
                                    <input type="text" name="promo_strip_code" class="form-control form-control-sm" value="<?= h($settings['promo_strip_code'] ?? '') ?>" placeholder="SAVE300">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Cashback Strip -->
                    <div class="col-12">
                        <div class="p-3 border rounded">
                            <label class="form-label fw-semibold">Cashback Strip Text (below hero carousel)</label>
                            <input type="text" name="cashback_text" class="form-control form-control-sm" value="<?= h($settings['cashback_text'] ?? '') ?>" placeholder="Flat 7.5% Cashback with HDFC &amp; SBI Credit Cards">
                        </div>
                    </div>
                    <!-- Banner 1 -->
                    <?php for ($bi = 1; $bi <= 3; $bi++): ?>
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100">
                                <p class="fw-semibold mb-2">Banner <?= $bi ?></p>
                                <div class="mb-2">
                                    <label class="form-label form-label-sm">Image</label>
                                    <?php $bImg = $settings["home_banner_{$bi}_img"] ?? ''; ?>
                                    <input type="hidden" id="imageUrl<?= $bi ?>" name="home_banner_<?= $bi ?>_img" value="<?= h($bImg) ?>">
                                    <div id="dropArea<?= $bi ?>" class="border rounded p-3 text-center" style="cursor:pointer; border-style:dashed !important; background:#fafafa; <?= $bImg ? 'display:none;' : 'display:flex; flex-direction:column; align-items:center;' ?>">
                                        <input type="file" class="file-input" accept="image/*" style="display:none;">
                                        <i class="bi bi-cloud-arrow-up fs-4 text-muted mb-1"></i>
                                        <span class="small text-muted">Click or drag image here</span>
                                    </div>
                                    <div id="preview<?= $bi ?>" class="position-relative mt-1" style="<?= $bImg ? 'display:block;' : 'display:none;' ?>">
                                        <img src="<?= h($bImg) ?>" class="preview-img img-fluid w-100" style="height:auto; border-radius:4px;">
                                        <button type="button" class="remove-img btn btn-sm btn-danger position-absolute top-0 end-0 m-1" style="padding: 2px 6px; line-height: 1;"><i class="bi bi-x"></i></button>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label form-label-sm">Title</label>
                                    <input type="text" name="home_banner_<?= $bi ?>_title" class="form-control form-control-sm" value="<?= h($settings["home_banner_{$bi}_title"] ?? '') ?>" placeholder="Summer Sale">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label form-label-sm">Subtitle</label>
                                    <input type="text" name="home_banner_<?= $bi ?>_subtitle" class="form-control form-control-sm" value="<?= h($settings["home_banner_{$bi}_subtitle"] ?? '') ?>" placeholder="Up to 50% off">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label form-label-sm">Link URL</label>
                                    <input type="text" name="home_banner_<?= $bi ?>_link" class="form-control form-control-sm" value="<?= h($settings["home_banner_{$bi}_link"] ?? '') ?>" placeholder="shop.php">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Payment Gateways -->
        <div class="col-12">
            <div class="admin-card mb-3">
                <h6 class="mb-4" style="font-weight:700;"><i class="bi bi-credit-card me-2"></i>Payment Gateways</h6>
                <div class="row g-3">

                    <!-- Razorpay -->
                    <div class="col-md-4">
                        <div class="p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Razorpay</strong>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="razorpay_enabled" id="rzp_enabled" value="1"
                                        <?= !empty($settings['razorpay_enabled']) && $settings['razorpay_enabled'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rzp_enabled">Enable</label>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Key ID</label>
                                <input type="text" name="razorpay_key_id" class="form-control form-control-sm" value="<?= h($settings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_live_…">
                            </div>
                            <div class="mb-0">
                                <label class="form-label form-label-sm">Key Secret</label>
                                <input type="password" name="razorpay_key_secret" class="form-control form-control-sm" value="<?= h($settings['razorpay_key_secret'] ?? '') ?>" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <!-- COD -->
                    <div class="col-md-4">
                        <div class="p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Cash on Delivery</strong>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="cod_enabled" id="cod_enabled" value="1"
                                        <?= !empty($settings['cod_enabled']) && $settings['cod_enabled'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="cod_enabled">Enable</label>
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label form-label-sm">Handling Fee (<?= h(CURRENCY) ?>)</label>
                                <input type="number" name="cod_fee" class="form-control form-control-sm" step="0.01" min="0" value="<?= h($settings['cod_fee'] ?? '0') ?>" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- Delhivery -->
                    <div class="col-md-4">
                        <div class="p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Delhivery</strong>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="delhivery_enabled" id="dlv_enabled" value="1"
                                        <?= !empty($settings['delhivery_enabled']) && $settings['delhivery_enabled'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="dlv_enabled">Enable</label>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">API Token</label>
                                <input type="password" name="delhivery_token" class="form-control form-control-sm" value="<?= h($settings['delhivery_token'] ?? '') ?>" placeholder="Token…">
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Warehouse Pincode</label>
                                <input type="text" name="delhivery_warehouse_pin" class="form-control form-control-sm" value="<?= h($settings['delhivery_warehouse_pin'] ?? '') ?>" placeholder="400001">
                            </div>
                            <div class="mb-0">
                                <label class="form-label form-label-sm">Client Name</label>
                                <input type="text" name="delhivery_client_name" class="form-control form-control-sm" value="<?= h($settings['delhivery_client_name'] ?? '') ?>" placeholder="Your Business Name">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- end .row -->

    <!-- Email & Notifications -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="admin-card mb-3">
                <h6 class="mb-4" style="font-weight:700;"><i class="bi bi-envelope-at me-2"></i>Email &amp; Notifications</h6>

                <div class="row g-3">
                    <!-- Mail driver + From fields -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Mail Driver</label>
                            <select name="mail_driver" id="mailDriver" class="form-select">
                                <option value="mail" <?= ($settings['mail_driver'] ?? 'mail') === 'mail'  ? 'selected' : '' ?>>PHP mail()</option>
                                <option value="smtp" <?= ($settings['mail_driver'] ?? 'mail') === 'smtp'  ? 'selected' : '' ?>>SMTP</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">From Name</label>
                            <input type="text" name="mail_from_name" class="form-control" value="<?= h($settings['mail_from_name'] ?? '') ?>" placeholder="<?= h(defined('SITE_NAME') ? SITE_NAME : 'Ecom Store') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">From Email</label>
                            <input type="email" name="mail_from_email" class="form-control" value="<?= h($settings['mail_from_email'] ?? '') ?>" placeholder="noreply@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Admin Notification Email</label>
                            <input type="email" name="mail_admin_email" class="form-control" value="<?= h($settings['mail_admin_email'] ?? '') ?>" placeholder="admin@example.com">
                        </div>
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="mail_notify_admin" id="notifyAdmin" value="1"
                                    <?= !empty($settings['mail_notify_admin']) && $settings['mail_notify_admin'] !== '0' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyAdmin">Email admin on new order</label>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="mail_notify_customer" id="notifyCustomer" value="1"
                                    <?= !empty($settings['mail_notify_customer']) && $settings['mail_notify_customer'] !== '0' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyCustomer">Email customer on status change</label>
                            </div>
                        </div>
                        <div class="mb-0 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="forgot_password_enabled" id="forgotPasswordEnabled" value="1"
                                    <?= ($settings['forgot_password_enabled'] ?? '1') !== '0' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="forgotPasswordEnabled">Enable "Forgot Password" feature for customers</label>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP settings (greyed out when PHP mail() is selected) -->
                    <div class="col-md-6" id="smtpBlock">
                        <div class="p-3 border rounded" id="smtpFields">
                            <p class="mb-3 fw-semibold">SMTP Configuration</p>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= h($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Port</label>
                                    <input type="number" name="smtp_port" class="form-control form-control-sm" value="<?= h($settings['smtp_port'] ?? '587') ?>" placeholder="587" min="1" max="65535">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Encryption</label>
                                    <select name="smtp_encryption" class="form-select form-select-sm">
                                        <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                                        <option value="ssl" <?= ($settings['smtp_encryption'] ?? 'tls') === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                                        <option value="none" <?= ($settings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Username</label>
                                <input type="text" name="smtp_username" class="form-control form-control-sm" value="<?= h($settings['smtp_username'] ?? '') ?>" placeholder="you@gmail.com" autocomplete="off">
                            </div>
                            <div class="mb-0">
                                <label class="form-label form-label-sm">Password / App Password</label>
                                <input type="password" name="smtp_password" class="form-control form-control-sm" value="<?= h($settings['smtp_password'] ?? '') ?>" placeholder="••••••••" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        (function() {
            var driver = document.getElementById('mailDriver');
            var block = document.getElementById('smtpFields');

            function toggle() {
                var isSMTP = driver.value === 'smtp';
                block.style.opacity = isSMTP ? '1' : '0.4';
                block.querySelectorAll('input,select').forEach(function(el) {
                    el.disabled = !isSMTP;
                });
            }
            driver.addEventListener('change', toggle);
            toggle();
        })();
    </script>

    <div class="d-flex justify-content-end gap-2 mt-2">
        <button type="reset" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
        </button>
        <button type="submit" class="btn btn-accent">
            <i class="bi bi-save me-2"></i>Save Settings
        </button>
    </div>
</form>

<script src="../assets/js/image-upload.js"></script>
<?php include __DIR__ . '/../_layout_foot.php'; ?>