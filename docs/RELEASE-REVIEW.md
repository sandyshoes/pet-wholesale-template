# 本轮发行与GitHub发布审查

状态：待作者确认的发行候选。没有关联远程或推送。历史审查另存 history/release-review-prior.md，历史测试71项不计入本轮。

## 保护与可审查范围

实际路径D:/php/htdocs/petwebsite。开始时不是Git仓库，没有本地分支、暂存区、远程或可访问提交历史；没有reset/clean/force-push/历史改写。记录406个文件的开始清单、大小、mtime和SHA-256，在.private/hardening-*建立独立工作副本和改造前允许发布文件副本。原数据库不连接、不修改，原env/JSON/图片不复制入发行候选。现场截断报告和旧ZIP没有删除。

已实际检查：旧ZIP的96个条目及其逐文件清单；本轮app/admin/前台源代码、config示例、database迁移、scripts、tests、examples、README/安全/部署/资源说明和选定docs；本轮最终ZIP将再次逐条校验与扫描。准备提交范围以最终RELEASE-MANIFEST.json为准，外部FINAL-ARTIFACT报告列出ZIP/清单哈希。不是用.gitignore代替ZIP内容审查。

## 发现及处理

1. 工作区ACCEPTANCE.md在打包后变成#。现场保留，已从旧ZIP保存历史完整报告并重建本轮报告；无法确定写入者，不虚构原因。
2. 旧打包器可接受截断报告、ZIP操作缺少可重复命令、测试隐式使用应用env且覆盖统一结果文件。现已添加完整性门槛、受控归档/校验/解压、外部独立测试操作员配置、隔离继承环境和独立测试证据。
3. 旧迁移只按文件名跳过，不能发现已执行SQL改变。现记录checksum；旧账本缺校验时须人工核验后显式接纳，不自动背书旧SQL。
4. 本地原始env、客户订单JSON、私人商品及上传、原图片和备份仍在保护范围，不能整体提交。ZIP扫描与原私有资源指纹比对未发现这些文件或业务值进入旧发行包；本轮最终扫描明细见外部报告。
5. 没有可访问本地Git历史。目标远程git ls-remote成功但没有公布引用；匿名GitHub仓库元数据返回404，因此无法确认visibility或检索可访问提交。不能声称历史泄露风险已被排除；若后续发现历史，先审查和提出撤销凭据/处理方案，不擅自重写。

## 必须排除

.env及测试操作员凭据；brand.local.json；data及旧home.json/products-data.js；所有原上传、cat/dog/img位图；证券软件快捷方式及lnk；.private历史备份/本轮工作目录；系统临时安装目录中的.env/日志/会话；数据库导出、缓存、旧dist包和.git。SQL只包含公开建表迁移，不含任何用户/订单/商品记录。

## 版权及许可证

原位图、字体/Figma导出和上传没有公开授权证据，未纳入发行。发行只含已有项目源代码和本次简单自制SVG，无打包字体或下载图标库；PHP/MariaDB/Node/PowerShell为外部运行工具，不打包其二进制。

用户尚未决定软件许可证、版权署名和原始代码/Figma授权范围；没有擅自添加LICENSE或将第三方资源称为用户所有。原先MIT建议尚不是授权。本包不得宣传为无条件开源。最终资源说明见THIRD_PARTY_NOTICES.md。

## 本轮测试

重新执行71项回归、44项从ZIP双实例干净安装、13项发行工具检查，共128项通过。测试含服务重启持久性、实际数据库权限隔离、错误配置恢复、迁移漂移拒绝、篡改ZIP/不安全路径拒绝及固定SOURCE_DATE_EPOCH重复归档一致。首次安装测试清理异常单独记录，不计通过。

本轮未重做上一轮浏览器/Apache实机视觉检查，不把历史结果表述成本轮结果。云端HTTPS/nginx/Docker及完整交易系统未验证。最终ZIP的精确哈希与额外安装复验在外部FINAL-ARTIFACT报告中；最终文件和扫描清单可在推送前复核。

## 目标仓库与关联方案（尚未执行）

用户提供：所有者sandyshoes；仓库pet-wholesale-template；HTTPS https://github.com/sandyshoes/pet-wholesale-template.git；目标main。没有创建任何本地分支或改动远程公开状态。

等待用户确认审查结果、许可证/署名、仓库可见性后，再在审查过的独立干净发行目录执行git init -b main，使用逐文件白名单提交；关联给定HTTPS远程；重新检查远程refs和历史。如发现远程已有内容，先报告差异，不覆盖。推送前展示暂存清单、diff、扫描与测试证据，再获得针对该仓库的最终确认。禁止强推，不在含原始私人数据的工作区git add .。
