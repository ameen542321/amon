// اختبارات Chromium مع بيانات اصطناعية وخادم محلي؛ لا تتصل ببيانات الإنتاج.
import http from 'node:http';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join, extname } from 'node:path';
const publicRoot = join(process.cwd(), 'public');
const phpFixture = String.raw`require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $store=new App\Models\Store; $store->id=9; $store->name='متجر التجربة'; $request=Illuminate\Http\Request::create('/accountant/dashboard'); $route=new Illuminate\Routing\Route('GET','accountant/dashboard',[]); $route->name('accountant.dashboard'); $request->setRouteResolver(fn()=>$route); $app->instance('request',$request); $data=['role'=>'accountant','workspaceStore'=>$store,'workspaceStores'=>collect([$store]),'workspaceStoreId'=>9,'workspaceLinks'=>(new App\Services\WorkspaceUiService)->links('accountant',$store)]; echo '<div class="ui-workspace-layout flex">'.view('dashboard.partials.workspace-sidebar',$data)->render().'<div class="min-w-0 flex-1">'.view('dashboard.partials.workspace-tools',$data)->render();`;
const renderer=spawnSync(process.env.PHP_BINARY??'php',['-r',phpFixture],{encoding:'utf8'});
if(renderer.status!==0) throw new Error(renderer.stderr);
const manifest=JSON.parse(readFileSync(join(publicRoot,'build/manifest.json')));
const script='/build/'+manifest['resources/js/app.js'].file;
const styles=[manifest['resources/css/app.css'].file,...(manifest['resources/js/app.js'].css??[])].map(file=>`<link rel="stylesheet" href="/build/${file}">`).join('');
const fixture=`<!doctype html><html lang="ar" dir="rtl" class="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles}</head><body class="ui-page" data-client-account-scope="accountant:1">${renderer.stdout}<main class="p-4"><form method="get" data-workspace-filters="from,to,status"><label>من<input class="ui-input" type="date" name="from" value="2026-10-14"></label><label>إلى<input class="ui-input" type="date" name="to" value="2026-10-14"></label><label><input type="checkbox" name="status" value="1" checked>بانتظار الاعتماد</label></form><div class="ui-table-wrap"><table data-workspace-table="fixture" class="ui-table"><thead><tr><th>المنتج</th><th>التكلفة</th><th>المورد</th></tr></thead><tbody><tr><td>منتج ب</td><td>1,200.50</td><td>=CMD()</td></tr><tr><td>منتج أ</td><td>25.00</td><td>مورد</td></tr></tbody></table></div><div data-workspace-record-list><article data-workspace-record data-record-title="طلبية ب" data-record-date="2026-10-14" data-record-reference="PO-1" data-record-status="معلق" class="ui-card p-4">طلبية ب</article><article data-workspace-record data-record-title="طلبية أ" data-record-date="2026-10-13" data-record-reference="PO-2" data-record-status="معلق" class="ui-card p-4">طلبية أ</article></div></main></div></div><script>window.requests=[];window.downloads=[];window.apiMode='ok';window.savedBlob=null;const realBlob=URL.createObjectURL;URL.createObjectURL=(blob)=>{window.savedBlob=blob;return realBlob(blob)};document.addEventListener('click',event=>{if(event.target.matches('a[download]'))event.preventDefault()});const realFetch=fetch;window.fetch=async(url,options)=>{const path=new URL(url,location.origin).pathname;if(path.includes('/api/v1/workspace/')){window.requests.push({url:String(url),options});if(window.apiMode==='error')throw new TypeError('failure');if(window.apiMode==='slow')await new Promise(r=>setTimeout(r,600));const data=path.endsWith('tasks')?{total:1,items:[{title:'مراجعة طلبية',detail:'بانتظار الاعتماد',url:'/accountant/purchase-orders/1',date:'2026-10-14'}]}:{items:[{title:'منتج <img src=x onerror=alert(1)>',detail:'متجر التجربة',url:'/accountant/quick-sale?workspace_search=123'}]};return new Response(JSON.stringify({data,meta:{account_scope:window.apiMode==='foreign'?'accountant:2':'accountant:1'}}),{headers:{'Content-Type':'application/json'}});}return realFetch(url,options)};document.addEventListener('submit',event=>{if(event.target.matches('[data-workspace-filters]')){event.preventDefault();window.appliedFilter=Object.fromEntries(new FormData(event.target))}});</script><script type="module" src="${script}"></script></body></html>`;
const mime = {
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.html': 'text/html',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf',
    '.webmanifest': 'application/manifest+json',
};
const server = http.createServer((req, res) => {
    const pathname = new URL(req.url, 'http://localhost').pathname;
    if (pathname === '/' || pathname === '/accountant/dashboard') {
        res.writeHead(200, {
            'Content-Type': 'text/html',
            'Cache-Control': 'no-store',
        });
        res.end(fixture);
        return;
    }
    const path = join(publicRoot, pathname);
    if (existsSync(path) && !pathname.includes('..')) {
        res.writeHead(200, {
            'Content-Type': mime[extname(path)] || 'application/octet-stream',
            'Cache-Control':
                pathname === '/sw.js' ? 'no-cache' : 'public,max-age=3600',
        });
        res.end(readFileSync(path));
        return;
    }
    res.writeHead(404);
    res.end('missing');
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;

const profile = mkdtempSync(join(tmpdir(), 'amon-workspace-cdp-'));
const screenshotDirectory = process.env.WORKSPACE_SCREENSHOT_DIR ?? profile;
mkdirSync(screenshotDirectory, { recursive: true });
const browser = spawn(
    process.env.CHROME_PATH ?? '/usr/bin/chromium',
    [
        '--no-sandbox',
        '--disable-breakpad',
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--remote-debugging-port=0',
        `--user-data-dir=${profile}`,
        origin,
    ],
    { stdio: 'ignore' },
);
const delay = (ms) => new Promise((r) => setTimeout(r, ms));
let socket;
try {
    for (
        let i = 0;
        i < 100 && !existsSync(join(profile, 'DevToolsActivePort'));
        i++
    )
        await delay(100);
    const [port] = readFileSync(
        join(profile, 'DevToolsActivePort'),
        'utf8',
    ).split('\n');
    const targets = await (
        await fetch(`http://127.0.0.1:${port}/json/list`)
    ).json();
    socket = new WebSocket(
        targets.find((t) => t.type === 'page').webSocketDebuggerUrl,
    );
    await new Promise((r) =>
        socket.addEventListener('open', r, { once: true }),
    );
    const pending = new Map();
    let id = 0;
    const errors = [];
    socket.addEventListener('message', ({ data }) => {
        const msg = JSON.parse(data);
        if (msg.method === 'Runtime.exceptionThrown')
            errors.push(msg.params.exceptionDetails);
        if (msg.id && pending.has(msg.id)) {
            const { r, j } = pending.get(msg.id);
            pending.delete(msg.id);
            if (msg.error) j(new Error(msg.error.message));
            else r(msg.result);
        }
    });
    const send = (method, params = {}) =>
        new Promise((r, j) => {
            const key = ++id;
            pending.set(key, { r, j });
            socket.send(JSON.stringify({ id: key, method, params }));
        });
    const evalJS = async (expression) => {
        const result = await send('Runtime.evaluate', {
            expression: expression.startsWith('const ') ? `{${expression}}` : expression,
            awaitPromise: true,
            returnByValue: true,
        });
        if (result.exceptionDetails)
            throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const wait = async (expression) => {
        for (let i = 0; i < 100; i++) {
            if (await evalJS(expression)) return;
            await delay(100);
        }
        throw new Error('timeout: ' + expression);
    };
    await send('Runtime.enable');
    await send('Page.enable');
    await wait("document.readyState==='complete' && window.Alpine && document.querySelector('[data-workspace-table] tbody input')");
    await evalJS("document.querySelector('[data-workspace-collapse]').click()");
    assert.equal(await evalJS("document.body.classList.contains('ui-workspace-collapsed')"),true);
    await evalJS("document.querySelector('[data-workspace-open=settings]').click();const font=document.querySelector('[data-workspace-preference=font]');font.value='large';font.dispatchEvent(new Event('change'));const theme=document.querySelector('[data-workspace-preference=theme]');theme.value='light';theme.dispatchEvent(new Event('change'))");
    assert.equal(await evalJS("document.documentElement.dataset.workspaceFont"),'large');
    assert.equal(await evalJS("JSON.parse(localStorage.getItem('carled:accountant:1:store:all:workspace:display')).theme"),'light');
    await evalJS("document.querySelector('[data-workspace-dialog=settings] [data-workspace-close]').click();document.dispatchEvent(new KeyboardEvent('keydown',{key:'k',ctrlKey:true,bubbles:true}))");
    assert.equal(await evalJS("document.querySelector('[data-workspace-dialog=search]').open && document.activeElement.id==='workspace-query'"),true);
    await evalJS("const query=document.querySelector('[data-workspace-query]');query.value='منتج';query.dispatchEvent(new Event('input'))");
    await wait("document.querySelector('[data-workspace-results] a')");
    assert.equal(await evalJS("document.querySelector('[data-workspace-results]').querySelector('img')===null"),true);
    assert.equal(await evalJS("window.requests.at(-1).options.cache"),'no-store');
    await evalJS("document.querySelector('[data-workspace-dialog=search] [data-workspace-close]').click();document.querySelector('[data-workspace-open=tasks]').click()");
    await wait("document.querySelector('[data-workspace-task-count]').textContent==='1'");
    assert.equal(await evalJS("document.querySelector('[data-workspace-tasks] a').textContent.includes('مراجعة طلبية')"),true);
    await evalJS("window.apiMode='foreign';document.querySelector('[data-workspace-refresh]').click()");
    await wait("document.querySelector('[data-workspace-tasks-status]').textContent.includes('تعذر')");
    await evalJS("window.apiMode='ok';document.querySelector('[data-workspace-dialog=tasks] [data-workspace-close]').click()");
    await evalJS("const filter=document.querySelector('[data-workspace-filters]');filter.querySelector('input[aria-label]').value='يوم واحد';[...filter.querySelectorAll('button')].find(x=>x.textContent==='حفظ الفلتر').click();filter.elements.from.value='2026-10-01';filter.elements.status.checked=false;filter.querySelector('select').value='0';[...filter.querySelectorAll('button')].find(x=>x.textContent==='تطبيق المحفوظ').click()");
    assert.deepEqual(await evalJS('window.appliedFilter'),{from:'2026-10-14',to:'2026-10-14',status:'1'});
    assert.equal(await evalJS("localStorage.getItem('carled:accountant:2:store:9:workspace:filters:/accountant/dashboard:0')"),null);
    await evalJS("document.querySelector('[data-workspace-table] thead th:nth-child(3) button').click()");
    assert.equal(await evalJS("document.querySelector('[data-workspace-table] tbody tr:first-child td:nth-child(3)').textContent"),'25.00');
    await evalJS("const toolbar=document.querySelector('[data-workspace-table]').closest('.ui-table-wrap').previousElementSibling;toolbar.querySelector('input[type=checkbox]').click()");
    assert.equal(await evalJS("document.querySelectorAll('[data-workspace-table] tbody input:checked').length"),2);
    await evalJS("const toolbar=document.querySelector('[data-workspace-table]').closest('.ui-table-wrap').previousElementSibling;[...toolbar.querySelectorAll('button')].find(x=>x.textContent==='تصدير المحدد CSV').click()");
    assert.match(await evalJS('window.savedBlob.text()'), /"'=CMD\(\)"/);
    await evalJS("const toolbar=document.querySelector('[data-workspace-table]').closest('.ui-table-wrap').previousElementSibling;toolbar.querySelector('details input').click()");
    assert.equal(await evalJS("document.querySelector('[data-workspace-table] tbody tr td:nth-child(3)').hidden"),true);
    for (const [width,height,theme] of [[390,844,'dark'],[390,844,'light'],[1440,900,'dark'],[1440,900,'light']]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
        await evalJS(`document.documentElement.className='${theme}'`);
        await delay(250);
        assert.equal(await evalJS('document.documentElement.scrollWidth<=innerWidth'),true,`overflow ${width} ${theme}`);
        assert.equal(await evalJS(`(()=>{const el=document.querySelector('.ui-workspace-toolbar button');const css=getComputedStyle(el);const luminance=color=>{const rgb=color.match(/[0-9.]+/g).slice(0,3).map(v=>{v=Number(v)/255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});return .2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2]};const a=luminance(css.color),b=luminance(css.backgroundColor);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05)>=4.5})()`),true,`contrast ${theme}`);
        assert.equal(await evalJS("getComputedStyle(document.querySelector('.ui-workspace-bottom')).display==='none'"),width>=1024);
        const result=await send('Page.captureScreenshot',{format:'png'});writeFileSync(join(screenshotDirectory,`workspace-${width}-${theme}.png`),Buffer.from(result.data,'base64'));
        await evalJS("document.querySelector('[data-workspace-table]').scrollIntoView({block:'center'})");
        const tableShot=await send('Page.captureScreenshot',{format:'png'});writeFileSync(join(screenshotDirectory,`table-${width}-${theme}.png`),Buffer.from(tableShot.data,'base64'));
        await evalJS('scrollTo(0,0)');
    }
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await evalJS("document.querySelector('[data-workspace-open=settings]').click();const view=document.querySelector('[data-workspace-preference=view]');view.value='table';view.dispatchEvent(new Event('change'));document.querySelector('[data-workspace-dialog=settings] [data-workspace-close]').click()");
    assert.equal(await evalJS("getComputedStyle(document.querySelector('[data-workspace-table]')).display"),'table');
    await evalJS("document.querySelector('[data-workspace-open=tasks]').click()");
    await send('Network.enable');await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
    await wait("document.querySelector('[data-workspace-tasks]').children.length===0");
    await evalJS("document.querySelector('[data-workspace-refresh]').click()");
    await wait("document.querySelector('[data-workspace-tasks-status]').textContent.includes('اتصال')");
    await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await evalJS("document.querySelector('[data-workspace-dialog=tasks] [data-workspace-close]').click();window.apiMode='slow';document.querySelector('[data-workspace-open=search]').click();document.querySelector('[data-workspace-dialog=search] [data-workspace-close]').click()");
    await delay(800);
    assert.equal(await evalJS("document.querySelector('[data-workspace-results]').children.length"),0);
    await send('Page.reload');
    await wait("document.readyState==='complete' && document.querySelector('[data-workspace-table] tbody input')");
    assert.equal(await evalJS("document.documentElement.dataset.workspaceFont==='large' && document.body.classList.contains('ui-workspace-collapsed')"),true);
    assert.equal(await evalJS("document.querySelector('[data-workspace-filters] select').options.length"),2);
    assert.equal(await evalJS("document.querySelector('[data-workspace-table] tbody td:nth-child(3)').hidden"),true);
    await evalJS("localStorage.setItem('carled-active-account','accountant:2');window.dispatchEvent(new StorageEvent('storage',{key:'carled-active-account',newValue:'accountant:2'}))");
    assert.equal(await evalJS("document.querySelector('[data-workspace-tasks]').children.length"),0);
    assert.deepEqual(errors,[]);
    console.log('PASS: Chromium RTL mobile/desktop light/dark, sidebar, settings, shortcut and focus, private search, safe text, tasks, saved day filter and checkbox, scoped storage, sorting, selection, CSV formula defense, columns, table mode, offline and stale response cancellation.');
} finally {
    socket?.close();
    browser.kill();
    server.close();
    await delay(300);
    rmSync(profile, { recursive: true, force: true });
}
