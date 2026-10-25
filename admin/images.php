<?php
require_once dirname(__DIR__).'/app/admin_layout.php'; require_once dirname(__DIR__).'/app/catalog.php'; admin_header('图片管理');
[$directory,$url]=upload_location();
?>
<h1>当前实例图片</h1><p>商品编辑中移除图片只解除引用。删除商品不会删除文件。这里只能清理当前上传目录中的新图片；仍被引用的图片会拒绝删除，旧文件保留。</p>
<p id="message" class="message" aria-live="polite"></p><div class="images">
<?php foreach(glob($directory.'/*')?:[] as $file): $name=basename($file); if(!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/D',$name)) continue; ?>
<figure><img src="<?=h($url.'/'.$name)?>" alt="已上传图片"><figcaption><?=h($name)?></figcaption><button data-name="<?=h($name)?>">删除未引用文件</button></figure>
<?php endforeach; ?></div>
<script>document.querySelectorAll('[data-name]').forEach(b=>b.onclick=async()=>{if(!confirm('确认永久删除这个图片文件？'))return;try{const fd=new FormData();fd.set('name',b.dataset.name);const r=await fetch('api/delete_image.php',{method:'POST',body:fd});const j=await r.json();if(!j.success)throw Error(j.message);b.closest('figure').remove();document.getElementById('message').textContent='图片已删除';}catch(e){document.getElementById('message').textContent=e.message;}});</script>
<?php admin_footer(); ?>
