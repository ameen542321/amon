import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { performance } from 'node:perf_hooks';

const rawOrigin = process.argv[2];
const outputPath = resolve(process.argv[3] ?? 'storage/app/pwa-acceptance/performance.json');
const requestedSamples = Number.parseInt(process.env.PWA_PERFORMANCE_SAMPLES ?? '12', 10);
const sampleCount = Number.isFinite(requestedSamples) ? Math.min(50, Math.max(5, requestedSamples)) : 12;
if (!rawOrigin) {
    console.error('Usage: node scripts/measure-pwa-staging-performance.mjs https://staging.example.com [performance.json]');
    process.exit(2);
}
const candidate = new URL(rawOrigin);
if (candidate.protocol !== 'https:' && !['localhost', '127.0.0.1'].includes(candidate.hostname)) {
    console.error('PWA performance measurement requires HTTPS except on localhost.');
    process.exit(2);
}
const origin = candidate.origin;

const timedFetch = async (path, options = {}) => {
    const { captureText = false, headers = {}, ...fetchOptions } = options;
    const started = performance.now();
    try {
        const response = await fetch(new URL(path, origin), {
            redirect: 'follow',
            cache: 'no-store',
            signal: AbortSignal.timeout(15000),
            headers: { Accept: '*/*', ...headers },
            ...fetchOptions,
        });
        const body = await response.arrayBuffer();
        return {
            ok: response.ok && new URL(response.url).origin === origin,
            status: response.status,
            duration_ms: Number((performance.now() - started).toFixed(2)),
            bytes: body.byteLength,
            content_type: response.headers.get('content-type'),
            final_url: response.url,
            ...(captureText ? { text: Buffer.from(body).toString('utf8') } : {}),
        };
    } catch (error) {
        return {
            ok: false,
            status: 0,
            duration_ms: Number((performance.now() - started).toFixed(2)),
            bytes: 0,
            error: error.message,
        };
    }
};
const percentile = (values, fraction) => {
    if (!values.length) return null;
    const sorted = [...values].sort((left, right) => left - right);
    return sorted[Math.min(sorted.length - 1, Math.ceil(sorted.length * fraction) - 1)];
};
const summarize = (samples) => {
    // Latency percentiles describe successful responses; failures remain visible through error_rate.
    const durations = samples.filter(({ ok }) => ok).map(({ duration_ms }) => duration_ms);
    const failures = samples.filter(({ ok }) => !ok);
    return {
        samples: samples.length,
        successful: samples.length - failures.length,
        failures: failures.length,
        error_rate: Number((failures.length / samples.length).toFixed(4)),
        p50_ms: percentile(durations, 0.50),
        p95_ms: percentile(durations, 0.95),
        p99_ms: percentile(durations, 0.99),
        min_ms: durations.length ? Math.min(...durations) : null,
        max_ms: durations.length ? Math.max(...durations) : null,
    };
};

const navigationSamples = [];
for (let index = 0; index < sampleCount; index += 1) {
    navigationSamples.push(await timedFetch('/'));
}
const home = await timedFetch('/', { captureText: true });
const homeText = home.ok ? home.text : '';
const assetUrls = [...homeText.matchAll(/<(?:script|link)\b[^>]*(?:src|href)=["']([^"']+)["'][^>]*>/gi)]
    .map(([, value]) => new URL(value, origin))
    .filter((url) => url.origin === origin && /\.(?:js|css)(?:\?|$)/i.test(url.href));
const uniqueAssets = [...new Map(assetUrls.map((url) => [url.href, url])).values()];
const assets = [];
for (const url of uniqueAssets) {
    const result = await timedFetch(url.href);
    assets.push({ url: url.href, ...result });
}
const pwaAssets = {};
for (const path of ['/manifest.webmanifest', '/sw.js', '/offline.html', '/carled.svg']) {
    pwaAssets[path] = await timedFetch(path);
}

const report = {
    schema_version: 1,
    origin,
    measured_at: new Date().toISOString(),
    methodology: {
        navigation_path: '/',
        sequential_samples: sampleCount,
        timeout_ms: 15000,
        cache_mode: 'no-store',
        note: 'This is a staging baseline, not an approved production SLO.',
    },
    navigation: summarize(navigationSamples),
    navigation_samples: navigationSamples,
    assets: {
        count: assets.length,
        total_bytes: assets.reduce((sum, item) => sum + item.bytes, 0),
        javascript_bytes: assets.filter(({ url }) => /\.js(?:\?|$)/i.test(url)).reduce((sum, item) => sum + item.bytes, 0),
        css_bytes: assets.filter(({ url }) => /\.css(?:\?|$)/i.test(url)).reduce((sum, item) => sum + item.bytes, 0),
        files: assets,
    },
    pwa_assets: pwaAssets,
};
mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify({
    origin,
    navigation: report.navigation,
    asset_count: report.assets.count,
    total_asset_bytes: report.assets.total_bytes,
    report: outputPath,
}, null, 2));
const publicAssetsHealthy = Object.values(pwaAssets).every(({ ok }) => ok);
process.exit(report.navigation.failures === 0 && publicAssetsHealthy && assets.every(({ ok }) => ok) ? 0 : 1);
