# 本轮发行硬化文件变更

所有实现先在独立工作副本完成；修改前文件及开始哈希已保存。未修改admin、商品页面、业务建表SQL、原env、上传或业务JSON；本轮没有新增业务功能或连接原业务数据库。

| 类型 | 路径 |
|---|---|
| 修改 | `README.md` |
| 修改 | `SECURITY.md` |
| 修改 | `THIRD_PARTY_NOTICES.md` |
| 修改 | `app/bootstrap.php` |
| 修改 | `docs/ACCEPTANCE.md` |
| 修改 | `docs/DEPLOYMENT.md` |
| 修改 | `docs/RELEASE-REVIEW.md` |
| 修改 | `docs/TEST-RESULTS.json` |
| 新增 | `docs/history/acceptance-prior-release.md` |
| 新增 | `docs/history/release-review-prior.md` |
| 新增 | `docs/history/test-results-prior.json` |
| 新增 | `scripts/archive-release.ps1` |
| 新增 | `scripts/doctor.php` |
| 修改 | `scripts/migrate.php` |
| 修改 | `scripts/package-release.mjs` |
| 新增 | `scripts/release-audit.mjs` |
| 修改 | `tests/database.php` |
| 新增 | `tests/install-from-zip.mjs` |
| 新增 | `tests/isolated-db.php` |
| 修改 | `tests/run.mjs` |
| 新增 | `tests/tooling.mjs` |

完整逐文件哈希见发行RELEASE-MANIFEST.json；本报告自身也纳入清单。旧报告在docs/history保留。详细本地差异保存在私有硬化工作目录，避免把修改前私人识别模式带入发行包。
