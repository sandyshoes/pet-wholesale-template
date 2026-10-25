<?php
require_once __DIR__.'/../auth.php'; require_post(); check_csrf();
$username=trim((string)($_POST['username']??'')); $password=(string)($_POST['password']??'');
if ($username==='' || strlen($username)>50 || strlen($password)>72) api_response(['success'=>false,'message'=>'用户名或密码错误'],401);
$pdo=db();
$buckets=[hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??'')),hash('sha256','user:'.strtolower($username))];
$pdo->beginTransaction();
foreach ($buckets as $bucket) {
    $pdo->prepare('INSERT IGNORE INTO app_login_limits(bucket,failures,started_at) VALUES (?,0,?)')->execute([$bucket,time()]);
    $stmt=$pdo->prepare('SELECT * FROM app_login_limits WHERE bucket=? FOR UPDATE'); $stmt->execute([$bucket]); $limit=$stmt->fetch();
    if (time()-(int)$limit['started_at']>=900) $pdo->prepare('UPDATE app_login_limits SET failures=0,started_at=? WHERE bucket=?')->execute([time(),$bucket]);
    elseif ((int)$limit['failures']>=10) { $pdo->commit(); header('Retry-After: 900'); api_response(['success'=>false,'message'=>'尝试过多，请15分钟后重试'],429); }
}
$stmt=$pdo->prepare('SELECT id,username,password_hash FROM admin_users WHERE username=?'); $stmt->execute([$username]); $user=$stmt->fetch();
$valid=password_verify($password,$user['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
if (!$user || !$valid) {
    foreach ($buckets as $bucket) $pdo->prepare('UPDATE app_login_limits SET failures=failures+1 WHERE bucket=?')->execute([$bucket]);
    $pdo->commit(); api_response(['success'=>false,'message'=>'用户名或密码错误'],401);
}
$pdo->prepare('DELETE FROM app_login_limits WHERE bucket=?')->execute([$buckets[1]]); $pdo->commit();
session_regenerate_id(true); $_SESSION=['admin_user'=>['id'=>$user['id'],'username'=>$user['username']],'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
api_response(['success'=>true]);
