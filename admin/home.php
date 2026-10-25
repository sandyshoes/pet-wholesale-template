<?php
require_once dirname(__DIR__).'/app/admin_layout.php';
require_once dirname(__DIR__).'/app/catalog.php';
require_admin(); $message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  $config=brand();
  foreach(['name'=>100,'intro'=>1000,'about'=>5000,'footer'=>300] as $key=>$length){$v=trim((string)($_POST[$key]??''));if($v===''||mb_strlen($v)>$length)throw new InvalidArgumentException($key.' 必填或超过长度');$config[$key]=$v;}
  $email=trim((string)($_POST['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('联系邮箱无效');$config['email']=$email;
  foreach(['primary','background'] as $key){if(!preg_match('/^#[0-9a-fA-F]{6}$/D',$_POST[$key]??''))throw new InvalidArgumentException('颜色格式无效');$config[$key]=$_POST[$key];}
  foreach(['logo','hero_image'] as $key)$config[$key]=image_url((string)($_POST[$key]??''));
  if(!in_array($_POST['currency']??'', ['USD','EUR','GBP','CNY','CAD','AUD','JPY'],true))throw new InvalidArgumentException('不支持的币种');$config['currency']=$_POST['currency'];
  foreach(['minimum_quantity','quantity_step'] as $key){$v=filter_var($_POST[$key]??'',FILTER_VALIDATE_INT);if($v===false||$v<1||$v>100000)throw new InvalidArgumentException('批发数量必须为1–100000的整数');$config[$key]=$v;}
  $target=APP_ROOT.'/'.setting('BRAND_FILE','config/brand.local.json');
  $temp=$target.'.'.bin2hex(random_bytes(6)).'.tmp';
  if(file_put_contents($temp,json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($temp,$target))throw new RuntimeException('品牌配置不可写');
  $message='品牌配置已保存，刷新前台查看';
 }catch(Throwable $e){$message=$e instanceof InvalidArgumentException?$e->getMessage():'保存失败，请检查配置文件权限';}
}
$config=brand();admin_header('品牌与主题设置');
?>
<h1>独立品牌设置</h1><p>这些内容仅属于当前实例。联系邮箱默认是占位地址，运营前请换成自己的邮箱。本站不承诺库存或发货时效。</p><p class="message"><?=h($message)?></p>
<form method="post">
<?php foreach(['name'=>'网站名称','email'=>'联系邮箱','intro'=>'品牌介绍','about'=>'关于我们','footer'=>'页脚','logo'=>'Logo 图片路径','hero_image'=>'首页图片路径','primary'=>'主题色','background'=>'背景色','minimum_quantity'=>'起订量','quantity_step'=>'加购数量步进'] as $key=>$label): ?>
<label for="<?=h($key)?>"><?=h($label)?></label><input style="width:100%" id="<?=h($key)?>" name="<?=h($key)?>" value="<?=h($config[$key])?>" required>
<?php endforeach; ?>
<label>币种</label><select name="currency"><?php foreach(['USD','EUR','GBP','CNY','CAD','AUD','JPY'] as $currency):?><option <?=$config['currency']===$currency?'selected':''?>><?=h($currency)?></option><?php endforeach;?></select><p><button>保存品牌配置</button></p>
</form>
<?php admin_footer(); ?>
