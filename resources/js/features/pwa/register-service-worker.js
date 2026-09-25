const canRegister = 'serviceWorker' in navigator
    && (window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname));

if (canRegister) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('تعذر تسجيل Service Worker.', error);
        });
    });
}
