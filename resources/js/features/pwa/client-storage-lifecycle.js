import { clearAccountData, currentAccountScope, revokeAccount } from './account-context';

const submittedForms = new WeakSet();
const logoutPaths = new Set(['/logout', '/user/logout', '/accountant/logout']);

document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || submittedForms.has(form)) return;
    const action = new URL(form.action, window.location.origin);
    const accountScope = currentAccountScope();
    if (action.origin !== window.location.origin || !logoutPaths.has(action.pathname) || !accountScope) return;
    event.preventDefault();
    submittedForms.add(form);
    revokeAccount(accountScope);
    try {
        const cleanup = () => clearAccountData(accountScope);
        if (navigator.locks) await navigator.locks.request(`carled-client:${accountScope}`, cleanup);
        else await cleanup();
    } catch (error) {
        console.warn('تعذر تنظيف بيانات الجهاز. لن تستخدمها الجلسة المنتهية.', error);
    } finally {
        form.requestSubmit(event.submitter instanceof HTMLElement ? event.submitter : undefined);
    }
});
