<?php
require_once __DIR__.'/auth.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 require_post();check_csrf(); $_SESSION=[];
 setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>app_base().'/','secure'=>setting('SESSION_SECURE','0')==='1','httponly'=>true,'samesite'=>'Lax']);
 session_destroy(); header('Location: login.php'); exit;
}
?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>退出</title><link rel="stylesheet" href="../assets/admin.css"></head><body><main><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><p>确认退出管理员账户？</p><button>退出登录</button><a href="products.php">返回</a></form></main></body></html>