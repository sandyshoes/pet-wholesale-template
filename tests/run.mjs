import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import {spawn,spawnSync} from 'node:child_process';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const root=path.resolve(import.meta.dirname,'..');process.chdir(root);
const php=process.env.PHP_BINARY||'php';
const id=crypto.randomBytes(6).toString('hex');
const testRoot=path.join(root,'.private',`test-${id}`);fs.mkdirSync(testRoot,{recursive:true});
const report=[];const children=[];const created=[];const envFiles=[];
function check(name,condition){assert.ok(condition,name);report.push(name);console.log('PASS '+name)}
function testEnv(extra={}){const env={...process.env};for(const key of Object.keys(env))if(/^(DB_|TEST_DB_|APP_URL$|APP_ENV_FILE$|UPLOAD_|BRAND_FILE$|SESSION_SECURE$)/.test(key))delete env[key];return {...env,...extra};}
function cli(args,env={},input){const r=spawnSync(php,args,{cwd:root,env:testEnv(env),encoding:'utf8',input});if(r.status!==0)throw Error(`${args[0]}: ${r.stderr||r.stdout}`);return r.stdout}
const operator=process.env.PET_TEST_OPERATOR_ENV;
if(!operator)throw Error('Set PET_TEST_OPERATOR_ENV to a separate test operator config; application .env is never used.');
const source=fs.readFileSync(operator,'utf8');
function config(port,label){
 const name=`pet_template_test_${id}_${label}`;
 cli(['tests/database.php','create',name],{APP_ENV_FILE:operator});created.push(name);
 const file=path.join(testRoot,`${label}.env`);
 const text=source.split(/\r?\n/).filter(x=>!/^APP_URL=|^DB_NAME=|^UPLOAD_DIR=|^BRAND_FILE=|^UPLOAD_MAX_BYTES=/.test(x)).join('\n')+`\nAPP_URL=http://127.0.0.1:${port}\nDB_NAME=${name}\nUPLOAD_DIR=uploads/test_${id}_${label}\nBRAND_FILE=.private/test-${id}/${label}.brand.json\nUPLOAD_MAX_BYTES=1024\n`;
 fs.writeFileSync(file,text);envFiles.push(file);
 const env={APP_ENV_FILE:file};
 cli(['scripts/migrate.php'],env);check(`${label}: migration retry is idempotent`,cli(['scripts/migrate.php'],env).includes('Already applied'));
 cli(['scripts/create-admin.php','template-admin'],env,'Test-only-random-'+id+'\n');
 const log=fs.openSync(path.join(testRoot,label+'.log'),'a');
 const server=spawn(php,['-d',`session.save_path=${os.tmpdir()}`,'-d',`upload_tmp_dir=${os.tmpdir()}`,'-d','display_errors=0','-d','upload_max_filesize=2M','-S',`127.0.0.1:${port}`,'router.php'],{cwd:root,env:testEnv(env),stdio:['ignore',log,log],windowsHide:true});children.push(server);fs.closeSync(log);
 return {base:`http://127.0.0.1:${port}`,env,name,label};
}
async function ready(base){for(let i=0;i<60;i++){try{if((await fetch(base+'/get_brand.php')).ok)return}catch{}await new Promise(r=>setTimeout(r,100))}throw Error('Development server did not start')}
function client(base){let cookie='';const request=async(route,options={})=>{const headers=new Headers(options.headers);headers.set('Connection','close');if(cookie)headers.set('Cookie',cookie);const r=await fetch(base+route,{...options,headers,redirect:'manual'});const c=r.headers.getSetCookie();if(c.length)cookie=c.map(x=>x.split(';')[0]).join('; ');const bytes=await r.arrayBuffer();return new Response(bytes,{status:r.status,headers:r.headers})};request.cookie=()=>cookie;return request}
const form=(values)=>new URLSearchParams(values);
async function login(client){const page=await client('/admin/login.php');const html=await page.text();const token=html.match(/name="csrf-token" content="([a-f0-9]+)"/)?.[1];assert.ok(token);const r=await client('/admin/api/login.php',{method:'POST',body:form({username:'template-admin',password:'Test-only-random-'+id,csrf_token:token})});assert.equal(r.status,200,await r.text());const authed=await client('/admin/products.php');const body=await authed.text();assert.equal(authed.status,200,body);return body.match(/name="csrf-token" content="([a-f0-9]+)"/)?.[1]}
const product={id:'',name:'EXAMPLE ONLY - Test <tag>',category:'Example category',price:'12.50',description:'Not a real product',images:'[]',colors:'["Gray"]',sizes:'["S","M"]',specs:'{"S":12.50,"M":15}',featured:'1',status:'on'};
try{
 // Syntax checks of every shipped PHP/JS and inline script, excluding private original files.
 function scan(d){for(const e of fs.readdirSync(d,{withFileTypes:true})){if(['.private','.git','dist','uploads','data'].includes(e.name))continue;const p=path.join(d,e.name);if(e.isDirectory())scan(p);else if(p.endsWith('.php')){cli(['-l',p]);for(const m of fs.readFileSync(p,'utf8').matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(m[1],{filename:p});}else if(p.endsWith('.html')||p.endsWith('.js')){const s=fs.readFileSync(p,'utf8');for(const code of p.endsWith('.js')?[s]:[...s.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].map(x=>x[1]))new vm.Script(code,{filename:p})}}}
 scan('.');check('All active PHP/JS syntax',true);
 const a=config(18871,'a'),b=config(18872,'b');await Promise.all([ready(a.base),ready(b.base)]);
 const anon=client(a.base),admin=client(a.base),other=client(b.base);
 check('Fresh database catalog is empty',(await (await anon('/get_products.php')).json()).data.length===0);
 for(const endpoint of ['save_product.php','delete_product.php','upload_image.php','delete_image.php','get_products.php','get_product.php','csv.php'])check('Unauthenticated denied: '+endpoint,(await anon('/admin/api/'+endpoint,{method:'POST',body:form({id:'test'})})).status===401);
 const csrf=await login(admin);check('Real password login and authenticated admin page',!!csrf);
 check('CSRF enforced',(await admin('/admin/api/save_product.php',{method:'POST',body:form(product)})).status===403);
 const post=async(route,values)=>admin(route,{method:'POST',body:form({...values,csrf_token:csrf})});
 check('Write method enforced',(await admin('/admin/api/save_product.php')).status===405);
 for(const invalid of [{price:'-1'},{specs:'bad'},{sizes:'{}'},{name:''},{images:'["javascript:alert(1)"]'}])check('Reject invalid product '+Object.keys(invalid)[0],(await post('/admin/api/save_product.php',{...product,...invalid})).status===422);
 let response=await post('/admin/api/save_product.php',product);let j=await response.json();check('Product create',response.status===200&&j.success);const pid=j.id;
 let detail=(await (await anon('/get_product.php?id='+pid)).json()).data;check('Public detail uses DB, JSON and numeric prices',detail.id===pid&&detail.specs.M===15&&Array.isArray(detail.images));
 check('SQL injection does not expose products',(await anon('/get_product.php?id='+encodeURIComponent("' OR 1=1 --"))).status===404);
 check('Second deployment has no first-instance products',(await (await other('/get_products.php')).json()).data.length===0);
 const bc=await login(other);check('Other deployment rejects product update',(await other('/admin/api/save_product.php',{method:'POST',body:form({...product,id:pid,csrf_token:bc})})).status===422);
 check('Real first-instance cookie is rejected by second instance',(await fetch(b.base+'/admin/api/get_products.php',{headers:{Cookie:admin.cookie()}})).status===401);
 let body=new FormData();body.set('image',new Blob([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==','base64')],{type:'image/png'}),'image.png');
 response=await admin('/admin/api/upload_image.php',{method:'POST',body,headers:{'X-CSRF-Token':csrf}});j=await response.json();check('Valid image upload',response.status===200&&j.success);const image=j.url;const name=image.split('/').at(-1);
 check('Uploaded image is accessible',(await anon(image)).status===200);
 for(const [label,data,type] of [['text','not an image','image/png'],['php','<?php echo 1;','image/png'],['oversized',Buffer.alloc(2048),'image/png']]){body=new FormData();body.set('image',new Blob([data],{type}),label+'.png');check('Reject upload '+label,(await admin('/admin/api/upload_image.php',{method:'POST',body,headers:{'X-CSRF-Token':csrf}})).status===422)}
 check('Edit persists image and name',(await post('/admin/api/save_product.php',{...product,id:pid,name:'EXAMPLE ONLY Updated',images:JSON.stringify([image])})).status===200);
 check('Referenced image deletion denied',(await post('/admin/api/delete_image.php',{name})).status===409);
 check('Cannot delete other-instance upload',(await other('/admin/api/delete_image.php',{method:'POST',body:form({name,csrf_token:bc})})).status===404);
 check('Admin search works',(await (await admin('/admin/products.php?q=not-present')).text()).includes('EXAMPLE ONLY Updated')===false);
 check('Classification add',(await (await post('/admin/categories.php',{action:'add',name:'Extra'})).text()).includes('操作成功'));
 check('Classification rename',(await (await post('/admin/categories.php',{action:'rename',old:'Example category',name:'Renamed'})).text()).includes('操作成功'));
 detail=(await (await anon('/get_product.php?id='+pid)).json()).data;check('Classification rename updates products',detail.category==='Renamed');
 check('Cannot delete occupied category',(await (await post('/admin/categories.php',{action:'delete',name:'Renamed'})).text()).includes('分类仍有商品'));
 const brandConfig=JSON.parse(fs.readFileSync('config/brand.example.json','utf8'));
 check('Brand settings save',(await (await post('/admin/home.php',{...brandConfig,name:'EXAMPLE Instance A'})).text()).includes('品牌配置已保存'));
 check('Brand settings remain instance-local',(await (await anon('/get_brand.php')).json()).data.name==='EXAMPLE Instance A'&&(await (await other('/get_brand.php')).json()).data.name==='Pet Wholesale Hub');
 const csv=fs.readFileSync('examples/products.csv');
 const csvPreview=async(bytes)=>{const fd=new FormData();fd.set('action','preview');fd.set('csv',new Blob([bytes],{type:'text/csv'}),'products.csv');return admin('/admin/api/csv.php',{method:'POST',body:fd,headers:{'X-CSRF-Token':csrf}})};
 response=await csvPreview(csv);j=await response.json();check('CSV preview without mutation',response.status===200&&j.token&&j.data.rows.length===1);
 check('CSV not applied before confirmation',(await anon('/get_product.php?id=demo-only-001')).status===404);
 response=await post('/admin/api/csv.php',{action:'confirm',token:j.token});j=await response.json();check('CSV explicit confirmation persists',j.success&&j.count===1);
 response=await csvPreview(csv);j=await response.json();check('Duplicate CSV rejected with row cause',j.data.errors.length===1&&!j.token);
 response=await csvPreview(Buffer.from('bad,header\n1,2'));check('Invalid CSV header rejected',response.status===422);
 response=await csvPreview(Buffer.from(csv.toString().replace(',12.50,',',invalid,')));j=await response.json();check('CSV invalid price has error details',j.data.errors.length>0&&!j.token);
 check('Export is current-instance data',(await (await admin('/admin/api/csv.php?action=export')).text()).includes('EXAMPLE ONLY Updated'));
 // Confirm a preview only after another session introduced a competing ID.
 const header=csv.toString().split(/\r?\n/)[0];
 const example=csv.toString().split(/\r?\n/)[1];
 const conflictCsv=header+'\n'+example.replace('demo-only-001','rollback-first').replace(/,off$/,',on')+'\n'+example.replace('demo-only-001','rollback-conflict').replace(/,off$/,',on')+'\n';
 j=await (await csvPreview(conflictCsv)).json();const staleToken=j.token;
 const competing=client(a.base);const competingCsrf=await login(competing);
 let fd=new FormData();fd.set('action','preview');fd.set('csv',new Blob([header+'\n'+example.replace('demo-only-001','rollback-conflict').replace(/,off$/,',on')+'\n']),'race.csv');
 const race=await (await competing('/admin/api/csv.php',{method:'POST',body:fd,headers:{'X-CSRF-Token':competingCsrf}})).json();
 await competing('/admin/api/csv.php',{method:'POST',body:form({action:'confirm',token:race.token,csrf_token:competingCsrf})});
 check('CSV conflict rolls entire batch back',(await post('/admin/api/csv.php',{action:'confirm',token:staleToken})).status===409&&(await anon('/get_product.php?id=rollback-first')).status===404);
 await post('/admin/api/delete_product.php',{id:'rollback-conflict'});
 // Real filesystem upload failure, without changing permissions on user data.
 const blockedName=`test_block_${id}`;const blocked=path.join(root,'uploads',blockedName);fs.writeFileSync(blocked,'test sentinel');
 const faultEnv=path.join(testRoot,'upload-fault.env');fs.writeFileSync(faultEnv,fs.readFileSync(envFiles[0],'utf8').replace(/UPLOAD_DIR=.*\n/,`UPLOAD_DIR=uploads/${blockedName}\n`));
 const faultLog=fs.openSync(path.join(testRoot,'fault.log'),'a');
 const fault=spawn(php,['-d',`session.save_path=${os.tmpdir()}`,'-d',`upload_tmp_dir=${os.tmpdir()}`,'-d','display_errors=0','-S','127.0.0.1:18873','router.php'],{cwd:root,env:testEnv({APP_ENV_FILE:faultEnv}),stdio:['ignore',faultLog,faultLog],windowsHide:true});children.push(fault);fs.closeSync(faultLog);
 await ready('http://127.0.0.1:18873');const faulty=client('http://127.0.0.1:18873');const faultyCsrf=await login(faulty);
 fd=new FormData();fd.set('image',new Blob([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==','base64')]),'valid.png');
 check('Unwritable upload location returns controlled error',(await faulty('/admin/api/upload_image.php',{method:'POST',body:fd,headers:{'X-CSRF-Token':faultyCsrf}})).status===500);fs.unlinkSync(blocked);
 response=await post('/admin/api/save_product.php',{...product,id:pid,status:'off'});check('Unpublish hides list and detail',response.ok&&(await anon('/get_product.php?id='+pid)).status===404&&(await (await anon('/get_products.php')).json()).data.length===0);
 check('Product delete',(await post('/admin/api/delete_product.php',{id:pid})).status===200);
 check('Detached file cleanup',(await post('/admin/api/delete_image.php',{name})).status===200);
 check('Private file deletion path traversal denied',(await post('/admin/api/delete_image.php',{name:'../.env'})).status===422);
 for(const route of ['/.env','/.private/original/data/orders.json','/data/orders.json','/config/brand.local.json','/database/001_catalog.sql','/tests/run.mjs'])check('Private route blocked '+route,(await anon(route)).status===404);
 for(const page of ['/','/shop.html','/product.html','/contact.html','/cart.html','/checkout.html'])check('Public page '+page,(await anon(page)).status===200);
 check('Checkout explicit unavailable',(await (await anon('/checkout.html')).text()).includes('No payment is collected'));
 check('Legacy payment API cannot claim success',(await anon('/payment_create.php',{method:'POST'})).status===501);
 check('Logout revokes session',(await post('/admin/logout.php',{})).status===302&&(await admin('/admin/api/get_products.php')).status===401);
 // Missing config and connection failure run real PHP subprocesses (no mocks).
 const missing=path.join(testRoot,'missing.env');fs.writeFileSync(missing,'APP_URL=http://localhost\n');
 let r=spawnSync(php,['-r',"require 'app/bootstrap.php'; app_db();"],{cwd:root,env:testEnv({APP_ENV_FILE:missing}),encoding:'utf8'});check('Missing DB configuration fails closed',r.status!==0&&r.stderr.includes('Missing environment variable'));
 const broken=path.join(testRoot,'broken.env');fs.writeFileSync(broken,fs.readFileSync(envFiles[0],'utf8').replace(/DB_PORT=.*\n/,'DB_PORT=1\n'));
 r=spawnSync(php,['-r',"require 'app/bootstrap.php'; app_db();"],{cwd:root,env:testEnv({APP_ENV_FILE:broken}),encoding:'utf8'});check('Unreachable database fails',r.status!==0);
 // Token bucket login throttling with real wrong-password requests.
 const attacker=client(a.base);const loginPage=await (await attacker('/admin/login.php')).text();const token=loginPage.match(/name="csrf-token" content="([a-f0-9]+)"/)[1];
 for(let n=0;n<10;n++)await attacker('/admin/api/login.php',{method:'POST',body:form({username:'missing-account',password:'incorrect',csrf_token:token})});
 check('Login throttling persists across requests',(await attacker('/admin/api/login.php',{method:'POST',body:form({username:'missing-account',password:'incorrect',csrf_token:token})})).status===429);
 const resultPath=process.env.PET_TEST_REPORT||path.join(testRoot,'RESULTS.json');fs.mkdirSync(path.dirname(resultPath),{recursive:true});fs.writeFileSync(resultPath,JSON.stringify({runId:id,date:new Date().toISOString(),kind:'regression-rerun',passed:report.length,tests:report,limitations:['Prior browser visual QA was not repeated by this suite.','Production TLS, nginx and Docker are not exercised by this suite.']},null,2));
 console.log(`\n${report.length} checks passed. Temporary databases will be removed.`);
}finally{
 for(const child of children){if(child.exitCode===null){const exited=new Promise(r=>child.once('exit',r));child.kill();await exited;}}
 for(const name of created)cli(['tests/database.php','drop',name],{APP_ENV_FILE:operator});
 for(const label of ['a','b']){const dir=path.join(root,'uploads',`test_${id}_${label}`);if(fs.existsSync(dir))fs.rmSync(dir,{recursive:true});}
 const blocked=path.join(root,'uploads',`test_block_${id}`);if(fs.existsSync(blocked))fs.unlinkSync(blocked);
 // Retain logs/configs only in ignored, web-blocked .private for troubleshooting.
}
