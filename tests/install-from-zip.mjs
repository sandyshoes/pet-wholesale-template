import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import net from 'node:net';
import assert from 'node:assert/strict';
import {spawn,spawnSync} from 'node:child_process';
const tooling=path.resolve(import.meta.dirname,'..');
const zip=path.resolve(process.argv[2]||'');
const operator=process.env.PET_TEST_OPERATOR_ENV;
if(!operator||!process.env.PET_TEST_REPORT||!fs.existsSync(zip))throw Error('Usage: set PET_TEST_OPERATOR_ENV, PET_TEST_REPORT, PHP_BINARY, POWERSHELL_BINARY; node tests/install-from-zip.mjs ARCHIVE.zip');
const php=process.env.PHP_BINARY||'php',pwsh=process.env.POWERSHELL_BINARY||'pwsh';
const runId=crypto.randomBytes(6).toString('hex'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'pet-release-install-'));
const sourceDigest=crypto.createHash('sha256').update(fs.readFileSync(zip)).digest('hex');
const evidence={runId,kind:'clean-zip-install',startedAt:new Date().toISOString(),archiveSha256:sourceDigest,steps:[],success:false,limitations:['Local PHP development server and MariaDB only; no production TLS/nginx/Docker certification.','No original business database is queried or selected.']};
const resources=[],servers=[];
const save=()=>{fs.mkdirSync(path.dirname(process.env.PET_TEST_REPORT),{recursive:true});fs.writeFileSync(process.env.PET_TEST_REPORT,JSON.stringify(evidence,null,2));};
const check=(name,ok)=>{assert.ok(ok,name);evidence.steps.push({name,passed:true});save();console.log('PASS '+name)};
function envFor(file){const env={...process.env};for(const key of Object.keys(env))if(/^(DB_|TEST_DB_|APP_URL$|APP_ENV_FILE$|UPLOAD_|BRAND_FILE$|SESSION_SECURE$)/.test(key))delete env[key];return {...env,APP_ENV_FILE:file};}
function cli(cwd,args,env,input,ok=true){const r=spawnSync(php,args,{cwd,env,encoding:'utf8',input});if(ok&&r.status!==0)throw Error('PHP command failed: '+args[0]+'\n'+r.stderr);return r;}
function shell(args){const r=spawnSync(pwsh,['-NoProfile','-File',path.join(tooling,'scripts/archive-release.ps1'),...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return JSON.parse(r.stdout)}
async function freePort(){const s=net.createServer();await new Promise((r,j)=>s.listen(0,'127.0.0.1',r).on('error',j));const p=s.address().port;await new Promise(r=>s.close(r));return p;}
async function stop(instance){const child=instance.server;if(child&&child.exitCode===null&&child.signalCode===null){const done=new Promise(r=>child.once('exit',r));child.kill();await done;}instance.server=null;}
async function start(instance){const log=fs.openSync(path.join(instance.dir,'storage/server.log'),'a');const child=spawn(php,['-d',`session.save_path=${path.join(instance.dir,'storage/sessions')}`,'-d',`upload_tmp_dir=${path.join(instance.dir,'storage/tmp')}`,'-d','display_errors=0','-S',`127.0.0.1:${instance.port}`,'router.php'],{cwd:instance.dir,env:instance.env,stdio:['ignore',log,log],windowsHide:true});instance.server=child;if(!servers.includes(instance))servers.push(instance);fs.closeSync(log);for(let n=0;n<80;n++){if(child.exitCode!==null)throw Error('Server stopped during startup');try{const r=await fetch(instance.url+'/get_brand.php',{headers:{Connection:'close'}});await r.arrayBuffer();if(r.ok)return}catch{}await new Promise(r=>setTimeout(r,100));}throw Error('Server did not start')}
function browser(instance){let cookie='';const request=async(url,options={})=>{const headers=new Headers(options.headers);headers.set('Connection','close');if(cookie)headers.set('Cookie',cookie);const r=await fetch(instance.url+url,{...options,headers,redirect:'manual'});if(r.headers.getSetCookie().length)cookie=r.headers.getSetCookie().map(x=>x.split(';')[0]).join('; ');const body=await r.arrayBuffer();return new Response(body,{status:r.status,headers:r.headers})};request.cookie=()=>cookie;return request;}
const body=x=>new URLSearchParams(x);
async function auth(request,password){const page=await (await request('/admin/login.php')).text(),token=page.match(/name="csrf-token" content="([a-f0-9]+)"/)?.[1];assert.ok(token);const r=await request('/admin/api/login.php',{method:'POST',body:body({username:'install-admin',password,csrf_token:token})});assert.equal(r.status,200);return (await (await request('/admin/products.php')).text()).match(/name="csrf-token" content="([a-f0-9]+)"/)[1];}
const operatorConfig=Object.fromEntries(fs.readFileSync(operator,'utf8').split(/\r?\n/).filter(l=>/^[A-Z_]+=/.test(l)).map(l=>{const p=l.indexOf('=');return[l.slice(0,p),l.slice(p+1).replace(/^(['"])(.*)\1$/,'$2')]}));
const imageBytes=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==','base64');
try{
 check('ZIP entries and every manifest digest verified',shell(['-Mode','Verify','-Archive',zip]).Files>1);
 for(const label of ['a','b']){
  const dir=path.join(temp,label);shell(['-Mode','Extract','-Archive',zip,'-Destination',dir]);
  check(label+': extract contains no application .env or uploads',!fs.existsSync(path.join(dir,'.env'))&&fs.readdirSync(path.join(dir,'uploads')).join(',')==='.htaccess');
  const runtime=cli(dir,['-r',"echo json_encode(['php'=>PHP_VERSION,'pdo_mysql'=>extension_loaded('pdo_mysql'),'fileinfo'=>extension_loaded('fileinfo'),'mbstring'=>extension_loaded('mbstring')]);"],envFor(path.join(dir,'missing.env')));
  const dependencies=JSON.parse(runtime.stdout);check(label+': runtime prerequisites present',dependencies.pdo_mysql&&dependencies.fileinfo&&dependencies.mbstring);
  const db=`pet_template_test_${runId}_${label}`,user=`pet_inst_${runId}_${label}`,password=crypto.randomBytes(24).toString('hex');
  cli(tooling,[path.join(tooling,'tests/isolated-db.php'),'create',db,user],envFor(operator),password+'\n');resources.push({db,user});
  const port=await freePort();const envFile=path.join(dir,'.env');
  // Start from the public example rather than copying any application env.
  const values={APP_URL:`http://127.0.0.1:${port}`,DB_HOST:operatorConfig.DB_HOST||'127.0.0.1',DB_PORT:operatorConfig.DB_PORT||'3306',DB_NAME:db,DB_USER:user,DB_PASSWORD:password,UPLOAD_DIR:`uploads/instance_${label}`,UPLOAD_MAX_BYTES:'5242880',BRAND_FILE:'config/brand.local.json',SESSION_SECURE:'0'};
  let example=fs.readFileSync(path.join(dir,'.env.example'),'utf8');for(const[k,v]of Object.entries(values))example=example.replace(new RegExp('^'+k+'=.*$','m'),k+'='+v);fs.writeFileSync(envFile,example,{mode:0o600});
  fs.copyFileSync(path.join(dir,'config/brand.example.json'),path.join(dir,'config/brand.local.json'));
  for(const d of ['storage/sessions','storage/tmp',`uploads/instance_${label}`])fs.mkdirSync(path.join(dir,d),{recursive:true});
  const instance={dir,port,url:values.APP_URL,env:envFor(envFile),db,user,password,adminPassword:crypto.randomBytes(24).toString('hex')};
  const doctor=JSON.parse(cli(dir,['scripts/doctor.php'],instance.env).stdout);check(label+': README environment and dedicated DB connection pass doctor',doctor.success);
  const wrongEnv=path.join(dir,'storage/wrong.env');fs.writeFileSync(wrongEnv,example.replace(/^DB_PASSWORD=.*$/m,'DB_PASSWORD=intentionally-incorrect'));
  const wrong=cli(dir,['scripts/doctor.php'],envFor(wrongEnv),undefined,false);check(label+': install failure gives controlled actionable error',wrong.status!==0&&wrong.stdout.includes('Check the dedicated database')&&!wrong.stdout.includes(password));
  check(label+': corrected environment recovers without deleting data',JSON.parse(cli(dir,['scripts/doctor.php'],instance.env).stdout).success);
  check(label+': migrations initialize a new database',cli(dir,['scripts/migrate.php'],instance.env).stdout.includes('Applied:'));
  check(label+': migration rerun is idempotent',cli(dir,['scripts/migrate.php'],instance.env).stdout.includes('Already applied:'));
  cli(dir,['-r',"require 'app/bootstrap.php'; app_db()->exec('UPDATE schema_migrations SET checksum = NULL');"],instance.env);
  const legacy=cli(dir,['scripts/migrate.php'],instance.env,undefined,false);
  check(label+': legacy migration checksum adoption requires explicit consent',legacy.status!==0&&legacy.stderr.includes('lacks checksum')&&cli(dir,['scripts/migrate.php','--adopt-checksums'],instance.env).status===0);
  const migration=path.join(dir,'database/001_catalog.sql'),original=fs.readFileSync(migration);fs.appendFileSync(migration,'\n-- intentional isolated drift test\n');
  const drift=cli(dir,['scripts/migrate.php'],instance.env,undefined,false);fs.writeFileSync(migration,original);
  check(label+': edited historical migration is rejected and restoration recovers',drift.status!==0&&drift.stderr.includes('checksum mismatch')&&cli(dir,['scripts/migrate.php'],instance.env).status===0);
  const short=cli(dir,['scripts/create-admin.php','install-admin'],instance.env,'short\n',false);check(label+': weak initial administrator password rejected',short.status!==0);
  check(label+': first administrator created via stdin',cli(dir,['scripts/create-admin.php','install-admin'],instance.env,instance.adminPassword+'\n').stdout.includes('Administrator created'));
  check(label+': duplicate administrator cannot overwrite account',cli(dir,['scripts/create-admin.php','install-admin'],instance.env,instance.adminPassword+'\n',false).status!==0);
  await start(instance);instance.client=browser(instance);instance.csrf=await auth(instance.client,instance.adminPassword);check(label+': initialized administrator can sign in',!!instance.csrf);
  resources.at(-1).instance=instance;
 }
 const a=resources[0].instance,b=resources[1].instance,post=async(route,values)=>a.client(route,{method:'POST',body:body({...values,csrf_token:a.csrf})});
 check('Instance B initially empty',(await(await b.client('/get_products.php')).json()).data.length===0);
 const fixture={id:'',name:'EXAMPLE INSTALL ONLY',category:'Install sample',price:'10.00',description:'Disposable install validation fixture',images:'[]',colors:'["Gray"]',sizes:'["S"]',specs:'{"S":12}',featured:'0',status:'on'};
 let r=await post('/admin/api/save_product.php',fixture),j=await r.json();check('Clean install product create',r.ok&&j.success);const pid=j.id;
 check('Created product publicly visible',(await(await a.client('/get_product.php?id='+pid)).json()).data.name===fixture.name);
 const upload=new FormData();upload.set('image',new Blob([imageBytes],{type:'image/png'}),'sample.png');r=await a.client('/admin/api/upload_image.php',{method:'POST',body:upload,headers:{'X-CSRF-Token':a.csrf}});j=await r.json();check('Clean install real image upload',r.ok&&j.success);const image=j.url;
 r=await post('/admin/api/save_product.php',{...fixture,id:pid,name:'EXAMPLE INSTALL UPDATED',images:JSON.stringify([image])});check('Clean install edit persists',r.ok&&(await(await a.client('/get_product.php?id='+pid)).json()).data.name==='EXAMPLE INSTALL UPDATED');
 const csv=new FormData();csv.set('action','preview');csv.set('csv',new Blob([fs.readFileSync(path.join(a.dir,'examples/products.csv'))]),'products.csv');j=await(await a.client('/admin/api/csv.php',{method:'POST',body:csv,headers:{'X-CSRF-Token':a.csrf}})).json();check('Clean install CSV preview',j.success&&j.token&&j.data.errors.length===0);
 check('Clean install CSV confirmation',(await(await post('/admin/api/csv.php',{action:'confirm',token:j.token})).json()).count===1);
 const exported=await(await a.client('/admin/api/csv.php?action=export')).text();check('Clean install CSV export includes instance data',exported.includes('EXAMPLE INSTALL UPDATED')&&exported.includes('demo-only-001'));
 check('Other instance catalog cannot see A',(await(await b.client('/get_products.php')).json()).data.length===0);
 check('Other instance export excludes A',!(await(await b.client('/admin/api/csv.php?action=export')).text()).includes('EXAMPLE INSTALL UPDATED'));
 check('Other instance storage cannot access A image',(await b.client(image)).status===404);
 const foreign=await fetch(b.url+'/admin/api/get_products.php',{headers:{Cookie:a.client.cookie(),Connection:'close'}});await foreign.arrayBuffer();check('Cross-instance cookie rejected',foreign.status===401);
 const denied=cli(a.dir,['-r',"require 'app/bootstrap.php'; try { app_db()->query('SELECT id FROM `"+b.db+"`.products'); exit(2); } catch (PDOException $e) { echo $e->errorInfo[1]; }"],a.env);check('Database grants prevent cross-instance SQL',['1142','1044'].includes(denied.stdout));
 await stop(a);await start(a);a.client=browser(a);a.csrf=await auth(a.client,a.adminPassword);
 check('Restart retains products and attached image',(await(await a.client('/get_product.php?id='+pid)).json()).data.images[0]===image&&(await a.client(image)).status===200);
 check('Restart retains imported CSV data',(await(await a.client('/admin/api/csv.php?action=export')).text()).includes('demo-only-001'));
 check('Product deletion after restart',(await(await post('/admin/api/delete_product.php',{id:pid})).json()).success&&(await a.client('/get_product.php?id='+pid)).status===404);
 check('Deletion retains shared-storage file until explicit cleanup',(await a.client(image)).status===200);
 evidence.success=true;
}catch(error){evidence.error=error.message;throw error;}
finally{
 const errors=[];for(const s of servers)try{await stop(s)}catch(e){errors.push('server cleanup failed')}
 for(const {db,user}of resources)try{cli(tooling,[path.join(tooling,'tests/isolated-db.php'),'drop',db,user],envFor(operator))}catch(e){errors.push('test database/user cleanup failed')}
 evidence.finishedAt=new Date().toISOString();evidence.passed=evidence.steps.length;evidence.cleanup={success:errors.length===0,errors};evidence.success=evidence.success&&errors.length===0;evidence.temporaryDirectory=temp;
 fs.mkdirSync(path.dirname(process.env.PET_TEST_REPORT),{recursive:true});fs.writeFileSync(process.env.PET_TEST_REPORT,JSON.stringify(evidence,null,2));
 if(errors.length)throw Error(errors.join('; '));
 console.log(`${evidence.passed} clean-install checks; success=${evidence.success}. Private temporary installations retained for inspection.`);
}
