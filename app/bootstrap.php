<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$envFile = getenv('APP_ENV_FILE') ?: APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $index => $line) {
        $line = trim($index === 0 ? preg_replace('/^\xEF\xBB\xBF/', '', $line) : $line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)) throw new RuntimeException('Invalid environment key on line ' . ($index + 1));
        $value = trim($value);
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && $value[strlen($value)-1] === $value[0]) $value = substr($value, 1, -1);
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $error): void {
    error_log($error->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
    api_response(['success' => false, 'message' => '服务暂不可用，请检查实例配置或联系管理员'], 503);
});

function setting(string $name, ?string $default = null): string {
    $value = getenv($name);
    if ($value === false) {
        if ($default !== null) return $default;
        throw new RuntimeException('Missing environment variable: ' . $name);
    }
    return $value;
}
function app_base(): string {
    return rtrim((string)(parse_url(setting('APP_URL'), PHP_URL_PATH) ?? ''), '/');
}
function app_db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . setting('DB_HOST') . ';port=' . setting('DB_PORT', '3306') . ';dbname=' . setting('DB_NAME') . ';charset=utf8mb4', setting('DB_USER'), setting('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function api_response(array $body, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function app_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('pet_' . substr(hash('sha256', setting('DB_HOST') . setting('DB_NAME') . setting('APP_URL')), 0, 20));
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => app_base() . '/', 'secure' => setting('SESSION_SECURE', '0') === '1', 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) throw new RuntimeException('Session storage unavailable');
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 3600) $_SESSION = [];
    $_SESSION['last_seen'] = time();
}
function csrf_token(): string {
    app_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function check_csrf(): void {
    app_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) api_response(['success' => false, 'message' => '会话校验失败，请刷新页面'], 403);
}
function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        api_response(['success' => false, 'message' => 'Only POST allowed'], 405);
    }
}
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function brand(): array {
    $defaults = json_decode(file_get_contents(APP_ROOT . '/config/brand.example.json'), true, 512, JSON_THROW_ON_ERROR);
    $file = APP_ROOT . '/' . setting('BRAND_FILE', 'config/brand.local.json');
    return is_file($file) ? array_replace($defaults, json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)) : $defaults;
}
function feature_unavailable(): never { api_response(['success' => false, 'message' => '在线账户、订单与支付模块尚未启用，请通过联系页面询价。'], 501); }
