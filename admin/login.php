<?php require_once __DIR__.'/auth.php'; ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录 - Pet Wholesale Hub</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fffaf5;color:#3b2d23;font-family:Arial,sans-serif}.card{width:min(420px,90vw);padding:32px;background:#fff;border:1px solid #eadccd;border-radius:16px;box-shadow:0 4px 16px #0001}h1{font-size:24px;color:#b37a39}label{display:block;margin:18px 0 6px}input,button{width:100%;padding:12px;border:1px solid #d9c4b0;border-radius:10px;font-size:16px}button{margin-top:24px;background:#b37a39;color:white;cursor:pointer}a{color:#7b4d1c}#error{color:#a32929;min-height:20px}
</style>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>"><link rel="stylesheet" href="../assets/admin.css"><script src="../assets/admin.js"></script></head>
<body>
<main class="card">
<h1>Pet Wholesale Hub</h1>
<h2>后台登录</h2>
<form id="loginForm" action="api/login.php" method="post">
<label for="username">用户名</label>
<input id="username" name="username" autocomplete="username" required>
<label for="password">密码</label>
<input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">登录</button>
<p id="error" role="alert"></p>
</form>
<a href="../Index.html">返回前台</a>
</main>
<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const button = this.querySelector('button');
  const error = document.getElementById('error');
  button.disabled = true;
  error.textContent = '';
  try {
    const response = await fetch(this.action, {method: 'POST', body: new FormData(this)});
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || '登录失败');
    location.href = 'products.php';
  } catch (e) {
    error.textContent = e.message || '登录失败，请重试';
  } finally {
    button.disabled = false;
  }
});
</script>
</body>
</html>
