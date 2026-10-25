<?php
require_once __DIR__.'/../auth.php'; require_admin();
require_once dirname(__DIR__,2).'/app/csv.php';
$action=(string)($_GET['action']??$_POST['action']??'');
if ($action==='export') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="products.csv"'); header('Cache-Control: no-store');
    $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,CSV_FIELDS,',','"','');
    foreach (db()->query('SELECT * FROM products ORDER BY id')->fetchAll() as $p) {
        $row=[];
        foreach (CSV_FIELDS as $key) {
            $value=(string)($p[$key]??'');
            // Neutralize spreadsheet formula execution in exported cells.
            if (preg_match('/^[\s]*[=+@-]/u',$value)) $value="'".$value;
            $row[]=$value;
        }
        fputcsv($out,$row,',','"','');
    }
    fclose($out); exit;
}
require_post();
try {
    if ($action==='preview') {
        unset($_SESSION['csv_preview']);
        $file=$_FILES['csv']??null;
        if (!$file || $file['error']!==UPLOAD_ERR_OK) throw new InvalidArgumentException('请选择有效 CSV 文件');
        $preview=preview_csv($file['tmp_name'],db());
        $token=bin2hex(random_bytes(24));
        if (!$preview['errors']) $_SESSION['csv_preview']=['token'=>$token,'rows'=>$preview['rows'],'expires'=>time()+600];
        api_response(['success'=>true,'data'=>$preview,'token'=>$preview['errors']?null:$token]);
    }
    if ($action==='confirm') {
        $preview=$_SESSION['csv_preview']??null;
        if (!$preview || $preview['expires']<time() || !hash_equals($preview['token'],(string)($_POST['token']??''))) throw new InvalidArgumentException('预览过期，请重新上传');
        unset($_SESSION['csv_preview']);
        $pdo=db(); $pdo->beginTransaction();
        foreach ($preview['rows'] as $row) store_product($pdo,$row,true);
        $pdo->commit(); api_response(['success'=>true,'count'=>count($preview['rows'])]);
    }
    throw new InvalidArgumentException('未知操作');
} catch (InvalidArgumentException|JsonException $e) { api_response(['success'=>false,'message'=>$e->getMessage()],422); }
catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage()); api_response(['success'=>false,'message'=>'整批导入已回滚，可能有 ID 冲突，请重新预览'],409);
}
