# Pet Wholesale Hub

[MIT licensed](LICENSE) · Copyright (c) 2026 sandyshoes.

An independently deployable pet wholesale **catalog and inquiry template**, built on the original HTML/CSS/JavaScript + PHP project. Keeps its warm palette, card layouts, product details and quantity/variant workflow. There is no frontend framework, Composer/npm runtime dependency or compile step.

This is not a payment processor or a finished ecommerce/fulfillment system. Customer accounts, checkout, payment and shipment APIs are explicitly unavailable (501). The inquiry list is browser-local and indicative; the visitor sends an email through their own mail application. No email is sent automatically. Product price, availability, shipping and payment terms require confirmation by the store owner.

## Features

- Public on-sale catalog, category filters, keyword search, price sorting, details, colors/sizes, per-size prices and loading/empty/error states.
- Password-hashed administrator login, idle expiry, logout, database-backed login throttling and CSRF protection.
- Product create/edit/delete/publish, category add/rename/delete-unused, admin search, safe image uploads/previews, reference removal and separate unreferenced-file cleanup.
- CSV UTF-8 template, validation and preview, explicit transactional **insert-only** confirmation, duplicate rejection, current-instance export.
- Independent database + upload directory + admin sessions per deployment, environment configuration, CLI migrations and administrator creation.
- Brand/email/logo/introduction/about/footer/theme/currency/MOQ configuration in a private instance file, editable under `admin/home.php`.

## Requirements

