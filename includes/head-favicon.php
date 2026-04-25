<?php
/**
 * includes/head-favicon.php
 * Outputs a <link rel="icon"> tag for every page.
 * Uses the admin-configured site_favicon setting when available,
 * otherwise falls back to an inline SVG so browsers never hit /favicon.ico.
 * Include this file inside <head> on every public-facing page.
 */
if (!function_exists('getSetting')) {
    return; // Safety guard — getSetting() comes from config/config.php
}
$_faviconUrl = getSetting('site_favicon', '');
?>
<?php if (!empty($_faviconUrl)): ?>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($_faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php else: ?>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛍️</text></svg>">
<?php endif; ?>
