<?php
declare(strict_types=1);

// Decode the JSON fields saved by the product editor for public API clients.
function public_product(array $row): array {
    foreach (['images', 'colors', 'sizes', 'specs'] as $field) {
        $value = json_decode((string)($row[$field] ?? ''), true);
        $row[$field] = is_array($value) ? $value : [];
    }
    $row['specs'] = (object)$row['specs'];
    $row['price'] = (float)$row['price'];
    $row['featured'] = (int)($row['featured'] ?? 0);
    return $row;
}
