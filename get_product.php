<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product_fields.php';

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') {
    json_out(400, '缺少商品ID');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'on' LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_out(404, '商品不存在或已下架');
    }
    json_out(200, 'ok', public_product($row));
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_out(500, '商品加载失败');
}
