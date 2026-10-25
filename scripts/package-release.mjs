import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {spawnSync} from 'node:child_process';
const root=path.resolve(import.meta.dirname,'..');
const date=new Date(process.env.SOURCE_DATE_EPOCH ? Number(process.env.SOURCE_DATE_EPOCH)*1000 : Date.now());
if(!Number.isFinite(date.getTime()))throw Error('Invalid SOURCE_DATE_EPOCH');
const target=path.resolve(process.argv[2]||path.join(root,'dist','release-'+date.toISOString().replace(/[:.]/g,'-')));
if(fs.existsSync(target))throw Error('Refusing to overwrite a release directory');
const acceptance=fs.readFileSync(path.join(root,'docs/ACCEPTANCE.md'),'utf8');
if(acceptance.trim().length<1000)throw Error('Acceptance report is empty/truncated; release blocked');
const result=JSON.parse(fs.readFileSync(path.join(root,'docs/TEST-RESULTS.json'),'utf8'));
if(result.schemaVersion===2){if(!result.current?.success)throw Error('Current evidence incomplete');}else if(!Array.isArray(result.tests)||result.passed!==result.tests.length)throw Error('Test evidence invalid');
const fixed=['.env.example','.gitignore','.htaccess','README.md','SECURITY.md','THIRD_PARTY_NOTICES.md','router.php','db.php','get_brand.php','get_product.php','get_products.php','product_fields.php','Index.html','shop.html','product.html','contact.html','cart.html','checkout.html','order.html','payment.html','payment_result.html','tracking.html','login.html','login.php','check_login.php','me.php','captcha.php','cart_add.php','cart_get.php','cart_update.php','cart_remove.php','order_create.php','order_detail.php','order_list.php','payment_create.php','config/brand.example.json','uploads/.htaccess','storage/.htaccess','docs/AUDIT.md','docs/ACCEPTANCE.md','docs/RELEASE-REVIEW.md','docs/TEST-RESULTS.json','docs/DEPLOYMENT.md','docs/CHANGES.md'];
fixed.push('docs/HARDENING-CHANGES.md');
fs.mkdirSync(target,{recursive:true});const manifest=[];const paths=new Set();
function copy(relative){if(paths.has(relative))throw Error('Duplicate release path');paths.add(relative);const source=path.join(root,relative);const st=fs.lstatSync(source);if(!st.isFile()||st.isSymbolicLink())throw Error('Only regular files allowed: '+relative);const bytes=fs.readFileSync(source);const dest=path.join(target,relative);fs.mkdirSync(path.dirname(dest),{recursive:true});fs.writeFileSync(dest,bytes);manifest.push({path:relative,bytes:bytes.length,sha256:crypto.createHash('sha256').update(bytes).digest('hex')});}
function directory(relative){for(const entry of fs.readdirSync(path.join(root,relative),{withFileTypes:true})){if(entry.name.startsWith('.')||entry.isSymbolicLink())throw Error('Unexpected hidden/symlink source: '+relative+'/'+entry.name);const p=relative+'/'+entry.name;if(entry.isDirectory())directory(p);else if(/\.(php|js|mjs|css|svg|sql|csv|ps1|md|json)$/.test(entry.name))copy(p);else throw Error('Unexpected release file: '+p)}}
for(const p of fixed)copy(p);
for(const d of ['app','admin','assets','database','scripts','tests','examples','docs/history'])directory(d);
manifest.sort((a,b)=>a.path.localeCompare(b.path,'en'));
const payload={generated:date.toISOString(),releaseStatus:'review-only; license and publication approval pending',files:manifest};
fs.writeFileSync(path.join(target,'RELEASE-MANIFEST.json'),JSON.stringify(payload,null,2)+'\n');
const audit=spawnSync(process.execPath,[path.join(root,'scripts/release-audit.mjs'),target],{encoding:'utf8'});
if(audit.status!==0){let findings;try{findings=JSON.parse(audit.stdout).findings}catch{}throw Error('Release scan failed: '+JSON.stringify(findings||audit.stderr));}
console.log(JSON.stringify({directory:target,files:manifest.length,bytes:manifest.reduce((n,f)=>n+f.bytes,0)}));
