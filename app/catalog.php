<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
function image_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (strlen($url)>1000 || preg_match('/[\x00-\x20<>"\x27\\\\]/',$url)) throw new InvalidArgumentException('图片路径含非法字符');
    if (preg_match('~^https://[^/]+/[^\s]*$~iD',$url) && filter_var($url,FILTER_VALIDATE_URL)) return $url;
    $relative=ltrim($url,'/'); $base=trim(app_base(),'/');
    if ($base!=='' && str_starts_with($relative,$base.'/')) $relative=substr($relative,strlen($base)+1);
    if (str_contains($relative,'..') || !preg_match('~^(?:uploads/[a-zA-Z0-9_./-]+\.(?:png|jpg|jpeg|gif|webp)|assets/[a-zA-Z0-9_-]+\.svg)$~iD',$relative)) throw new InvalidArgumentException('仅允许本站图片路径或 HTTPS 图片 URL');
    return app_base().'/'.$relative;
}
function json_field(mixed $input,bool $object=false): array {
    if ($input==='' || $input===null) return [];
    if (is_string($input)) {
        $decoded=json_decode($input,false,32,JSON_THROW_ON_ERROR);
        if ($object ? !is_object($decoded) : !is_array($decoded)) throw new InvalidArgumentException($object?'规格必须为 JSON 对象':'列表必须为 JSON 数组');
        return $object?(array)$decoded:$decoded;
    }
    if (!is_array($input)) throw new InvalidArgumentException('数据格式无效');
    return $input;
}
function validate_product(array $input): array {
    $p=[];
    foreach (['id'=>64,'name'=>255,'category'=>100,'description'=>20000] as $field=>$limit) {
        if (!is_scalar($input[$field]??'')) throw new InvalidArgumentException($field.' 必须为文本');
        $p[$field]=trim((string)($input[$field]??''));
        if (mb_strlen($p[$field])>$limit) throw new InvalidArgumentException($field.' 过长');
    }
    if ($p['id']!=='' && !preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$p['id'])) throw new InvalidArgumentException('商品 ID 格式无效');
    if ($p['name']==='' || $p['category']==='') throw new InvalidArgumentException('名称与分类必填');
    $price=(string)($input['price']??'');
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$price)) throw new InvalidArgumentException('价格须为非负金额，最多两位小数');
    $p['price']=$price; $p['status']=(string)($input['status']??'off'); $p['featured']=(string)($input['featured']??'0');
    if (!in_array($p['status'],['on','off'],true) || !in_array($p['featured'],['0','1'],true)) throw new InvalidArgumentException('状态或推荐标记无效');
    foreach (['images','colors','sizes'] as $field) {
        $values=json_field($input[$field]??'[]');
        if (count($values)>50) throw new InvalidArgumentException($field.' 最多50项');
        foreach ($values as &$value) {
            if (!is_string($value) || mb_strlen($value)>1000 || trim($value)==='') throw new InvalidArgumentException($field.' 必须为非空字符串数组');
            $value=$field==='images'?image_url($value):trim($value);
        }
        unset($value);
        if ($field==='images' && !empty($input['cover_image'])) array_unshift($values,image_url((string)$input['cover_image']));
        $p[$field]=json_encode(array_values(array_unique($values)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    $specs=json_field($input['specs']??'{}',true); $sizes=json_decode($p['sizes'],true);
    foreach ($specs as $size=>$amount) {
        if (!in_array((string)$size,$sizes,true)) throw new InvalidArgumentException('规格价格的键必须对应一个尺码');
        if (!is_scalar($amount) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',(string)$amount)) throw new InvalidArgumentException('规格价格无效');
        $specs[$size]=(float)$amount;
    }
    $p['specs']=json_encode((object)$specs,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    return $p;
}
function store_product(PDO $pdo,array $p,bool $create): string {
    if ($create && $p['id']==='') $p['id']='p'.bin2hex(random_bytes(12));
    $pdo->prepare('INSERT IGNORE INTO categories(name) VALUES (?)')->execute([$p['category']]);
    if ($create) $sql='INSERT INTO products(id,name,category,description,price,status,featured,images,colors,sizes,specs) VALUES (:id,:name,:category,:description,:price,:status,:featured,:images,:colors,:sizes,:specs)';
    else {
        $check=$pdo->prepare('SELECT id FROM products WHERE id=? FOR UPDATE'); $check->execute([$p['id']]);
        if (!$check->fetch()) throw new InvalidArgumentException('商品不存在');
        $sql='UPDATE products SET name=:name,category=:category,description=:description,price=:price,status=:status,featured=:featured,images=:images,colors=:colors,sizes=:sizes,specs=:specs WHERE id=:id';
    }
    $pdo->prepare($sql)->execute($p); return $p['id'];
}
function upload_location(): array {
    $relative=trim(setting('UPLOAD_DIR','uploads/products'),'/');
    if (!preg_match('~^uploads/[a-zA-Z0-9_-]+$~D',$relative)) throw new RuntimeException('UPLOAD_DIR must be uploads/INSTANCE_DIRECTORY');
    return [APP_ROOT.'/'.$relative,app_base().'/'.$relative];
}
