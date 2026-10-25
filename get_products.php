<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product_fields.php';

try {
    $stmt = $pdo->query("SELECT * FROM products WHERE status = 'on' ORDER BY created_at DESC, id DESC");
    json_out(200, 'ok', array_map('public_product', $stmt->fetchAll()));
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_out(500, '商品加载失败');
}
