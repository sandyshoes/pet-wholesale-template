import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
const root=path.resolve(import.meta.dirname,'..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'pet-release-tools-'));
const php=process.env.PHP_BINARY||'php',pwsh=process.env.POWERSHELL_BINARY||'pwsh';const tests=[];
const check=(name,ok)=>{assert.ok(ok,name);tests.push(name);console.log('PASS '+name)};
const run=(exe,args,env={})=>spawnSync(exe,args,{cwd:root,env:{...process.env,...env},encoding:'utf8',windowsHide:true});
const shell=args=>run(pwsh,['-NoProfile','-File',path.join(root,'scripts/archive-release.ps1'),...args]);
const hash=p=>crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const acceptance=path.join(root,'docs/ACCEPTANCE.md'),saved=fs.readFileSync(acceptance);
try{
 fs.writeFileSync(acceptance,'#');const r=run(process.execPath,['scripts/package-release.mjs',path.join(temp,'blocked')]);check('Truncated acceptance blocks packaging',r.status!==0&&r.stderr.includes('truncated')&&!fs.existsSync(path.join(temp,'blocked')));
}finally{fs.writeFileSync(acceptance,saved)}
for(const [name,value,line]of [['BOM','http://localhost:8080','\uFEFFAPP_URL=http://localhost:8080\n'],['quoted',"contains'quote",'PET_ENV_PROBE="contains\'quote"\n'],['literal','a"b"c','PET_ENV_PROBE=a"b"c\n'],['empty','','PET_ENV_PROBE=\n']]){
 const file=path.join(temp,name+'.env');fs.writeFileSync(file,line);const variable=name==='BOM'?'APP_URL':'PET_ENV_PROBE';const env={...process.env,APP_ENV_FILE:file};delete env[variable];
 const result=spawnSync(php,['-r',`require 'app/bootstrap.php'; echo hash('sha256',setting('${variable}'));`],{cwd:root,env,encoding:'utf8'});
 check('Environment parser '+name,result.status===0&&result.stdout===crypto.createHash('sha256').update(value).digest('hex'));
}
const noOperator={...process.env};delete noOperator.PET_TEST_OPERATOR_ENV;const denied=spawnSync(process.execPath,['tests/run.mjs'],{cwd:root,env:noOperator,encoding:'utf8'});check('Regression runner refuses implicit application DB configuration',denied.status!==0&&denied.stderr.includes('PET_TEST_OPERATOR_ENV'));
for(const label of ['one','two']){
 const folder=path.join(temp,label),result=run(process.execPath,['scripts/package-release.mjs',folder],{SOURCE_DATE_EPOCH:'946684800'});check(label+': package allowlist and content scan',result.status===0);
 check(label+': archive create and manifest verification',shell(['-Mode','Create','-Source',folder,'-Archive',path.join(temp,label+'.zip')]).status===0);
}
check('Same sources and SOURCE_DATE_EPOCH produce identical ZIP bytes',hash(path.join(temp,'one.zip'))===hash(path.join(temp,'two.zip')));
const mutate=path.join(temp,'tamper.ps1');fs.writeFileSync(mutate,`param($Archive)\n$z=[IO.Compression.ZipFile]::Open($Archive,[IO.Compression.ZipArchiveMode]::Update)\ntry{$e=$z.GetEntry('README.md');$e.Delete();$e=$z.CreateEntry('README.md');$w=[IO.StreamWriter]::new($e.Open());$w.Write('tampered');$w.Dispose()}finally{$z.Dispose()}\n`);
fs.copyFileSync(path.join(temp,'one.zip'),path.join(temp,'tampered.zip'));run(pwsh,['-NoProfile','-File',mutate,path.join(temp,'tampered.zip')]);check('Archive content tampering rejected',shell(['-Mode','Verify','-Archive',path.join(temp,'tampered.zip')]).status!==0);
const traversal=path.join(temp,'traversal.ps1');fs.writeFileSync(traversal,`param($Archive)\n$z=[IO.Compression.ZipFile]::Open($Archive,[IO.Compression.ZipArchiveMode]::Create)\ntry{$z.CreateEntry('../outside.txt')|Out-Null}finally{$z.Dispose()}\n`);
run(pwsh,['-NoProfile','-File',traversal,path.join(temp,'traversal.zip')]);const target=path.join(temp,'unsafe');check('Unsafe ZIP paths rejected before extraction',shell(['-Mode','Extract','-Archive',path.join(temp,'traversal.zip'),'-Destination',target]).status!==0&&!fs.existsSync(target));
const report={kind:'release-tooling',date:new Date().toISOString(),success:true,passed:tests.length,tests,temporaryDirectory:temp};fs.writeFileSync(process.env.PET_TEST_REPORT||path.join(temp,'RESULTS.json'),JSON.stringify(report,null,2));console.log(tests.length+' tooling checks passed');
