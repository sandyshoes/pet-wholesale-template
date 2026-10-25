<?php
require_once dirname(__DIR__).'/app/admin_layout.php'; admin_header('CSV 导入与导出');
?>
<h1>批量商品</h1><p>UTF-8，最多2MB、1000行。只新增，不覆盖。任一行有误则不能确认；提交时发生冲突则整批回滚。预览10分钟有效。</p>
<p><a href="../examples/products.csv">下载示例模板（示例商品默认下架）</a> · <a href="api/csv.php?action=export">导出当前实例所有商品</a></p>
<form id="csvForm"><input type="file" name="csv" accept=".csv" required><button>校验并预览</button></form>
<pre id="message" class="message" aria-live="polite">尚未导入</pre><div class="scroll"><table><thead><tr><th>ID</th><th>名称</th><th>分类</th><th>价格</th><th>状态</th></tr></thead><tbody id="preview"></tbody></table></div><button id="confirm" disabled>确认新增预览中的商品</button>
<script>
let previewToken=null;
document.getElementById('csvForm').onsubmit=async function(e){
 e.preventDefault(); previewToken=null; document.getElementById('confirm').disabled=true; document.getElementById('preview').replaceChildren();
 try { const fd=new FormData(this); fd.set('action','preview'); const r=await fetch('api/csv.php',{method:'POST',body:fd}); const j=await r.json(); if(!j.success)throw Error(j.message);
  document.getElementById('message').textContent=j.data.errors.length?j.data.errors.map(x=>'第'+x.line+'条记录：'+x.message).join('\n'):'校验通过：'+j.data.rows.length+'条，请核对后确认。';
  j.data.rows.forEach(p=>{const tr=document.createElement('tr');['id','name','category','price','status'].forEach(k=>{const td=document.createElement('td');td.textContent=p[k];tr.append(td)});document.getElementById('preview').append(tr)});
  previewToken=j.token;document.getElementById('confirm').disabled=!previewToken;
 }catch(e){document.getElementById('message').textContent=e.message;}
};
document.getElementById('confirm').onclick=async function(){this.disabled=true;try{const fd=new FormData();fd.set('action','confirm');fd.set('token',previewToken);const r=await fetch('api/csv.php',{method:'POST',body:fd});const j=await r.json();if(!j.success)throw Error(j.message);document.getElementById('message').textContent='已新增 '+j.count+' 条商品';previewToken=null;}catch(e){document.getElementById('message').textContent=e.message;}};
</script>
<?php admin_footer(); ?>