- PHP 8.2+ with PDO MySQL, fileinfo, mbstring, JSON, session. Production should use the latest patched supported PHP release; the local test runtime 8.2.12 is old and is not a production recommendation. See [PHP support policy](https://www.php.net/supported-versions.php).
- MySQL 8 or MariaDB 10.4+; tested here with MariaDB 10.4.32. A new, dedicated database for each deployment.
- Apache 2.4 with mod_rewrite/AllowOverride All, or correctly configured nginx + PHP-FPM. Local CLI server supported for development only.
- Node 22+ only for automated tests/release packaging (tested on Node 24.18.0). No npm install needed.

## Installation

1. Use the sanitized release source folder produced by `node scripts/package-release.mjs`, or a reviewed public repository once one exists. **Do not copy the original working directory wholesale**: it contains private historical data/assets.
2. Create a new UTF-8 database and an instance-specific user. Example SQL below uses placeholders; replace them locally. Do not reuse the author's database or root account:

```sql
CREATE DATABASE your_catalog CHARACTER SET utf8mb4;
CREATE USER 'your_catalog_user'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_RANDOM_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER ON your_catalog.* TO 'your_catalog_user'@'localhost';
```

3. Copy `.env.example` to `.env`. Set APP_URL (no trailing slash, includes subdirectory when used), DB_HOST/PORT/NAME/USER/PASSWORD. An empty DB_PASSWORD is only for explicitly configured local development; production must use a strong private credential. No default DB/admin is shipped.
4. Copy `config/brand.example.json` to `config/brand.local.json`, or configure later in the admin. The brand file and uploads/products must be writable by PHP; source files, `.env` and migrations should not be web-writable. `uploads` is public image storage; `storage` and session temporary directories must be private. Follow [deployment protections](docs/DEPLOYMENT.md).
5. Run migrations, then create your own administrator. Password is read from standard input, **not** a command-line argument or a default. Use 12–72 bytes. For production pipe from a password manager; the interactive terminal may echo the input.

```sh
php scripts/doctor.php
php scripts/migrate.php
php scripts/create-admin.php YOUR_ADMIN_USERNAME
php -S 127.0.0.1:8080 router.php
```

Open `http://127.0.0.1:8080/` and `/admin/login.php`. Stop the development server with Ctrl+C. No build step is required. The migration is idempotent and does not drop, truncate or overwrite existing products/users/orders. It creates catalog/auth/category/limiting tables and a migration ledger. MySQL DDL auto-commits; migrations are not described as fully transactional.

Windows/XAMPP example for this local workspace (already has a private `.env`):

```powershell
& D:\php\php\php.exe scripts/migrate.php
& D:\php\php\php.exe scripts/create-admin.php YOUR_NEW_ADMIN
$env:PHP_BINARY='D:\php\php\php.exe'
$env:PET_TEST_OPERATOR_ENV='C:\PRIVATE_TEST_CONFIG\operator.env'
$env:PET_TEST_REPORT='C:\PRIVATE_TEST_RESULTS\regression.json'
node tests/run.mjs
```

With Apache running, use `http://localhost/petwebsite/Index.html` and `/petwebsite/admin/login.php`. Do not start another server on Apache's port. For a different deployment, configure your own APP_URL. In Linux, URLs are case-sensitive: the preserved homepage filename is `Index.html`; root directory routing resolves it through .htaccess/router.php.

## Operating your store

Log in → 商品 → 新增商品. Supply name, category, nonnegative base price, description and optional images/options. Image upload accepts genuine JPEG/PNG/GIF/WebP only, normally ≤5MB, max 25 million pixels; PHP's own post/upload limits also apply. Each upload appends a preview; select 设为封面 to move an image to the first position. Removing a preview only removes that product reference on save. No stock or supplier data is invented.

JSON fields: images `[]`, colors `["Gray"]`, sizes `["S","M"]`, specs `{"S":12.5,"M":15}`. Every specification key must match a size. Status defaults to off for a new product; choose on to publish. Set featured=1 to show it on the homepage. An unpriced (zero-price) product uses a quote prompt. Product deletion leaves files in place. 图片管理 deletes only new files in this instance's configured upload directory and refuses known product/brand/homepage references. It does not erase shared images automatically.

Manage categories in 分类; renaming updates the products, and a category with products cannot be deleted. Manage brand in 品牌设置. Replace `wholesale@example.com` before use; the quote action refuses the placeholder address. Currency is display metadata: changing it does not convert stored prices. MOQ/quantity step apply to inquiry quantities, not to a server-side contract or inventory reservation.

## CSV

Use `examples/products.csv`. It contains an explicitly labelled fictional **off-sale example**, never auto-imported. The exact header/order is required. Price ≤8 integer digits and 2 decimal places, JSON arrays/objects as above. Up to 2MB/1000 rows, UTF-8. Empty ID generates one; supplied ID must be unique within the batch and instance. Import is insert-only: it never overwrites existing products. Any validation error blocks confirmation; any database conflict during confirmation rolls back the entire batch. Preview belongs to the admin session, is single-use and expires after 10 minutes. Re-preview after a failure.

Export includes on/off products for the authenticated instance only. Formula-like cells are prefixed with `'` to avoid spreadsheet execution; review this prefix if re-importing such fields. The CSV parser reports logical record numbers (multiline descriptions can span physical lines). Images are referenced by paths or HTTPS URLs; uploading remote/bulk images is not implemented.

## Testing and release preparation

```sh
# Explicit separate TEST operator config, never your application .env
PET_TEST_OPERATOR_ENV=/private/test-operator.env PHP_BINARY=php PET_TEST_REPORT=/private/regression.json node tests/run.mjs
node scripts/package-release.mjs
# PowerShell 7 / .NET are used only for archive verification and clean-ZIP acceptance
pwsh -NoProfile -File scripts/archive-release.ps1 -Mode Create -Source dist/RELEASE_SOURCE -Archive /private/review.zip
pwsh -NoProfile -File scripts/archive-release.ps1 -Mode Verify -Archive /private/review.zip
PET_TEST_OPERATOR_ENV=/private/test-operator.env PHP_BINARY=php POWERSHELL_BINARY=pwsh PET_TEST_REPORT=/private/install.json node tests/install-from-zip.mjs /private/review.zip
```

Tests require an **explicit, separate** PET_TEST_OPERATOR_ENV. The test runner does not fall back to the normal application .env and clears inherited DB variables in its child processes. Use a test-only MySQL/MariaDB operator on a disposable server if possible, with CREATE/DROP DATABASE and CREATE/DROP USER/GRANT rights for temporary test resources. Never configure this privileged operator as a website runtime account. Example private operator file (replace placeholders; keep it outside the release):

```dotenv
APP_URL=http://127.0.0.1
DB_HOST=YOUR_TEST_DB_HOST
DB_PORT=3306
DB_NAME=pet_template_test_operator
DB_USER=YOUR_TEST_OPERATOR
DB_PASSWORD=YOUR_TEST_OPERATOR_PASSWORD
TEST_DB_USER=YOUR_TEST_OPERATOR
TEST_DB_PASSWORD=YOUR_TEST_OPERATOR_PASSWORD
```

The regression suite uses its own random pet_template_test_* databases and ports 18871–18873. Clean ZIP acceptance extracts twice into fresh OS temp directories, creates two independently granted runtime database users, separate upload/session directories, uses available ports and tests a real process restart. Finally blocks drop only strictly named synthetic databases/users; private installation folders/logs remain for inspection and must never be committed. Test reports go to PET_TEST_REPORT (otherwise a private path), not directly to docs/TEST-RESULTS.json. A failed run must not replace successful evidence. PowerShell 7 with .NET is needed for the included archive/clean-install tooling; application installation itself requires only PHP + MySQL/MariaDB. No npm/Composer dependencies were installed because the application has none.

Release packaging copies a strict allowlist, blocks truncated acceptance reports, scans the actual release contents and writes a SHA-256 manifest. The archive command refuses unmanifested files and Verify checks every file. SOURCE_DATE_EPOCH fixes manifest time, and ZIP entry timestamps/order are deterministic; identical-source reproducibility was tested. Extract mode requires a new destination and rejects unsafe/duplicate paths. It is an aid to review, not proof of zero secrets or copyright clearance. Do not `git add .` in the original workspace. Review [release review](docs/RELEASE-REVIEW.md), [acceptance](docs/ACCEPTANCE.md), [resource notices](THIRD_PARTY_NOTICES.md) and [security](SECURITY.md).

## Troubleshooting

- 503: verify all required DB env values, DB service/permissions, migration and writable session directory; details go to server logs, not public JSON.
- Upload fails/invalid JSON: configure PHP `upload_tmp_dir` and `session.save_path` to writable private temporary folders, `display_errors=Off`, `upload_max_filesize`/`post_max_size` ≥ your app limit. No world-writable permissions required.
- 403 on save: refresh the admin page and log in again; CSRF token/cookie may have expired.
- Empty frontend: ensure status=on, correct instance DB, and clear the keyword/category filter. Homepage uses featured=1 only.
- HTTPS: set APP_URL=https://your-domain and SESSION_SECURE=1. Separate origins and databases are recommended for distinct deployments. Back up DB + uploads + private brand file together.

## Project provenance / license status

Product concept, business planning and Figma design were led by the original author with AI-assisted implementation. No Figma source/link was supplied for this audit, so pixel fidelity and original asset ownership are unverified. The original private source snapshot remains local in `.private/original`; it is not part of the release.

The repository's included software, documentation and generated SVG placeholders are licensed under [MIT](LICENSE), Copyright (c) 2026 sandyshoes. The author confirmed the right to license the included code. Retain the copyright and permission notice when redistributing.

Original unverified photos, uploaded product/customer data, Figma assets and private configurations remain excluded. MIT does not grant rights to excluded resources, third-party runtime binaries, fonts or trademarks. See [resource notices](THIRD_PARTY_NOTICES.md) and [current open-source status](docs/OPEN-SOURCE-STATUS.md).

The earlier audit reports are dated historical evidence: their references to pending licensing or unpublished code describe the state before the author's later authorization. They do not override LICENSE. No new payment, inventory or production-hosting capability is claimed by adding a license.

## Release-hardening evidence and migration upgrades

The previous 71 checks are archived under docs/history. This round separately reran 71 regression, 44 clean-ZIP install and 13 release-tooling checks; see docs/TEST-RESULTS.json for run IDs and dates. Final archive bytes and manifest hashes are attested externally so the archive is not required to contain its own hash.

Migration SQL is immutable after application. The ledger now records SHA-256. If upgrading an old installation whose ledger has no checksum, back up your database and compare each historical SQL file against the release you actually installed; only after verification run php scripts/migrate.php --adopt-checksums. The flag only accepts missing checksums and cannot bypass an existing mismatched checksum. Tests exercised both legacy adoption and changed-SQL refusal on disposable databases; this task did not execute migration against the original business database.

The CLI doctor prints check names and actionable failure guidance without configuration values. It handles missing variables, placeholders, required PHP extensions and database connection. Fix the environment and rerun doctor/migrate rather than deleting business data. UTF-8 BOM is accepted, one matching outer quote pair is removed from values, inner quotes are preserved; shell interpolation/escape expansion is not performed.
