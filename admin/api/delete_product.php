<?php
require_once __DIR__.'/../auth.php'; require_admin(); require_post();
$stmt=db()->prepare('DELETE FROM products WHERE id=?'); $stmt->execute([(string)($_POST['id']??'')]);
api_response(['success'=>$stmt->rowCount()>0,'message'=>'商品已删除；图片文件保留，可在图片管理中清理'],$stmt->rowCount()>0?200:404);
