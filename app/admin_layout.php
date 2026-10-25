<?php
require_once dirname(__DIR__).'/admin/auth.php';
function admin_header(string $title): void {
    require_admin();
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?= h(csrf_token()) ?>"><title><?= h($title) ?></title><link rel="stylesheet" href="../assets/admin.css"><script src="../assets/admin.js"></script></head><body><header><strong>Pet Wholesale Hub · <?= h($title) ?></strong><nav><a href="products.php">商品</a><a href="categories.php">分类</a><a href="images.php">图片</a><a href="csv.php">导入导出</a><a href="home.php">品牌</a><a href="../Index.html">前台</a><a href="logout.php">退出</a></nav></header><main><?php
}
function admin_footer(): void { echo '</main></body></html>'; }
