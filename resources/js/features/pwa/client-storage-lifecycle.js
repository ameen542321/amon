import { deleteDraftsForAccount } from './draft-store';

const submittedForms = new WeakSet();
const logoutPaths = new Set(['/logout', '/user/logout', '/accountant/logout']);

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || submittedForms.has(form)) return;

    const action = new URL(form.action, window.location.origin);
    const accountScope = document.querySelector('[data-client-account-scope]')?.dataset.clientAccountScope;
    if (action.origin !== window.location.origin || !logoutPaths.has(action.pathname) || !accountScope) return;

    event.preventDefault();
    submittedForms.add(form);

    try {
        await deleteDraftsForAccount(accountScope);
    } catch (error) {
        console.warn('تعذر تنظيف مسودات الحساب المحلية قبل تسجيل الخروج.', error);
    } finally {
        form.requestSubmit(event.submitter instanceof HTMLElement ? event.submitter : undefined);
    }
});
