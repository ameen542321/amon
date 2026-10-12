// اختبارات Chromium مع بيانات اصطناعية وخادم محلي؛ لا تتصل ببيانات الإنتاج.
import http from 'node:http';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join, extname } from 'node:path';
const publicRoot = join(process.cwd(), 'public');
const renderer = spawnSync(
    process.env.PHP_BINARY ?? 'php',
    [
        '-r',
        "require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo view('components.pwa-install-panel')->render();",
    ],
    { encoding: 'utf8' },
);
if (renderer.status !== 0)
    throw new Error('Unable to render the PWA fixture: ' + renderer.stderr);
const manifest = JSON.parse(
    readFileSync(join(publicRoot, 'build/manifest.json')),
);
const script = '/build/' + manifest['resources/js/app.js'].file;
const styles = [
    manifest['resources/css/app.css'].file,
    ...(manifest['resources/js/app.js'].css ?? []),
]
    .map((file) => `<link rel="stylesheet" href="/build/${file}">`)
    .join('');
const component = renderer.stdout;
const header = `<link rel="manifest" href="/manifest.webmanifest"><meta name="csrf-token" content="fake"><meta name="pwa-build-version" content="browser-test-1"><meta name="pwa-service-worker-enabled" content="true"><meta name="pwa-updates-enabled" content="true"><meta name="pwa-outbox-enabled" content="true">`;
const fixture = `<!doctype html><html dir="rtl" lang="ar" class="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${header}${styles}</head><body class="ui-page p-6" data-client-account-scope="accountant:1"><main><h1 class="ui-title">اختبار PWA</h1><form method="post" id="dirty-form"><label class="ui-label">بيانات غير محفوظة<input name="test" class="ui-input" value="original"></label></form><form method="POST" action="/accountant/inventory-counts/3/items" data-inventory-count-form data-inventory-count-storage-key="inventory-count:accountant:1:store:9:session:3" data-inventory-count-account-scope="accountant:1" data-inventory-count-store-id="9" data-inventory-count-version="old"><div data-inventory-count-item><input class="ui-input" name="items[1][accountant_quantity]" value="2" data-inventory-count-quantity><select name="items[1][unit_type]" data-inventory-count-unit><option value="piece">حبة</option></select></div></form><p data-inventory-count-draft-status></p></main>${component}<script>window.testWrites=[];window.testMode='ok';window.addEventListener('carled:pwa-status',event=>{if(event.detail.status==='registered')window.pwaReady=true;});window.confirm=()=>true;const realFetch=window.fetch.bind(window);window.fetch=async(url,options)=>{if(String(url).includes('/api/v1/')||String(url).includes('/inventory-counts/3/items')){window.testWrites.push({url:String(url),method:options?.method||'GET'});if(window.testMode==='network')throw new TypeError('Network');const data=String(url).endsWith('app-context')?{account:{type:'accountant',id:1}}:String(url).endsWith('preferences')?{announcements:true,messages:true,operations:true}:{saved:true,session_id:3,session_version:'new'};return new Response(JSON.stringify({data,meta:{}}),{headers:{'Content-Type':'application/json'}});}return realFetch(url,options);};</script><script type="module" src="${script}"></script></body></html>`;
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

