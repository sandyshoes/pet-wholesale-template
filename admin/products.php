<?php
require_once __DIR__ . '/auth.php';
require_admin();

$search = trim((string)($_GET['q'] ?? ''));
$products = [];
try {
    $stmt = db()->prepare("SELECT * FROM products WHERE name LIKE ? OR id LIKE ? OR category LIKE ? ORDER BY id DESC");
    $needle = '%' . $search . '%';
    $stmt->execute([$needle,$needle,$needle]);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    throw $e;
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>商品管理</title>
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
.nav a,.btn{
    display:inline-flex;align-items:center;justify-content:center;
    height:36px;padding:0 14px;border-radius:999px;border:0;
    cursor:pointer;background:#f2e7db;color:#6f4a25;font-weight:700
}
.btn.primary{background:#b37a39;color:#fff}
.container{max-width:1200px;margin:0 auto;padding:18px}
.card{
    background:#fff;border:1px solid #f0e1d0;border-radius:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);padding:16px
}
.table{width:100%;border-collapse:collapse;margin-top:14px}
.table th,.table td{
    border-bottom:1px solid #f1e5d8;padding:12px 10px;text-align:left;font-size:14px;vertical-align:top
}
.badge{
    display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;
    background:#f2e7db;color:#6f4a25
}
.badge.on{background:#e8f5e9;color:#2e7d32}
.badge.off{background:#ffebee;color:#c62828}
.img{
    width:56px;height:56px;object-fit:cover;border-radius:10px;border:1px solid #eee;background:#fafafa
}
.actions a,.actions button{
    margin-right:6px;
    border:none;
    cursor:pointer;
    padding:6px 10px;
    border-radius:8px;
    font-size:13px;
}
.edit{background:#e8f0ff;color:#1e40af}
.del{background:#fee2e2;color:#b91c1c}
.small{font-size:12px;color:#8b7667}
</style>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>"><link rel="stylesheet" href="../assets/admin.css"><script src="../assets/admin.js"></script></head>
<body>
<div class="topbar">
    <div class="brand">Pet Wholesale Hub - 商品管理</div>
    <div class="nav"><a href="categories.php">分类</a><a href="images.php">图片</a><a href="csv.php">CSV</a><a href="home.php">品牌设置</a>
        <a href="dashboard.php">后台首页</a>
        <a href="home.php">首页内容</a>
        <a href="orders.php">订单管理</a>
        <a href="logout.php">退出</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <h2 style="margin:0 0 6px;">商品列表</h2>
                <div class="small">字段已按你数据库 products 表严格对接。</div>
            </div>
            <a class="btn primary" href="product_edit.php">+ 新增商品</a>
        </div>

        <form method="get"><input name="q" value="<?=h($search)?>" placeholder="搜索名称、ID或分类"><button>搜索</button></form><table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>图片</th>
                    <th>名称</th>
                    <th>分类</th>
                    <th>价格</th>
                    <th>规格/颜色</th>
                    <th>推荐</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <?php
                    $images = json_decode($p['images'] ?? '[]', true);
                    $firstImg = '';
                    if (is_array($images) && !empty($images)) {
                        $firstImg = $images[0];
                    }
                    $colors = json_decode($p['colors'] ?? '[]', true);
                    $sizes = json_decode($p['sizes'] ?? '[]', true);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['id']); ?></td>
                    <td>
                        <?php if ($firstImg): ?>
                            <img class="img" src="<?php echo htmlspecialchars($firstImg); ?>" alt="">
                        <?php else: ?>
                            <div class="small">无图</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['category'] ?? ''); ?></td>
                    <td>$<?php echo number_format((float)($p['price'] ?? 0), 2); ?></td>
                    <td>
                        <div class="small">颜色：<?php echo htmlspecialchars(is_array($colors) ? implode(',', $colors) : ''); ?></div>
                        <div class="small">尺码：<?php echo htmlspecialchars(is_array($sizes) ? implode(',', $sizes) : ''); ?></div>
                    </td>
                    <td>
                        <span class="badge <?php echo !empty($p['featured']) ? 'on' : 'off'; ?>">
                            <?php echo !empty($p['featured']) ? 'featured' : 'normal'; ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo (($p['status'] ?? 'on') === 'on') ? 'on' : 'off'; ?>">
                            <?php echo htmlspecialchars($p['status'] ?? 'on'); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a class="edit" href="product_edit.php?id=<?php echo urlencode($p['id']); ?>">编辑</a>
                        <button class="del" type="button" onclick="deleteProduct('<?php echo htmlspecialchars($p['id']); ?>')">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function deleteProduct(id) {
    if (!confirm('确定删除该商品吗？')) return;

    const fd = new FormData();
    fd.append('id', id);

    const res = await fetch('api/delete_product.php', {
        method: 'POST',
        body: fd
    });
    const data = await res.json();
    if (data.success) {
        alert('删除成功');
        location.reload();
    } else {
        alert(data.message || '删除失败');
    }
}
</script>
</body>
</html>
