<?php
require_once __DIR__.'/app/catalog.php';
$config=brand();
foreach(['logo','hero_image'] as $key)$config[$key]=image_url((string)$config[$key]);
api_response(['success'=>true,'data'=>$config]);
