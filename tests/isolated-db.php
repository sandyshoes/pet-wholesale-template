<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !getenv('APP_ENV_FILE')) { http_response_code(404);exit(1); }
require_once dirname(__DIR__).'/app/bootstrap.php';
$database=$argv[2]??''; $username=$argv[3]??'';
if (!preg_match('/^pet_template_test_[a-f0-9]{12}_[ab]$/D',$database) || !preg_match('/^pet_inst_[a-f0-9]{12}_[ab]$/D',$username)) throw new RuntimeException('Refusing a non-test database/user');
$pdo=new PDO('mysql:host='.setting('DB_HOST').';port='.setting('DB_PORT','3306').';charset=utf8mb4',setting('TEST_DB_USER',setting('DB_USER')),setting('TEST_DB_PASSWORD',setting('DB_PASSWORD')),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$account=$pdo->quote($username)."@'127.0.0.1'";
if ($argv[1]==='create') {
    $password=rtrim((string)fgets(STDIN),"\r\n");
    if (strlen($password)<24) throw new RuntimeException('Random test password required on stdin');
    $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4');
    try { $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password)); }
    catch(Throwable $e) { $pdo->exec('DROP DATABASE `'.$database.'`');throw $e; }
    $pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER ON `'.$database.'`.* TO '.$account);
} elseif ($argv[1]==='drop') {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    $pdo->exec('DROP USER IF EXISTS '.$account);
} else throw new RuntimeException('Invalid test operation');
