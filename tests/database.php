<?php
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
if (!getenv('APP_ENV_FILE')) { fwrite(STDERR,"Explicit test operator APP_ENV_FILE required; normal application .env will not be used.\n"); exit(1); }
require_once dirname(__DIR__).'/app/bootstrap.php';
$name=$argv[2]??'';
if (!preg_match('/^pet_template_test_[a-f0-9]{12}_[ab]$/D',$name)) throw new RuntimeException('Refusing non-test database');
$pdo=new PDO('mysql:host='.setting('DB_HOST').';port='.setting('DB_PORT','3306').';charset=utf8mb4',setting('TEST_DB_USER',setting('DB_USER')),setting('TEST_DB_PASSWORD',setting('DB_PASSWORD')),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ($argv[1]==='create') $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
elseif ($argv[1]==='drop') $pdo->exec('DROP DATABASE `'.$name.'`');
else throw new RuntimeException('Invalid operation');
