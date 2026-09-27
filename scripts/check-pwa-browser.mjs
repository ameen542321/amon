import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const rawOrigin = process.argv[2];
if (!rawOrigin) {
    console.error('Usage: node scripts/check-pwa-browser.mjs https://example.com');
    process.exit(2);
}

const candidate = new URL(rawOrigin);
const localHost = ['localhost', '127.0.0.1'].includes(candidate.hostname);
if (candidate.protocol !== 'https:' && !localHost) {
    console.error('Browser acceptance requires HTTPS except on localhost.');
    process.exit(2);
}

const origin = candidate.origin;
const browserCandidates = [
    process.env.CHROME_PATH,
    process.platform === 'win32' && process.env.PROGRAMFILES && join(process.env.PROGRAMFILES, 'Google/Chrome/Application/chrome.exe'),
    process.platform === 'win32' && process.env['PROGRAMFILES(X86)'] && join(process.env['PROGRAMFILES(X86)'], 'Microsoft/Edge/Application/msedge.exe'),
    process.platform === 'win32' && process.env.LOCALAPPDATA && join(process.env.LOCALAPPDATA, 'Google/Chrome/Application/chrome.exe'),
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
].filter(Boolean);
const browserPath = browserCandidates.find(existsSync);
if (!browserPath) {
    console.error('Chrome/Edge was not found. Set CHROME_PATH to the browser executable.');
    process.exit(2);
}

const profile = mkdtempSync(join(tmpdir(), 'carled-pwa-browser-'));
const browser = spawn(browserPath, [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, origin,
], { stdio: 'ignore' });
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const deadline = Date.now() + 15000;
let socket;
let nextId = 0;
const pending = new Map();
const pass = (message) => console.log(`PASS: ${message}`);
const fail = (message) => { throw new Error(message); };

const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++nextId;
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
});
const evaluate = async (expression) => {
    const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) fail(result.exceptionDetails.text || 'browser evaluation failed');
    return result.result.value;
};
const waitForReadyState = async () => {
    for (let attempt = 0; attempt < 100; attempt += 1) {
        if (await evaluate('document.readyState') === 'complete') return;
        await delay(100);
    }
    fail('page did not reach the complete ready state');
};

try {
    const portFile = join(profile, 'DevToolsActivePort');
    while (!existsSync(portFile) && Date.now() < deadline) await delay(100);
    if (!existsSync(portFile)) fail('browser did not expose the DevTools endpoint');

    const [port] = readFileSync(portFile, 'utf8').split(/\r?\n/);
    let targets = [];
    while (!targets.length && Date.now() < deadline) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/list`);
            targets = (await response.json()).filter((target) => target.type === 'page');
        } catch { await delay(100); }
    }
    if (!targets.length) fail('browser page target was not created');

    socket = new WebSocket(targets[0].webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (!message.id || !pending.has(message.id)) return;
        const request = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) request.reject(new Error(message.error.message));
        else request.resolve(message.result);
    });

    await send('Page.enable');
    await send('Runtime.enable');
    await send('Network.enable');
    await send('Page.navigate', { url: origin });
    await waitForReadyState();

    const registration = await evaluate(`Promise.race([
        navigator.serviceWorker.ready.then((value) => ({
            scope: value.scope, scriptURL: value.active?.scriptURL, state: value.active?.state,
        })),
        new Promise((_, reject) => setTimeout(() => reject(new Error('service worker activation timed out')), 15000)),
    ])`);
    if (registration.scope !== `${origin}/` || registration.scriptURL !== `${origin}/sw.js` || registration.state !== 'activated') {
        fail(`unexpected service worker registration: ${JSON.stringify(registration)}`);
    }
    pass('service worker is activated with root scope');

    const workerIdentity = await evaluate(`navigator.serviceWorker.ready.then((readyRegistration) => new Promise((resolve, reject) => {
        const worker = navigator.serviceWorker.controller || readyRegistration.active;
        if (!worker) { reject(new Error('active worker is unavailable')); return; }
        const channel = new MessageChannel();
        const timer = setTimeout(() => reject(new Error('worker identity timed out')), 3000);
        channel.port1.onmessage = ({ data }) => { clearTimeout(timer); resolve(data); };
        worker.postMessage({ type: 'GET_VERSION' }, [channel.port2]);
    }))`);
    if (!workerIdentity?.version || workerIdentity.cache !== `carled-shell-${workerIdentity.version}`) {
        fail(`invalid service worker identity: ${JSON.stringify(workerIdentity)}`);
    }
    pass(`service worker reports coherent version ${workerIdentity.version}`);

    const cacheSnapshot = await evaluate(`(async () => Promise.all((await caches.keys()).map(async (name) => ({
        name, urls: (await (await caches.open(name)).keys()).map((request) => request.url),
    }))))()`);
    if (!cacheSnapshot.some(({ name }) => name === workerIdentity.cache)) {
        fail(`active worker cache ${workerIdentity.cache} is absent`);
    }
    if (cacheSnapshot.some(({ name }) => name.startsWith('carled-shell-') && name !== workerIdentity.cache)) {
        fail('a stale CARLED shell cache remains after worker activation');
    }
    pass('only the active build shell cache remains');
    const cachedUrls = cacheSnapshot.flatMap(({ urls }) => urls);
    if (!cachedUrls.includes(`${origin}/offline.html`)) fail('offline shell is absent from browser Cache Storage');
    pass('offline shell is present in browser Cache Storage');
    const privatePrefixes = ['/admin', '/user', '/accountant', '/api', '/device-token'];
    if (cachedUrls.some((url) => privatePrefixes.some((prefix) => new URL(url).pathname.startsWith(prefix)))) {
        fail('private application URL was found in browser Cache Storage');
    }
    pass('browser Cache Storage contains no private application routes');

    await send('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0 });
    await send('Page.navigate', { url: `${origin}/pwa-offline-acceptance` });
    await waitForReadyState();
    const offlineHeading = await evaluate("document.querySelector('#offline-title')?.textContent?.trim()");
    if (offlineHeading !== 'لا يوجد اتصال بالإنترنت') fail('offline navigation did not render the public offline shell');
    pass('offline navigation renders the public offline shell');

    const mutationResult = await evaluate(`fetch('/pwa-network-only-probe', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
    }).then(() => 'resolved').catch(() => 'rejected')`);
    if (mutationResult !== 'rejected') fail('offline mutation was unexpectedly resolved');
    pass('offline mutation remains network-only and is not queued');
    console.log(`PWA browser acceptance passed for ${origin}.`);
} catch (error) {
    console.error(`FAIL: ${error.message}`);
    process.exitCode = 1;
} finally {
    pending.clear();
    socket?.close();
    browser.kill();
    await Promise.race([
        new Promise((resolve) => browser.once('exit', resolve)),
        delay(2000),
    ]);
    try { rmSync(profile, { recursive: true, force: true }); } catch { /* OS cleanup can finish after process exit. */ }
}
