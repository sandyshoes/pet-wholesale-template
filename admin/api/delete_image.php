<?php
require_once __DIR__.'/../auth.php'; require_admin(); require_post();
require_once dirname(__DIR__,2).'/app/catalog.php';
[$directory,$url]=upload_location(); $name=(string)($_POST['name']??'');
if (!preg_match('/^[a-f0-9]{32}\.(?:jpg|png|gif|webp)$/D',$name)) api_response(['success'=>false,'message'=>'只能删除当前实例上传的新图片'],422);
foreach (db()->query('SELECT images FROM products')->fetchAll() as $row) foreach (json_decode($row['images']??'[]',true)??[] as $image) if (basename((string)$image)===$name) api_response(['success'=>false,'message'=>'商品仍在引用此图片，请先保存商品并移除引用'],409);
foreach (brand() as $value) if (is_string($value) && basename($value)===$name) api_response(['success'=>false,'message'=>'品牌配置仍引用此图片'],409);
try { foreach (db()->query('SELECT banner_image,why_image FROM home_content')->fetchAll() as $row) foreach ($row as $value) if (basename((string)$value)===$name) api_response(['success'=>false,'message'=>'首页仍引用此图片'],409); }
catch (PDOException $e) { if ($e->getCode()!=='42S02') throw $e; }
if (!is_file($directory.'/'.$name)) api_response(['success'=>false,'message'=>'图片不存在'],404);
if (!unlink($directory.'/'.$name)) api_response(['success'=>false,'message'=>'删除失败'],500);
api_response(['success'=>true]);
