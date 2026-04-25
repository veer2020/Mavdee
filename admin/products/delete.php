<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Invalid product ID.');
    header('Location: index.php'); exit;
}

try {
    $stmt = db()->prepare("SELECT name FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        setFlash('danger', 'Product not found.');
        header('Location: index.php'); exit;
    }

    db()->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    logAdminActivity('delete_product', "Deleted product ID $id: {$product['name']}");
    setFlash('success', 'Product "' . h($product['name']) . '" deleted.');
} catch (Throwable $e) {
    setFlash('danger', 'Could not delete product: ' . $e->getMessage());
}

header('Location: index.php');
exit;
