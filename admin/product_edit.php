<?php
require_once __DIR__ . '/auth.php';
require_admin();

$id = $_GET['id'] ?? '';
$product = [
    'id' => '',
    'name' => '',
    'category' => '',
    'price' => '0.00',
    'description' => '',
    'images' => '',
    'colors' => '',
    'sizes' => '',
    'specs' => '',
    'featured' => 0,
    'status' => 'off'
];

if ($id !== '') {
    try {
        $stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $product = array_merge($product, $row);
        } else { http_response_code(404); exit('商品不存在，请返回列表'); }
    } catch (Throwable $e) { throw $e; }
}


?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>商品编辑</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fffaf5;color:#3b2d23}
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
    cursor:pointer;background:#f2e7db;color:#6f4a25;font-weight:700;text-decoration:none
}
.btn.primary{background:#b37a39;color:#fff}
.container{max-width:1100px;margin:0 auto;padding:18px}
.card{
    background:#fff;border:1px solid #f0e1d0;border-radius:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);padding:18px
}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{display:block;font-size:14px;font-weight:700;margin:10px 0 6px}
input,textarea,select{
    width:100%;box-sizing:border-box;padding:10px 12px;
    border:1px solid #d9c4b0;border-radius:10px;font-size:14px
}
textarea{min-height:110px;resize:vertical}
.small{font-size:12px;color:#8b7667}
.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.preview{
    width:120px;height:120px;border:1px solid #eadccd;border-radius:12px;
    overflow:hidden;background:#faf4ee;display:flex;align-items:center;justify-content:center
}
.preview img{width:100%;height:100%;object-fit:cover}
.sep{height:1px;background:#f0e1d0;margin:18px 0}
</style>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>"><link rel="stylesheet" href="../assets/admin.css"><script src="../assets/admin.js"></script></head>
<body>

<div class="topbar">
    <div class="brand">Pet Wholesale Hub - 商品编辑</div>
    <div class="nav"><a href="categories.php">分类</a><a href="images.php">图片</a><a href="csv.php">CSV</a><a href="home.php">品牌设置</a>
        <a href="products.php">返回商品列表</a>
        <a href="home.php">首页内容</a>
        <a href="orders.php">订单管理</a>
        <a href="logout.php">退出</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2 style="margin-top:0;"><?php echo $product['id'] ? '编辑商品' : '新增商品'; ?></h2>

        <form id="productForm">
            <input type="hidden" name="id" value="<?php echo h($product['id']); ?>">

            <div class="grid">
                <div>
                    <label>商品名称</label>
                    <input type="text" name="name" value="<?php echo h($product['name']); ?>" required>
                </div>
                <div>
                    <label>分类</label>
                    <input type="text" name="category" value="<?php echo h($product['category']); ?>" list="categoryOptions" required maxlength="100" placeholder="选择已有分类或输入新分类">
                </div>

                <div>
                    <datalist id="categoryOptions"><?php foreach(db()->query("SELECT name FROM categories ORDER BY name")->fetchAll() as $c): ?><option value="<?=h($c['name'])?>"><?php endforeach; ?></datalist><label>基础价格</label>
                    <input type="number" min="0" step="0.01" name="price" value="<?php echo h($product['price']); ?>" required>
                </div>
                <div>
                    <label>状态</label>
                    <select name="status">
                        <option value="on" <?php echo $product['status'] === 'on' ? 'selected' : ''; ?>>上架</option>
                        <option value="off" <?php echo $product['status'] === 'off' ? 'selected' : ''; ?>>下架</option>
                    </select>
                </div>

                <div>
                    <label>Featured</label>
                    <select name="featured">
                        <option value="0" <?php echo (string)$product['featured'] === '0' ? 'selected' : ''; ?>>否</option>
                        <option value="1" <?php echo (string)$product['featured'] === '1' ? 'selected' : ''; ?>>是</option>
                    </select>
                </div>

                <div>
                    <label>添加商品图片（首张为封面）</label>
                    <input type="file" id="coverFile" accept="image/*">
                    <input type="hidden" name="cover_image" id="cover_image" value="">
                    <div class="small">上传后加入图片列表，可在预览中设为封面</div>
                </div>
            </div>

            <label>商品描述</label>
            <textarea name="description"><?php echo h($product['description']); ?></textarea>

            <div class="sep"></div>

            <label>图片列表（JSON 数组）</label>
            <div id="imagePreview" class="images"></div><p>可逐次上传多张图片。预览中移除只解除引用；物理文件请在图片管理中清理。支持 JSON 字符串数组。</p><textarea name="images" placeholder='["/uploads/a.jpg","/uploads/b.jpg"]'><?php echo h($product['images']); ?></textarea>
            <div class="small">如果你只上传一张封面图，也可以只填写一个路径。</div>

            <div class="grid">
                <div>
                    <label>颜色（JSON 数组）</label>
                    <textarea name="colors" placeholder='["red","blue","black"]'><?php echo h($product['colors']); ?></textarea>
                </div>
                <div>
                    <label>尺码（JSON 数组）</label>
                    <textarea name="sizes" placeholder='["S","M","L"]'><?php echo h($product['sizes']); ?></textarea>
                </div>
            </div>

            <label>规格价格表（JSON 对象）</label>
            <textarea name="specs" placeholder='{"S":2.99,"M":3.99,"L":4.99}'><?php echo h($product['specs']); ?></textarea>
            <div class="small">前端商品详情页会按尺码读取对应价格。</div>

            <div style="margin-top:18px;" class="row">
                <button type="submit" class="btn primary">保存商品</button>
                <a href="products.php" class="btn">返回列表</a>
            </div>
        </form>
    </div>
</div>

<script>
function renderImagePreview(){
 const box=document.getElementById('imagePreview');box.replaceChildren();
 const area=document.querySelector('[name="images"]');let values;try{values=JSON.parse(area.value || '[]')}catch(e){box.textContent='图片列表 JSON 无效';return}
 if(!Array.isArray(values)){box.textContent='图片列表必须为数组';return}
 values.forEach((src,i)=>{const figure=document.createElement('figure');const img=document.createElement('img');
 if(typeof src==='string' && !/[<>"']/.test(src) && /^(?:https:\/\/|\/?(?:[A-Za-z0-9_-]+\/)*uploads\/|\/?(?:[A-Za-z0-9_-]+\/)*assets\/)/.test(src)) img.src=src;
 img.alt='商品图片';const b=document.createElement('button');b.type='button';b.textContent='移除引用';b.onclick=()=>{values.splice(i,1);area.value=JSON.stringify(values);renderImagePreview()};const cover=document.createElement('button');cover.type='button';cover.textContent=i===0?'当前封面':'设为封面';cover.disabled=i===0;cover.onclick=()=>{values.unshift(values.splice(i,1)[0]);area.value=JSON.stringify(values);renderImagePreview()};figure.append(img,cover,b);box.append(figure)});
}
document.querySelector('[name="images"]').addEventListener('input',renderImagePreview);renderImagePreview();
const coverFile = document.getElementById('coverFile');
const coverImage = document.getElementById('cover_image');
let uploading = false;

coverFile.addEventListener('change', async function () {
    if (!this.files.length) return;
    const fd = new FormData();
    fd.append('image', this.files[0]);
    uploading = true;
    try {
    const res = await fetch('api/upload_image.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        coverImage.value = '';
        const area = document.querySelector('[name="images"]');
        let values;try{values=JSON.parse(area.value || '[]')}catch(e){throw Error('请先修正图片列表 JSON')}
        values.push(data.url);area.value=JSON.stringify(values);renderImagePreview();
        alert('上传成功');
    } else {
        throw new Error(data.message || '上传失败');
    }
    } catch (e) {
        this.value = '';
        alert(e.message || '上传失败');
    } finally {
        uploading = false;
    }
});

document.getElementById('productForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (uploading) {
        alert('图片正在上传，请稍候再保存');
        return;
    }
    const button = this.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
    const fd = new FormData(this);

    const res = await fetch('api/save_product.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();
    if (data.success) {
        alert('保存成功');
        location.href = 'products.php';
    } else {
        alert(data.message || '保存失败');
    }
    } catch (e) {
        alert('保存失败，请检查网络后重试');
    } finally {
        button.disabled = false;
    }
});
</script>

</body>
</html>
