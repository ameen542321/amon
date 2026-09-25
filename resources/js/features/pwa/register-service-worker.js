const canRegister = 'serviceWorker' in navigator
    && (window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname));
const buildVersion = document.querySelector('meta[name="pwa-build-version"]')?.content ?? 'development';

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
    await Promise.allSettled(pending);
    activeRegistration?.waiting?.postMessage({ type: 'SKIP_WAITING' });
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
            if (activeRegistration.waiting) showUpdate();

            activeRegistration.addEventListener('updatefound', () => {
                const worker = activeRegistration.installing;
                worker?.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) showUpdate();
                });
            });
        } catch (error) {
            console.warn(`تعذر تسجيل Service Worker للإصدار ${buildVersion}.`, error);
        }
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloadingForUpdate) return;
        reloadingForUpdate = true;
        window.location.reload();
    });
}