const profile = mkdtempSync(join(tmpdir(), 'amon-pwa-cdp-'));
const screenshotDirectory = process.env.PWA_SCREENSHOT_DIR ?? profile;
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
            expression,
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
    await wait(
        "document.readyState==='complete' && window.Alpine && document.querySelector('[data-pwa-center-open]')",
    );
    await evalJS("document.querySelector('[data-pwa-center-open]').click()");
    await wait(
        "document.querySelector('[data-pwa-server-status]').textContent.includes('الحساب مؤكد')",
    );
    assert.equal(
        await evalJS(
            "document.activeElement.hasAttribute('data-pwa-center-close')",
        ),
        true,
    );
    await evalJS(
        `(async()=>{const db=await new Promise((r,j)=>{const q=indexedDB.open('carled-client',3);q.onsuccess=()=>r(q.result);q.onerror=()=>j(q.error)});const tx=db.transaction(['drafts','outbox'],'readwrite');tx.objectStore('drafts').put({key:'own',accountScope:'accountant:1',storeId:9,draftType:'purchase-order',savedAt:Date.now(),supplier_name:'A'});tx.objectStore('drafts').put({key:'other',accountScope:'accountant:2',storeId:10,draftType:'purchase-order',savedAt:Date.now(),supplier_name:'B'});tx.objectStore('outbox').put({localId:'test-request',operationType:'inventory-count-draft',accountScope:'accountant:1',storeId:9,url:'/accountant/inventory-counts/3/items',method:'PUT',payload:{session_version:'old','items[1][accountant_quantity]':'5'},serverVersion:'old',idempotencyKey:'stable-browser-key',status:'failed',attempts:5,createdAt:Date.now(),lastError:{message:'<img src=x onerror=alert(1)>'}});await new Promise(r=>tx.oncomplete=r);db.close();window.dispatchEvent(new CustomEvent('carled:outbox-changed'));})()`,
    );
    await wait(
        "document.querySelector('[data-pwa-pending-count]').textContent==='1'",
    );
    assert.equal(
        await evalJS(
            "document.querySelector('[data-pwa-drafts-list]').children.length",
        ),
        1,
    );
    assert.equal(
        await evalJS(
            "document.querySelector('[data-pwa-outbox-list] img')===null",
        ),
        true,
    );
    await evalJS(
        "[...document.querySelectorAll('[data-pwa-outbox-list] button')].find(b=>b.textContent==='إعادة المحاولة').click()",
    );
    await wait(
        "document.querySelector('[data-pwa-pending-count]').textContent==='0'",
    );
    assert.equal(
        await evalJS(
            "window.testWrites.filter(x=>x.method==='PUT'&&x.url.includes('/inventory-counts/')).length",
        ),
        1,
    );
    await evalJS(
        "document.querySelector('[data-pwa-preferences] [name=announcements]').checked=false;document.querySelector('[data-pwa-preferences]').requestSubmit()",
    );
    await wait(
        "document.querySelector('[data-pwa-center-feedback]').textContent.includes('حُفظت')",
    );
    for (const [width, height, theme] of [
        [390, 844, 'dark'],
        [390, 844, 'light'],
        [1440, 900, 'dark'],
        [1440, 900, 'light'],
    ]) {
        await send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile: width < 500,
        });
        await evalJS(`document.documentElement.className='${theme}'`);
        assert.equal(
            await evalJS('document.documentElement.scrollWidth<=innerWidth'),
            true,
            `overflow ${width} ${theme}`,
        );
        const result = await send('Page.captureScreenshot', { format: 'png' });
        writeFileSync(
            join(screenshotDirectory, `center-${width}-${theme}.png`),
            Buffer.from(result.data, 'base64'),
        );
    }
    await evalJS(
        "document.querySelector('[data-pwa-center-close]').click();{const field=document.querySelector('#dirty-form input');field.value='changed';field.dispatchEvent(new Event('input',{bubbles:true}))}",
    );
    const blocked = await evalJS(
        "(async()=>{const pending=[];window.dispatchEvent(new CustomEvent('carled:pwa-prepare-update',{detail:{pending}}));return (await Promise.allSettled(pending)).some(result=>result.status==='rejected')})()",
    );
    assert.equal(blocked, true);
    await evalJS(
        "{const field=document.querySelector('#dirty-form input');field.value='original';field.dispatchEvent(new Event('input',{bubbles:true}))}",
    );
    const allowed = await evalJS(
        "(async()=>{const pending=[];window.dispatchEvent(new CustomEvent('carled:pwa-prepare-update',{detail:{pending}}));return (await Promise.allSettled(pending)).every(result=>result.status==='fulfilled')})()",
    );
    assert.equal(allowed, true);
    await wait(
        "document.querySelector('[data-inventory-count-form]').dataset.pwaDraftSafe==='true'",
    );
    const failedPreparation = await evalJS(
        "(async()=>{const open=indexedDB.open.bind(indexedDB);indexedDB.open=()=>{throw new Error('storage failure')};const pending=[];window.dispatchEvent(new CustomEvent('carled:pwa-prepare-update',{detail:{pending}}));const results=await Promise.allSettled(pending);indexedDB.open=open;return results.some(result=>result.status==='rejected')})()",
    );
    assert.equal(failedPreparation, true);
    const flushed = await evalJS(
        "(async()=>{const input=document.querySelector('[data-inventory-count-quantity]');input.value='6';input.dispatchEvent(new Event('input',{bubbles:true}));const pending=[];window.dispatchEvent(new CustomEvent('carled:pwa-prepare-update',{detail:{pending}}));return (await Promise.allSettled(pending)).every(result=>result.status==='fulfilled')})()",
    );
    assert.equal(flushed, true);
    const preservesUnrelated = await evalJS(
        "(async()=>{window.dispatchEvent(new CustomEvent('carled:outbox-synced',{detail:{accountScope:'accountant:1',localId:'other-session',queuedAt:Date.now()}}));const db=await new Promise(r=>{const q=indexedDB.open('carled-client',3);q.onsuccess=()=>r(q.result)});const draft=await new Promise(r=>{const q=db.transaction('drafts').objectStore('drafts').get('inventory-count:accountant:1:store:9:session:3');q.onsuccess=()=>r(q.result)});db.close();return draft.values['items[1][accountant_quantity]']==='6'})()",
    );
    assert.equal(preservesUnrelated, true);
    await evalJS("document.querySelector('[data-pwa-center-open]').click()");
    await wait("!document.querySelector('[data-pwa-sync]').disabled");
    await send('Network.enable');
    await send('Network.emulateNetworkConditions', {
        offline: true,
        latency: 0,
        downloadThroughput: 0,
        uploadThroughput: 0,
    });
    await wait(
        "document.querySelector('[data-pwa-sync]').disabled && document.querySelector('[data-pwa-preferences-save]').disabled",
    );
    assert.equal(
        await evalJS(
            "document.querySelector('[data-pwa-drafts-list]').children.length",
        ),
        2,
    );
    await send('Network.emulateNetworkConditions', {
        offline: false,
        latency: 0,
        downloadThroughput: 0,
        uploadThroughput: 0,
    });
    await wait("!document.querySelector('[data-pwa-sync]').disabled");
    await evalJS("document.querySelector('[data-pwa-center-close]').click()");
    await wait('Boolean(navigator.serviceWorker.controller)');
    await evalJS('window.beforeReload=true');
    await send('Page.reload');
    await wait(
        "window.beforeReload===undefined && document.readyState==='complete' && window.Alpine && navigator.serviceWorker.controller && window.pwaReady",
    );
    await evalJS(
        "window.testSentinel='retained';navigator.serviceWorker.dispatchEvent(new Event('controllerchange'))",
    );
    await delay(300);
    assert.equal(await evalJS('window.testSentinel'), 'retained');
    assert.equal(
        await evalJS(
            "document.querySelector('[data-pwa-update]').classList.contains('hidden')",
        ),
        false,
    );
    assert.deepEqual(errors, []);
    console.log(
        'PASS: real Chromium center, identity isolation, safe error rendering, retry once, preferences, update dirty guard, storage failure blocks update, unrelated sync preserves draft, controller change does not reload another tab, offline controls, mobile/desktop dark/light.',
    );
} finally {
    socket?.close();
    browser.kill();
    server.close();
    await delay(300);
    rmSync(profile, { recursive: true, force: true });
}
