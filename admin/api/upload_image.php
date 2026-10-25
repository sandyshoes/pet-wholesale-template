<?php
require_once __DIR__.'/../auth.php'; require_admin(); require_post();
require_once dirname(__DIR__,2).'/app/catalog.php';
$file=$_FILES['image']??$_FILES['file']??null;
if (!$file || is_array($file['error']) || $file['error']!==UPLOAD_ERR_OK) api_response(['success'=>false,'message'=>'上传失败或文件超过服务器限制'],422);
if ($file['size']<=0 || $file['size']>(int)setting('UPLOAD_MAX_BYTES','5242880')) api_response(['success'=>false,'message'=>'图片大小超过限制'],422);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $types=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
$size=@getimagesize($file['tmp_name']);
if (!isset($types[$mime]) || !$size || $size[0]>10000 || $size[1]>10000 || $size[0]*$size[1]>25000000) api_response(['success'=>false,'message'=>'只允许有效 JPG/PNG/GIF/WebP 图片，最多2500万像素'],422);
if (str_contains(file_get_contents($file['tmp_name']),'<?')) api_response(['success'=>false,'message'=>'图片内容不安全'],422);
[$directory,$url]=upload_location();
if ((!is_dir($directory) && !mkdir($directory,0750,true)) || !is_writable($directory)) api_response(['success'=>false,'message'=>'图片存储不可写'],500);
$name=bin2hex(random_bytes(16)).'.'.$types[$mime];
if (!move_uploaded_file($file['tmp_name'],$directory.'/'.$name)) api_response(['success'=>false,'message'=>'图片保存失败'],500);
api_response(['success'=>true,'url'=>$url.'/'.$name]);
