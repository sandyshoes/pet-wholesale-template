<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
$checks=[];
$record=function(string $name, bool $ok, string $message='') use (&$checks): void { $checks[]=['check'=>$name,'ok'=>$ok,'message'=>$message]; };
$record('PHP >= 8.2', version_compare(PHP_VERSION,'8.2','>='),PHP_VERSION);
foreach (['pdo_mysql','fileinfo','mbstring','json','session'] as $ext) $record('extension '.$ext,extension_loaded($ext));
foreach (['APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
    try { $value=setting($key); $record('configuration '.$key,!str_starts_with($value,'YOUR_') && ($key==='DB_PASSWORD'||$value!==''),'Values are deliberately not printed'); }
    catch(Throwable $e){ $record('configuration '.$key,false,'Missing required variable'); }
}
try {
    $url=setting('APP_URL');
    $record('APP_URL format',filter_var($url,FILTER_VALIDATE_URL)!==false && in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true) && !parse_url($url,PHP_URL_QUERY) && !parse_url($url,PHP_URL_FRAGMENT));
    $record('DB_NAME format',preg_match('/^[A-Za-z0-9_]+$/D',setting('DB_NAME'))===1);
    $port=filter_var(setting('DB_PORT','3306'),FILTER_VALIDATE_INT);
    $record('DB_PORT range',$port!==false&&$port>0&&$port<=65535);
} catch(Throwable $e){ $record('configuration format',false,'Correct the required variables first'); }
$valid=!in_array(false,array_column($checks,'ok'),true);
if ($valid) {
    try { app_db()->query('SELECT 1'); $record('database connection',true); }
    catch(Throwable $e) { $record('database connection',false,'Connection failed. Check the dedicated database, credentials, port and grants; fix .env and rerun doctor.'); }
}
echo json_encode(['success'=>!in_array(false,array_column($checks,'ok'),true),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(in_array(false,array_column($checks,'ok'),true)?1:0);
