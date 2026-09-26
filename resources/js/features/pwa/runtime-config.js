const booleanMeta = (name, fallback = false) => {
    const value = document.querySelector(`meta[name="${name}"]`)?.content;
    if (value === undefined) return fallback;
    return value === 'true';
};

export const pwaRuntimeConfig = Object.freeze({
    serviceWorkerEnabled: booleanMeta('pwa-service-worker-enabled'),
    updatesEnabled: booleanMeta('pwa-updates-enabled'),
    outboxEnabled: booleanMeta('pwa-outbox-enabled'),
});
