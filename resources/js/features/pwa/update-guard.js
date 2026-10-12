const baselines = new WeakMap();
const dirtyForms = new Set();
let revision = 0;
const snapshot = (form) =>
    JSON.stringify(
        [...new FormData(form)]
            .filter(([key]) => !['_token', '_idempotency_key'].includes(key))
            .map(([key, value]) => [
                key,
                typeof value === 'string'
                    ? value
                    : `${value.name}:${value.size}`,
            ]),
    );
const trackable = (form) =>
    form instanceof HTMLFormElement && form.method.toUpperCase() !== 'GET';
const initialize = () =>
    document.querySelectorAll('form').forEach((form) => {
        if (trackable(form)) baselines.set(form, snapshot(form));
    });
if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', initialize);
else initialize();

document.addEventListener('focusin', (event) => {
    const form = event.target.form;
    if (trackable(form) && !baselines.has(form))
        baselines.set(form, snapshot(form));
});
const changed = (event) => {
    const form = event.target.form;
    if (!trackable(form)) return;
    revision += 1;
    if (!baselines.has(form) || snapshot(form) !== baselines.get(form))
        dirtyForms.add(form);
    else dirtyForms.delete(form);
};
document.addEventListener('input', changed);
document.addEventListener('change', changed);
export const inputRevision = () => revision;
export const hasUnsavedForms = () =>
    [...dirtyForms].some(
        (form) =>
            form.isConnected &&
            snapshot(form) !== baselines.get(form) &&
            form.dataset.pwaDraftSafe !== 'true',
    );
window.addEventListener('carled:pwa-prepare-update', (event) => {
    if (hasUnsavedForms())
        event.detail.pending.push(
            Promise.reject(new Error('احفظ النموذج المفتوح قبل التحديث.')),
        );
});

window.addEventListener('carled:form-saved', (event) => {
    const form = event.detail.form;
    if (trackable(form)) {
        baselines.set(form, snapshot(form));
        dirtyForms.delete(form);
    }
});
