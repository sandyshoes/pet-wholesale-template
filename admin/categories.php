<?php
require_once dirname(__DIR__).'/app/admin_layout.php'; require_admin();
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=trim((string)($_POST['name']??'')); $old=(string)($_POST['old']??''); $action=(string)($_POST['action']??'');
    try {
        if ($name==='' || mb_strlen($name)>100) throw new InvalidArgumentException('分类名必填，最多100字');
        $pdo=db(); $pdo->beginTransaction();
        if ($action==='delete') {
            $check=$pdo->prepare('SELECT id FROM products WHERE category=? LIMIT 1 FOR UPDATE'); $check->execute([$name]);
            if ($check->fetch()) throw new InvalidArgumentException('分类仍有商品，不能删除');
            $pdo->prepare('DELETE FROM categories WHERE name=?')->execute([$name]);
        } elseif ($action==='rename') {
            $pdo->prepare('UPDATE categories SET name=? WHERE name=?')->execute([$name,$old]);
            $pdo->prepare('UPDATE products SET category=? WHERE category=?')->execute([$name,$old]);
        } elseif ($action==='add') $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$name]);
        else throw new InvalidArgumentException('未知操作');
        $pdo->commit(); $message='操作成功';
    } catch (Throwable $e) { if (isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); $message=$e instanceof InvalidArgumentException?$e->getMessage():'操作失败，分类可能已存在'; }
}
admin_header('分类管理'); ?>
<h1>分类管理</h1><p class="message"><?= h($message) ?></p>
<form method="post"><input name="name" maxlength="100" placeholder="新分类名称" required><button name="action" value="add">新增分类</button></form>
<?php foreach(db()->query('SELECT name FROM categories ORDER BY name')->fetchAll() as $row): ?>
<form method="post"><input type="hidden" name="old" value="<?=h($row['name'])?>"><input name="name" value="<?=h($row['name'])?>" required maxlength="100"><button name="action" value="rename">重命名并同步商品</button><button name="action" value="delete">删除空分类</button></form>
<?php endforeach; admin_footer(); ?>
