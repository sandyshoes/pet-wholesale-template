<?php
require_once __DIR__ . '/app/bootstrap.php';
$pdo = app_db();
function json_out(int $code, string $message, mixed $data = null): never {
    api_response(['success' => $code >= 200 && $code < 300, 'message' => $message, 'data' => $data], $code);
}
