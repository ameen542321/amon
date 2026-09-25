const rawOrigin = process.argv[2];
const failures = [];
const passes = [];

const fail = (message) => failures.push(message);
const pass = (message) => passes.push(message);

if (!rawOrigin) {
    console.error('Usage: node scripts/check-pwa-deployment.mjs https://example.com');
    process.exit(2);
}

let origin;
try {
    const candidate = new URL(rawOrigin);
    const localHost = ['localhost', '127.0.0.1'].includes(candidate.hostname);
    if (candidate.protocol !== 'https:' && !localHost) throw new Error('public PWA checks require HTTPS');
    origin = candidate.origin;
} catch (error) {
    console.error(`Invalid deployment origin: ${error.message}`);
    process.exit(2);
}

const fetchPath = async (path, expectedContentTypes) => {
    const response = await fetch(new URL(path, origin), {
        redirect: 'follow',
        signal: AbortSignal.timeout(15000),
        headers: { Accept: '*/*' },
    });

    if (!response.ok) fail(`${path} returned HTTP ${response.status}`);
    else pass(`${path} returned HTTP ${response.status}`);

    if (new URL(response.url).origin !== origin) fail(`${path} redirected to another origin`);

    const contentType = response.headers.get('content-type') ?? '';
    if (!expectedContentTypes.some((type) => contentType.includes(type))) {
        fail(`${path} has unexpected Content-Type: ${contentType || 'missing'}`);
    } else pass(`${path} Content-Type is ${contentType}`);

    return { response, body: await response.text() };
};

try {
    const home = await fetchPath('/', ['text/html']);
    if (!home.body.includes('manifest.webmanifest')) fail('main page does not expose the web manifest');
    else pass('main page exposes the web manifest');

    const manifestResult = await fetchPath('/manifest.webmanifest', ['application/manifest+json', 'application/json']);
    try {
        const manifest = JSON.parse(manifestResult.body);
        if (manifest.scope !== '/' || manifest.start_url !== '/') fail('manifest scope/start_url must target the domain root');
        else pass('manifest scope and start_url target the domain root');
        if (!manifest.icons?.some((icon) => icon.type === 'image/svg+xml')) fail('manifest SVG icon is missing');
        else pass('manifest exposes the SVG icon');
    } catch {
        fail('manifest response is not valid JSON');
    }

    const worker = await fetchPath('/sw.js', ['javascript', 'text/plain']);
    const workerCache = worker.response.headers.get('cache-control') ?? '';
    if (!/no-cache|no-store|max-age=0/i.test(workerCache)) fail(`sw.js Cache-Control is unsafe: ${workerCache || 'missing'}`);
    else pass(`sw.js Cache-Control prevents a stale worker: ${workerCache}`);
    if (!worker.body.includes("const CACHE_VERSION = 'carled-shell-")) fail('sw.js response is not the CARLED worker');
    else pass('sw.js response contains the CARLED worker contract');

    await fetchPath('/offline.html', ['text/html']);
    await fetchPath('/icons/carled.svg', ['image/svg+xml']);
} catch (error) {
    fail(`HTTPS request failed: ${error.message}`);
}

for (const message of passes) console.log(`PASS: ${message}`);
for (const message of failures) console.error(`FAIL: ${message}`);

if (failures.length) {
    console.error(`PWA deployment check failed with ${failures.length} issue(s).`);
    process.exit(1);
}

console.log(`PWA deployment check passed for ${origin}.`);
