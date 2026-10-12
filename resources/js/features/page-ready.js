export const onPageReady = (callback) => {
    if (document.readyState === 'loading')
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    else callback();
};
