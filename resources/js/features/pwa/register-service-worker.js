const canRegister = 'serviceWorker' in navigator
    && (window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname));
const buildVersion = document.querySelector('meta[name="pwa-build-version"]')?.content ?? 'development';
const UPDATE_PREPARATION_TIMEOUT = 10000;
const VERSION_PROBE_TIMEOUT = 3000;

const panel = document.querySelector('[data-pwa-panel]');
const title = panel?.querySelector('[data-pwa-title]');
const message = panel?.querySelector('[data-pwa-message]');
const installButton = panel?.querySelector('[data-pwa-install]');
const updateButton = panel?.querySelector('[data-pwa-update]');
const retryButton = panel?.querySelector('[data-pwa-retry]');
const dismissButton = panel?.querySelector('[data-pwa-dismiss]');
let deferredInstallPrompt = null;
let activeRegistration = null;
let reloadingForUpdate = false;
let onlineTimer = null;

const withTimeout = (promise, milliseconds, label) => new Promise((resolve, reject) => {
    const timer = window.setTimeout(
        () => reject(new Error(`${label} timed out after ${milliseconds}ms`)),
        milliseconds,
    );

    Promise.resolve(promise).then(
        (value) => {
            window.clearTimeout(timer);
            resolve(value);
        },
        (error) => {
            window.clearTimeout(timer);
            reject(error);
        },
    );
});

const workerVersion = (worker) => {
    if (!worker) return Promise.resolve(null);

    return withTimeout(new Promise((resolve, reject) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = ({ data }) => resolve(data ?? null);
        channel.port1.onmessageerror = () => reject(new Error('invalid service-worker version response'));
        worker.postMessage({ type: 'GET_VERSION' }, [channel.port2]);
    }), VERSION_PROBE_TIMEOUT, 'service-worker version probe');
};

const announceStatus = (status, detail = {}) => window.dispatchEvent(new CustomEvent(
    'carled:pwa-status',
    { detail: { status, buildVersion, ...detail } },
));

const setHidden = (element, hidden) => element?.classList.toggle('hidden', hidden);
const showPanel = () => panel?.classList.remove('hidden');
const hidePanel = () => panel?.classList.add('hidden');

const showOffline = () => {
    if (!panel) return;
    clearTimeout(onlineTimer);
    title.textContent = 'الاتصال منقطع';
    message.textContent = 'لن تُرسل العمليات أثناء الانقطاع. أعد الاتصال ثم حاول مرة أخرى.';
    setHidden(installButton, true);
    setHidden(updateButton, true);
    setHidden(retryButton, false);
    setHidden(dismissButton, true);
    showPanel();
};

const showOnline = () => {
    if (!panel || panel.classList.contains('hidden') || deferredInstallPrompt || activeRegistration?.waiting) return;
    title.textContent = 'عاد الاتصال';
    message.textContent = 'يمكنك الآن إعادة فتح الصفحة ومتابعة العمل من الخادم.';
    setHidden(retryButton, true);
    showPanel();
    clearTimeout(onlineTimer);
    onlineTimer = window.setTimeout(hidePanel, 4000);
};

const showInstall = () => {
    if (!panel || sessionStorage.getItem('pwa-install-dismissed') === '1') return;
    title.textContent = 'ثبّت تطبيق CARLED';
    message.textContent = 'أضف التطبيق إلى شاشة الهاتف للوصول السريع؛ تبقى العمليات الحساسة بحاجة إلى الإنترنت.';
    setHidden(installButton, false);
    setHidden(updateButton, true);
    setHidden(retryButton, true);
    setHidden(dismissButton, false);
    showPanel();
};

const showUpdate = () => {
    if (!panel) return;
    title.textContent = 'تحديث جديد متاح';
    message.textContent = 'احفظ أي نموذج مفتوح، ثم حدّث التطبيق للحصول على النسخة الجديدة.';
    setHidden(installButton, true);
    setHidden(updateButton, false);
    setHidden(retryButton, true);
    setHidden(dismissButton, false);
    showPanel();
};

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    showInstall();
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    sessionStorage.removeItem('pwa-install-dismissed');
    hidePanel();
});

window.addEventListener('offline', showOffline);
window.addEventListener('online', showOnline);

installButton?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    await deferredInstallPrompt.prompt();
    const choice = await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    if (choice.outcome !== 'accepted') hidePanel();
});

updateButton?.addEventListener('click', async () => {
    updateButton.disabled = true;
    message.textContent = 'جارٍ حفظ المسودة قبل تطبيق التحديث…';
    const pending = [];
    window.dispatchEvent(new CustomEvent('carled:pwa-prepare-update', { detail: { pending } }));
    const results = await Promise.allSettled(pending.map((task) => withTimeout(
        task,
        UPDATE_PREPARATION_TIMEOUT,
        'PWA update preparation',
    )));
    const failures = results.filter(({ status }) => status === 'rejected');

    if (failures.length) {
        message.textContent = 'تعذر حفظ البيانات المحلية. لم يُطبّق التحديث؛ حاول مرة أخرى.';
        updateButton.disabled = false;
        announceStatus('update-blocked', { failures: failures.length });
        return;
    }

    const waitingWorker = activeRegistration?.waiting;
    if (!waitingWorker) {
        updateButton.disabled = false;
        announceStatus('update-worker-missing');
        return;
    }

    announceStatus('update-approved', { preparedTasks: results.length });
    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
});

retryButton?.addEventListener('click', () => window.location.reload());

dismissButton?.addEventListener('click', () => {
    if (deferredInstallPrompt) sessionStorage.setItem('pwa-install-dismissed', '1');
    hidePanel();
});

if (!navigator.onLine) showOffline();

if (canRegister) {
    window.addEventListener('load', async () => {
        try {
            activeRegistration = await navigator.serviceWorker.register(`/sw.js?v=${encodeURIComponent(buildVersion)}`, { scope: '/' });
            const deployedWorker = activeRegistration.active ?? activeRegistration.waiting;
            const deployedVersion = await workerVersion(deployedWorker).catch(() => null);
            announceStatus('registered', { workerVersion: deployedVersion?.version ?? null });
            if (deployedVersion?.version && deployedVersion.version !== buildVersion) {
                announceStatus('version-mismatch', { workerVersion: deployedVersion.version });
                await activeRegistration.update();
            }
            if (activeRegistration.waiting) showUpdate();

            activeRegistration.addEventListener('updatefound', () => {
                const worker = activeRegistration.installing;
                worker?.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) showUpdate();
                });
            });

            window.addEventListener('online', () => activeRegistration?.update());
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && navigator.onLine) activeRegistration?.update();
            });
        } catch (error) {
            announceStatus('registration-failed');
            console.warn(`تعذر تسجيل Service Worker للإصدار ${buildVersion}.`, error);
        }
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloadingForUpdate) return;
        reloadingForUpdate = true;
        window.location.reload();
    });
}
