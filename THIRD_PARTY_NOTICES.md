# Resources and licensing review

| Resource | Evidence and release treatment |
|---|---|
| Existing PHP/HTML/CSS/JS and AI-assisted changes | Author supplied the project; exact historical code origins and Figma rights not independently proven. Author to approve code license/attribution. No license grant has been made. |
| assets/logo.svg and placeholder.svg | New simple vector geometry and text authored for this change; no downloaded art or embedded fonts. Included in review candidate. Final license follows author-approved scope. |
| Original cat/dog PNG, img/*.png, uploads/admin/* | No copyright/source records supplied; retained privately and excluded from sanitized package. Obtain evidence before adding them. |
| products-data.js external Unsplash images | Legacy sample source, excluded; no inference of model/property rights or endorsement. No remote images downloaded for republishing. |
| placehold.co images | Old URLs replaced with local generated placeholders in active pages. No dependency on that service in release. |
| Arial/Helvetica/system fonts | Family names only, no font binaries redistributed; users' systems render their installed fonts. |
| Unicode/emoji icons | Text characters, no separately bundled icon pack. OS/browser renders fonts; no ownership claim over glyph art. |
| PHP/MariaDB/Node | External runtime dependencies, not bundled. Follow their own distribution terms when packaging runtime containers/binaries. |
| Private JSON, original uploads and software shortcut | Not application assets for redistribution. Excluded from package, regardless of .gitignore. |

Suggested code license: [MIT (OSI)](https://opensource.org/license/mit), subject to explicit author approval and confirmed rights. A license for software must not be used to imply ownership of third-party images, trademarks, customer records or Figma assets. This release candidate is not yet legally licensed as open-source software.

本轮未增加第三方业务库或下载资产。PowerShell/.NET用于本地发行与安装验收，没有打包其二进制。目标仓库已由作者提供，但软件许可证、版权署名及原始代码/Figma权利未获确认；即使敏感文件扫描通过，也不代表无条件公开授权。
