<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function decode_json($value, $default) {
    if ($value === null || $value === '') return $default;
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;
    return $default;
}

try {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') {
        json_out(['success' => false, 'message' => '缺少商品ID'], 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_out(['success' => false, 'message' => '商品不存在'], 404);
    }

    $row['images'] = decode_json($row['images'] ?? '', []);
    $row['colors'] = decode_json($row['colors'] ?? '', []);
    $row['sizes']  = decode_json($row['sizes'] ?? '', []);
    $row['specs']  = decode_json($row['specs'] ?? '', new stdClass());
    $row['featured'] = (int)($row['featured'] ?? 0);

    json_out(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
    json_out(['success' => false, 'message' => '读取失败：' . $e->getMessage()], 500);
}
