# Deployment and data isolation

## PHP / Apache

Use a supported patched PHP build. This environment only verified code execution on PHP 8.2.12; upgrade the server separately before public use. The [official support table](https://www.php.net/supported-versions.php) should be checked when deploying. No PHP runtime or service upgrade was performed by this task.

Serve the sanitized release directory. Apache needs mod_rewrite and AllowOverride All so the included `.htaccess` denies config/app/scripts/tests/docs/data/storage/.private/dist/dotfiles and disables directory indexes. DirectoryIndex must resolve `Index.html`. The uploads `.htaccess` permits only image extensions and disables handlers. Root or subdirectory deployments use the corresponding APP_URL. Verify blocking with curl after configuring the real server; never deploy blindly when overrides are disabled.

## nginx + PHP-FPM example (root deployment)

Adjust document root, fastcgi socket and TLS configuration. This configuration is provided for review and was not run here.

```nginx
root /srv/your-catalog;
index Index.html;
client_max_body_size 6m;
location ~ (^|/)\. { deny all; }
location ~* ^/(app|config|database|scripts|tests|docs|data|storage|dist)/ { deny all; }
location ~* \.(lnk|sql|log|bak|sqlite)$ { deny all; }
location ~* ^/uploads/.*\.(png|jpe?g|gif|webp)$ { try_files $uri =404; add_header X-Content-Type-Options nosniff; }
location ~* ^/uploads/ { deny all; }
location / { try_files $uri $uri/ =404; }
location ~ \.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/your-php-fpm.sock;
}
```

Production must provide its own TLS, backups, monitoring, DB credentials and file permissions. Existing raw working directories contain private files that should never be a deployment artifact; use the allowlisted release. Test `/data/orders.json`, `/.env`, `/config/brand.local.json`, `/scripts/create-admin.php` are 403/404 and uploaded executable suffixes cannot execute. These checks were run on local Apache and the development router, not a real production nginx server.

## Per-instance boundaries

Create a new database and DB account for every instance. Use distinct application directories/upload volumes and hostnames. For several local instances sharing a source tree, configure different APP_ENV_FILE, DB_NAME, UPLOAD_DIR and BRAND_FILE. APP_ENV_FILE is a server process setting, never taken from request parameters. HTTP clients cannot choose a DB or brand file. Storage and sessions must not be shared unintentionally with other sites or applications.

## Initialization/upgrade/backup

`php scripts/migrate.php` applies only missing versioned SQL. 001_catalog creates products/admin_users/categories/app_login_limits and a schema_migrations ledger; categories are initialized from existing products. Existing private user/order/home tables are left intact but are not part of this catalog template's active data model. No customer/order seed, administrator password, or private product is bundled.

Before future upgrades: back up the database + uploads + brand file, test the migration on a private copy, run tests, then deploy. MySQL DDL auto-commits and rollback is not automatic. Restoring a backup is the recovery plan. An unexpected pre-existing incompatible schema requires explicit migration review, not data deletion.

Create the first administrator using the CLI-only create-admin script; it refuses duplicate usernames and does not reset existing users. Password reset UI is not implemented. Operators can create a new administrator locally, then revoke an old account through a reviewed database operation if necessary.

## Commands

- No npm/composer install or asset build required.
- Migrate: `php scripts/migrate.php`.
- Initialize admin: `php scripts/create-admin.php USERNAME`, password on stdin.
- Local start: `php -S 127.0.0.1:8080 router.php`; stop Ctrl+C.
- Check: `PHP_BINARY=php node tests/run.mjs` (PowerShell env syntax in README).
- Stage release: `node scripts/package-release.mjs`. It does not publish or push.

Docker Compose was evaluated but intentionally deferred: the small no-build PHP app already installs using standard PHP/MySQL hosting; Docker/image pulls/production container security could not be verified here. No container support is claimed.

## 本轮硬化补充

历史测试见docs/history，本轮证据见TEST-RESULTS.json，不能把历史71项等同于本轮复验。迁移新增checksum元数据，已执行SQL变更会拒绝运行；旧账本缺值必须核验历史SQL后显式--adopt-checksums。迁移用户需要CREATE/ALTER，正常网站运行用户可在迁移后撤销这些DDL权限。

先运行php scripts/doctor.php，再迁移、创建管理员并启动。测试仅使用显式PET_TEST_OPERATOR_ENV和限定名称的临时数据库/用户，默认不读取业务.env。ZIP验收工具依赖PowerShell7/.NET（应用部署本身不依赖它们），同时验证重启后数据和两个运行账号的SQL权限隔离。
