<?php
require_once __DIR__.'/catalog.php';
const CSV_FIELDS=['id','name','category','price','description','images','colors','sizes','specs','featured','status'];
function preview_csv(string $file, PDO $pdo): array {
    if (filesize($file)>2097152) throw new InvalidArgumentException('CSV 最大2MB');
    $handle=fopen($file,'rb'); $header=fgetcsv($handle,0,',','"','');
    if (!$header) throw new InvalidArgumentException('CSV 为空');
    $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
    if ($header!==CSV_FIELDS) throw new InvalidArgumentException('表头及顺序必须与模板一致');
    $rows=[]; $errors=[]; $seen=[]; $line=1;
    $exists=$pdo->prepare('SELECT id FROM products WHERE id=?');
    while (($cells=fgetcsv($handle,0,',','"',''))!==false) {
        $line++;
        if ($cells===[null]) continue;
        if (count($rows)+count($errors)>=1000) throw new InvalidArgumentException('每次最多1000条');
        try {
            if (count($cells)!==count(CSV_FIELDS)) throw new InvalidArgumentException('列数不匹配');
            foreach ($cells as $cell) if (!mb_check_encoding($cell,'UTF-8')) throw new InvalidArgumentException('文件必须为 UTF-8');
            $row=validate_product(array_combine(CSV_FIELDS,$cells));
            if ($row['id']!=='') {
                $exists->execute([$row['id']]);
                if (isset($seen[$row['id']]) || $exists->fetch()) throw new InvalidArgumentException('重复 ID：'.$row['id'].'；导入不会覆盖已有商品');
                $seen[$row['id']]=true;
            }
            $rows[]=$row;
        } catch (InvalidArgumentException|JsonException $e) { $errors[]=['line'=>$line,'message'=>$e->getMessage()]; }
    }
    fclose($handle);
    if (!$rows && !$errors) throw new InvalidArgumentException('没有商品数据');
    return ['rows'=>$rows,'errors'=>$errors];
}
