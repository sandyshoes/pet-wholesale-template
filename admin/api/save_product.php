<?php
require_once __DIR__.'/../auth.php'; require_admin(); require_post();
require_once dirname(__DIR__,2).'/app/catalog.php';
try {
    $product=validate_product($_POST); $pdo=db(); $pdo->beginTransaction();
    $id=store_product($pdo,$product,$product['id']===''); $pdo->commit();
    api_response(['success'=>true,'message'=>'保存成功','id'=>$id]);
} catch (InvalidArgumentException|JsonException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    api_response(['success'=>false,'message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage());
    api_response(['success'=>false,'message'=>'保存失败，请检查数据库或联系管理员'],500);
}
