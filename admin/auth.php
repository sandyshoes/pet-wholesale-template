<?php
require_once __DIR__ . '/db.php';
app_session();
function is_admin_logged_in(): bool { return !empty($_SESSION['admin_user']['id']); }
function require_admin(): void {
    if (!is_admin_logged_in()) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) api_response(['success'=>false,'message'=>'请先登录'],401);
        header('Location: ' . app_base() . '/admin/login.php'); exit;
    }
    $check = db()->prepare('SELECT id FROM admin_users WHERE id = ?');
    $check->execute([$_SESSION['admin_user']['id']]);
    if (!$check->fetch()) { $_SESSION = []; api_response(['success'=>false,'message'=>'账号已失效'],401); }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { require_post(); check_csrf(); }
}
function require_admin_login(): void { require_admin(); }
function require_admin_page(): void { require_admin(); }
function admin_user(): ?array { return $_SESSION['admin_user'] ?? null; }
