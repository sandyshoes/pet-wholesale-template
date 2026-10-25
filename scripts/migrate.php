<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';
$pdo = app_db();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, checksum CHAR(64) NULL)');
$columns = $pdo->query('SHOW COLUMNS FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('checksum', $columns, true)) $pdo->exec('ALTER TABLE schema_migrations ADD checksum CHAR(64) NULL');
foreach (glob(APP_ROOT . '/database/*.sql') as $file) {
    $version = basename($file);
    $checksum = hash_file('sha256', $file);
    $check = $pdo->prepare('SELECT version,checksum FROM schema_migrations WHERE version = ?');
    $check->execute([$version]);
    $applied = $check->fetch();
    if ($applied) {
        if ($applied['checksum'] === null) {
            if (!in_array('--adopt-checksums', $argv, true)) throw new RuntimeException('Historical migration lacks checksum: ' . $version . '. Compare its SQL with your reviewed release, back up the database, then explicitly run --adopt-checksums. No product records were changed.');
            $pdo->prepare('UPDATE schema_migrations SET checksum=? WHERE version=? AND checksum IS NULL')->execute([$checksum,$version]);
        } elseif (!hash_equals($applied['checksum'], $checksum)) throw new RuntimeException('Migration checksum mismatch: ' . $version . '. Restore the original reviewed SQL; do not edit an applied migration.');
        echo "Already applied: $version\n"; continue;
    }
    // MariaDB/MySQL DDL implicitly commits. Migrations are idempotent for safe retry.
    foreach (explode(';', file_get_contents($file)) as $sql) if (trim($sql) !== '') $pdo->exec($sql);
    $pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)')->execute([$version,$checksum]);
    echo "Applied: $version\n";
}
