import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const args=process.argv.slice(2);const root=path.resolve(args[0]||'.');
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
const findings=[],files=[],referenceHashes=new Set(),privateTokens=new Set();
const reference=args.indexOf('--private-reference');
if(reference>=0){const r=path.resolve(args[reference+1]);
 function refs(d){if(!fs.existsSync(d))return;for(const e of fs.readdirSync(d,{withFileTypes:true})){const p=path.join(d,e.name);if(e.isDirectory())refs(p);else if(e.isFile()&&e.name!=='.htaccess'){const b=fs.readFileSync(p);referenceHashes.add(hash(b));if(p.endsWith('.json')){try{const collect=(x,key='')=>{if(typeof x==='string'&&x.length>=8&&/(?:email|phone|address|recipient|order_?no|payment_?no|username|account|customer|product_?name|name)$/i.test(key))privateTokens.add(x);else if(x&&typeof x==='object')for(const[k,v]of Object.entries(x))collect(v,k)};collect(JSON.parse(b))}catch{}}}}}
 refs(path.join(r,'data'));refs(path.join(r,'uploads'));
 if(fs.existsSync(path.join(r,'.env'))){const raw=fs.readFileSync(path.join(r,'.env'));referenceHashes.add(hash(raw));for(const line of raw.toString('utf8').split(/\r?\n/)){const match=line.match(/^[A-Z_]*(?:PASSWORD|SECRET|TOKEN|API_KEY)=([\s\S]+)$/);if(match){const value=match[1].replace(/^(['"])(.*)\1$/,'$2');if(value.length>=8)privateTokens.add(value);}}}
 for(const f of ['cat.png','cat2.png','dog.png','dog2.png','home.json'])if(fs.existsSync(path.join(r,f)))referenceHashes.add(hash(fs.readFileSync(path.join(r,f))));
}
const rules=[
 ['private-key',/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/],
 ['github-token',/\bgh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{30,}/],
 ['service-token',/\bsk-(?:proj-)?[A-Za-z0-9_-]{30,}|\bAKIA[A-Z0-9]{16}\b/],
 ['credential-url',/https?:\/\/[^\s/]+:[^\s/@]+@/],
 ['private-address',/[A-Za-z0-9._%+-]+@(?!(?:example\.(?:com|org|net)|localhost)\b)[A-Za-z0-9.-]+\.[A-Za-z]{2,}/],
];
function walk(dir){for(const e of fs.readdirSync(dir,{withFileTypes:true})){const p=path.join(dir,e.name),relative=path.relative(root,p).replaceAll('\\','/');const st=fs.lstatSync(p);if(st.isSymbolicLink()){findings.push({path:relative,rule:'symlink'});continue}if(st.isDirectory()){if(/(^|\/)(\.git|\.private|node_modules|vendor|data|cache|logs|backups)(\/|$)/i.test(relative))findings.push({path:relative,rule:'private-directory'});walk(p);continue}if(!st.isFile())continue;const b=fs.readFileSync(p);const digest=hash(b);files.push({path:relative,bytes:b.length,sha256:digest});
 if(/(^|\/)\.env(?:\..*)?$/.test(relative)&&relative!=='.env.example')findings.push({path:relative,rule:'environment-file'});
 if(/brand\.local\.json$|\.(?:lnk|log|sqlite\d*|bak|gz|zip|pem|key)$/i.test(relative)||/^uploads\//i.test(relative)&&relative!=='uploads/.htaccess')findings.push({path:relative,rule:'private-or-unapproved-file'});
 if(referenceHashes.has(digest))findings.push({path:relative,rule:'matches-original-private-resource'});
 const text=b.toString('utf8');for(const [rule,re]of rules)if(re.test(text))findings.push({path:relative,rule});
 for(const value of privateTokens)if(text.includes(value)){findings.push({path:relative,rule:'matches-original-private-value'});break;}
 if(/\.(?:png|jpe?g|gif|webp|woff2?|ttf|otf)$/i.test(relative))findings.push({path:relative,rule:'asset-license-evidence-required'});
}}
walk(root);
const manifestPath=path.join(root,'RELEASE-MANIFEST.json');
if(fs.existsSync(manifestPath)){
 const m=JSON.parse(fs.readFileSync(manifestPath,'utf8'));const actual=new Map(files.map(f=>[f.path,f]));
 for(const f of m.files)if(!actual.has(f.path)||actual.get(f.path).sha256!==f.sha256)findings.push({path:f.path,rule:'manifest-mismatch'});
 const expected=new Set([...m.files.map(f=>f.path),'RELEASE-MANIFEST.json']);for(const f of files)if(!expected.has(f.path))findings.push({path:f.path,rule:'unmanifested-file'});
}
const result={scannedAt:new Date().toISOString(),fileCount:files.length,privateReferenceCompared:reference>=0,findings,files,limitations:['Pattern and private-reference scans are not proof of all secrets being absent.','Original code/Figma rights still require author approval.','No Git history exists in the original workspace; remote verification is separate.']};
console.log(JSON.stringify(result,null,2));process.exitCode=findings.length?1:0;
