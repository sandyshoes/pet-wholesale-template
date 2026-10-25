<?php
require_once __DIR__ . '/auth.php';
require_admin();

$adminUser = admin_user() ?? ['username' => 'admin'];
$username = htmlspecialchars($adminUser['username'], ENT_QUOTES, 'UTF-8');

$productCount = 0;
$orderCount = 0;
$homeCount = 0;

try {
    $productCount = (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
} catch (Throwable $e) {}

try {
    $orderCount = (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
} catch (Throwable $e) {}

try {
    $homeCount = (int)db()->query("SELECT COUNT(*) FROM home_content")->fetchColumn();
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台管理 - Dashboard</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fffaf5;color:#3b2d23}
a{text-decoration:none;color:inherit}
.topbar{
    height:58px;background:#fff;border-bottom:1px solid #eadccd;
    display:flex;align-items:center;justify-content:space-between;
    padding:0 18px;position:sticky;top:0;z-index:10
}
.brand{font-size:20px;font-weight:800;color:#b37a39}
.nav{display:flex;gap:12px;align-items:center}
.nav a{
    display:inline-flex;align-items:center;justify-content:center;
    height:36px;padding:0 14px;border-radius:999px;border:0;
    background:#f2e7db;color:#6f4a25;font-weight:700
}
.container{max-width:1100px;margin:0 auto;padding:18px}
.card{
    background:#fff;border:1px solid #f0e1d0;border-radius:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);padding:18px;margin-bottom:18px
}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.stat{padding:16px;border-radius:12px;background:#fff8f0;border:1px solid #f2e2d0}
.num{font-size:30px;font-weight:800;color:#b37a39;margin-top:8px}
</style>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>"><link rel="stylesheet" href="../assets/admin.css"><script src="../assets/admin.js"></script></head>
<body>
<div class="topbar">
    <div class="brand">Pet Wholesale Hub 后台</div>
    <div class="nav"><a href="categories.php">分类</a><a href="images.php">图片</a><a href="csv.php">CSV</a><a href="home.php">品牌设置</a>
        <a href="dashboard.php">首页</a>
        <a href="home.php">首页内容</a>
        <a href="products.php">商品管理</a>
        <a href="orders.php">订单管理</a>
        <a href="logout.php">退出</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2 style="margin-top:0">欢迎，<?php echo $username; ?></h2>
        <p>当前后台已登录。商品、分类、图片、CSV及品牌设置已启用；订单与支付未启用。</p>
    </div>

    <div class="grid">
        <div class="stat">
            <div>商品数量</div>
            <div class="num"><?php echo $productCount; ?></div>
        </div>
        <div class="stat">
            <div>订单数量</div>
            <div class="num"><?php echo $orderCount; ?></div>
        </div>
        <div class="stat">
            <div>首页内容块</div>
            <div class="num"><?php echo $homeCount; ?></div>
        </div>
    </div>
</div>
</body>
</html>
