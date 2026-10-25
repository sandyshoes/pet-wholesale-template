<?php
// Development server only. Apache uses .htaccess; production routing must match.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, '..') || preg_match('~(?:^|/)(?:\.[^/]*|app|config|database|scripts|tests|docs|data|storage|dist|\.private)(?:/|$)|\.(?:lnk|sql|log|bak|sqlite)$~i', $path)) {
    http_response_code(404); exit;
}
if ($path === '/') { readfile(__DIR__ . '/Index.html'); return true; }
if ($path === '/index.html') { readfile(__DIR__ . '/Index.html'); return true; }
$file = realpath(__DIR__ . $path);
if (!$file || !str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
if (str_starts_with($path, '/uploads/') && !preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', $path)) { http_response_code(404); exit; }
return false;
