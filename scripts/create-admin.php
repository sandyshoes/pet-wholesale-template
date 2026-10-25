<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';
$username = trim($argv[1] ?? '');
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D', $username)) throw new RuntimeException('Usage: php scripts/create-admin.php USERNAME; password is read from stdin (12+ characters).');
fwrite(STDERR, "Password (stdin; terminal may echo, prefer a password-manager pipe): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12 || strlen($password) > 72) throw new RuntimeException('Password must be 12–72 bytes.');
app_db()->prepare('INSERT INTO admin_users(username,password_hash) VALUES (?,?)')->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
echo "Administrator created. No existing account changed.\n";
