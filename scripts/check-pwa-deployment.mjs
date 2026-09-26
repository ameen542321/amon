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

const sameOriginModuleScripts = (html) => [...html.matchAll(/<script\b[^>]*>/gi)]
    .map(([tag]) => ({
        type: tag.match(/\btype=["']([^"']+)["']/i)?.[1],
        src: tag.match(/\bsrc=["']([^"']+)["']/i)?.[1],
    }))
    .filter(({ type, src }) => type === 'module' && src)
    .map(({ src }) => new URL(src, origin))
    .filter((url) => url.origin === origin);

try {
    const home = await fetchPath('/', ['text/html']);
    if (!home.body.includes('manifest.webmanifest')) fail('main page does not expose the web manifest');
    else pass('main page exposes the web manifest');
    const buildVersion = home.body.match(/<meta\s+name=["']pwa-build-version["']\s+content=["']([^"']+)["']/i)?.[1];
    if (!buildVersion) fail('main page does not expose the PWA build version');
    else pass(`main page exposes PWA build version ${buildVersion}`);

    const moduleScripts = sameOriginModuleScripts(home.body);
    if (!moduleScripts.length) {
        fail('main page does not expose a same-origin JavaScript module bundle');
    } else {
        pass(`main page exposes ${moduleScripts.length} same-origin JavaScript module bundle(s)`);
        const deployedModules = await Promise.all(moduleScripts.map((url) => fetchPath(url.href, ['javascript'])));
        const moduleSource = deployedModules.map(({ body }) => body).join('\n');
        if (!moduleSource.includes('serviceWorker') || !moduleSource.includes('/sw.js')) {
            fail('deployed JavaScript bundle does not contain the service-worker registration contract');
        } else {
            pass('deployed JavaScript bundle contains the service-worker registration contract');
        }
    }

    const manifestResult = await fetchPath('/manifest.webmanifest', ['application/manifest+json', 'application/json']);
    let manifest = null;
    try {
        manifest = JSON.parse(manifestResult.body);
    } catch {
        fail('manifest response is not valid JSON');
    }

    if (manifest) {
        if (manifest.id !== '/' || manifest.scope !== '/' || manifest.start_url !== '/') {
            fail('manifest id/scope/start_url must target the domain root');
        } else pass('manifest id, scope and start_url target the domain root');

        if (!manifest.name || !manifest.short_name) fail('manifest application names are missing');
        else pass('manifest exposes full and short application names');

        if (manifest.display !== 'standalone' || manifest.lang !== 'ar' || manifest.dir !== 'rtl') {
            fail('manifest must expose the standalone Arabic RTL contract');
        } else pass('manifest exposes the standalone Arabic RTL contract');

        const svgIcons = manifest.icons?.filter((icon) => icon.type === 'image/svg+xml') ?? [];
        if (svgIcons.length !== 1 || svgIcons[0].sizes !== 'any') {
            fail('manifest must expose exactly one scalable SVG icon');
        } else {
            pass('manifest exposes exactly one scalable SVG icon');
            await fetchPath(svgIcons[0].src, ['image/svg+xml']);
        }
    }

    const worker = await fetchPath(`/sw.js${buildVersion ? `?v=${encodeURIComponent(buildVersion)}` : ''}`, ['javascript', 'text/plain']);
    const workerCache = worker.response.headers.get('cache-control') ?? '';
    if (!/no-cache|no-store|max-age=0/i.test(workerCache)) fail(`sw.js Cache-Control is unsafe: ${workerCache || 'missing'}`);
    else pass(`sw.js Cache-Control prevents a stale worker: ${workerCache}`);
    if (!worker.body.includes("const CACHE_VERSION = 'carled-shell-")) fail('sw.js response is not the CARLED worker');
    else pass('sw.js response contains the CARLED worker contract');
    if (!worker.body.includes("event.data?.type === 'GET_VERSION'") || !worker.body.includes('event.ports[0]?.postMessage')) {
        fail('sw.js does not expose the runtime version probe');
    } else pass('sw.js exposes the runtime version probe');

    await fetchPath('/offline.html', ['text/html']);
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
