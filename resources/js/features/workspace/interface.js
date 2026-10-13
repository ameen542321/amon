import { onPageReady } from '../page-ready';
import { accountReady, currentAccountScope, canUseCurrentPage } from '../pwa/account-context';

export const normalizeSearch = value => String(value).normalize('NFKC').toLocaleLowerCase('ar').replace(/[\u064B-\u065F\u0640]/g, '').trim();
const defaults = { font: 'normal', density: 'comfortable', view: 'auto', collapsed: false };

export const workspaceStorage = (purpose, store = 'all') => {
    const scope = currentAccountScope();
    if (!scope) return null;
    return `carled:${scope}:store:${store}:workspace:${purpose}`;
};
export const readWorkspaceState = key => {
    try { return key ? JSON.parse(localStorage.getItem(key)) : null; } catch { return null; }
};
export const saveWorkspaceState = (key, value) => {
    try {
        if (!key || !canUseCurrentPage()) return false;
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch { return false; }
};

onPageReady(async () => {
    const root = document.querySelector('[data-workspace-tools]');
    if (!root) return;
    await accountReady;
    const key = workspaceStorage('display');
    let preferences = { ...defaults, theme: document.documentElement.classList.contains('light') ? 'light' : 'dark', ...readWorkspaceState(key) };
    const apply = () => {
        const html = document.documentElement;
        const theme = preferences.theme === 'light' ? 'light' : 'dark';
        html.classList.toggle('light', theme === 'light');
        html.classList.toggle('dark', theme === 'dark');
        html.dataset.workspaceFont = ['large', 'larger'].includes(preferences.font) ? preferences.font : 'normal';
        html.dataset.workspaceDensity = preferences.density === 'compact' ? 'compact' : 'comfortable';
        html.dataset.workspaceView = ['cards', 'table'].includes(preferences.view) ? preferences.view : 'auto';
        document.body.classList.toggle('ui-workspace-collapsed', preferences.collapsed === true);
        document.querySelector('[data-workspace-collapse]')?.setAttribute('aria-expanded', String(!preferences.collapsed));
        document.querySelectorAll('[data-theme-toggle]').forEach(button => button.setAttribute('aria-pressed', String(theme === 'dark')));
        root.querySelectorAll('[data-workspace-preference]').forEach(select => { select.value = preferences[select.dataset.workspacePreference]; });
    };
    const persist = () => {
        root.querySelector('[data-workspace-storage-status]').textContent = saveWorkspaceState(key, preferences) ? 'تم حفظ إعدادات العرض.' : 'التخزين غير متاح؛ يطبق العرض على هذه الصفحة فقط.';
    };
    apply();
    root.querySelectorAll('[data-workspace-preference]').forEach(select => select.addEventListener('change', () => {
        preferences[select.dataset.workspacePreference] = select.value;
        apply(); persist();
        if (select.dataset.workspacePreference === 'theme') {
            try { localStorage.setItem('theme', preferences.theme); } catch { /* Page-only theme remains available. */ }
        }
    }));
    document.querySelector('[data-workspace-collapse]')?.addEventListener('click', () => { preferences.collapsed = !preferences.collapsed; apply(); persist(); });
    document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
        preferences.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        apply(); persist();
    }));
    root.querySelector('[data-workspace-reset]').addEventListener('click', () => { preferences = { ...defaults, theme: 'dark' }; apply(); persist(); });

    const query = root.querySelector('[data-workspace-query]');
    const results = root.querySelector('[data-workspace-results]');
    const searchStatus = root.querySelector('[data-workspace-search-status]');
    const taskStatus = root.querySelector('[data-workspace-tasks-status]');
    let searchRequest, taskRequest, debounce;
    const render = (container, items) => {
        container.replaceChildren();
        for (const item of items) {
            const url = new URL(item.url, location.origin);
            if (url.origin !== location.origin) continue;
            const link = document.createElement('a'); link.className = 'ui-workspace-result'; link.href = url.href;
            const title = document.createElement('strong'); title.textContent = item.title;
            const detail = document.createElement('span'); detail.className = 'ui-text-soft'; detail.textContent = [item.detail, item.date].filter(Boolean).join(' · ');
            link.append(title, detail); container.append(link);
        }
    };
    const fetchPrivate = async (url, controller) => {
        if (!navigator.onLine || !canUseCurrentPage()) throw new Error('offline');
        const expectedScope = currentAccountScope() ?? document.body.dataset.clientAccountScope;
        const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error('response');
        const payload = await response.json();
        if (!canUseCurrentPage() || payload.meta?.account_scope !== expectedScope) throw new Error('session');
        return payload.data;
    };
    const search = async () => {
        searchRequest?.abort(); results.replaceChildren();
        const term = query.value.trim(); const normalized = normalizeSearch(term);
        root.querySelectorAll('[data-workspace-command-links] a').forEach(link => { link.hidden = Boolean(normalized) && !normalizeSearch(link.textContent).includes(normalized); });
        if (!root.dataset.workspaceSearchUrl || term.length < 2) { searchStatus.textContent = term ? 'اكتب حرفين على الأقل للبحث في البيانات.' : 'اختر صفحة أو ابحث في بياناتك.'; return; }
        if (!navigator.onLine) { searchStatus.textContent = 'البحث في البيانات يحتاج اتصالًا بالإنترنت.'; return; }
        const controller = searchRequest = new AbortController();
        const url = new URL(root.dataset.workspaceSearchUrl, location.origin); url.searchParams.set('q', term);
        const store = root.querySelector('[data-workspace-search-store]')?.value ?? root.dataset.workspaceStore;
        if (store && store !== 'all') url.searchParams.set('store_id', store);
        searchStatus.textContent = 'جارٍ البحث…';
        try {
            const data = await fetchPrivate(url, controller);
            if (controller !== searchRequest || controller.signal.aborted) return;
            render(results, data.items ?? []); searchStatus.textContent = data.items?.length ? 'النتائج المطابقة' : 'لا توجد نتائج مطابقة.';
        } catch (error) {
            if (!controller.signal.aborted) searchStatus.textContent = navigator.onLine ? 'تعذر البحث. حاول مجددًا.' : 'البحث يحتاج اتصالًا بالإنترنت.';
        }
    };
    const loadTasks = async () => {
        taskRequest?.abort();
        if (!root.dataset.workspaceTasksUrl) { taskStatus.textContent = 'راجع تذاكر الدعم من القائمة أدناه.'; return; }
        const controller = taskRequest = new AbortController();
        taskStatus.textContent = 'جارٍ تحميل المهام…';
        try {
            const url = new URL(root.dataset.workspaceTasksUrl, location.origin);
            const store = root.dataset.workspaceTasksStore ?? root.dataset.workspaceStore;
            if (store && store !== 'all') url.searchParams.set('store_id', store);
            const data = await fetchPrivate(url, controller);
            if (controller !== taskRequest || controller.signal.aborted) return;
            render(root.querySelector('[data-workspace-tasks]'), data.items ?? []);
            root.querySelector('[data-workspace-task-count]').textContent = String(data.total ?? 0);
            taskStatus.textContent = data.total ? `${data.total} مهمة تحتاج مراجعتك${data.limited ? '؛ تظهر أقدم المهام، وبقية السجلات في صفحاتها.' : '.'}` : 'لا توجد مهام معلقة ضمن المتاجر المتاحة.';
        } catch {
            if (!controller.signal.aborted) taskStatus.textContent = navigator.onLine ? 'تعذر تحميل المهام. اضغط تحديث.' : 'عرض المهام يحتاج اتصالًا بالإنترنت.';
        }
    };
    const open = name => {
        if (!canUseCurrentPage()) return;
        const dialog = root.querySelector(`[data-workspace-dialog="${name}"]`);
        if (!dialog || dialog.open) return;
        root.querySelectorAll('dialog[open]').forEach(other => other.close());
        dialog.showModal();
        if (name === 'search') { query.focus(); search(); }
        if (name === 'tasks') loadTasks();
    };
    const desktopSearch = root.querySelector('[data-workspace-desktop-search]');
    desktopSearch?.addEventListener('click', () => open('search'));
    desktopSearch?.addEventListener('input', () => { query.value = desktopSearch.value; desktopSearch.value = ''; open('search'); });
    desktopSearch?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); open('search'); } });
    root.querySelectorAll('[data-workspace-open]').forEach(button => button.addEventListener('click', () => open(button.dataset.workspaceOpen)));
    root.querySelectorAll('[data-workspace-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    root.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', event => { if (event.target === dialog) { const box = dialog.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close(); } });
        dialog.addEventListener('close', () => {
            if (dialog.open) return;
            if (dialog.dataset.workspaceDialog === 'search') { clearTimeout(debounce); searchRequest?.abort(); results.replaceChildren(); }
            if (dialog.dataset.workspaceDialog === 'tasks') taskRequest?.abort();
        });
    });
    query.addEventListener('input', () => { searchRequest?.abort(); clearTimeout(debounce); debounce = setTimeout(search, 250); });
    root.querySelector('[data-workspace-search-store]')?.addEventListener('change', search);
    root.querySelector('[data-workspace-refresh]').addEventListener('click', loadTasks);
    document.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); open('search'); }
    });
    const clearPrivate = () => {
        searchRequest?.abort(); taskRequest?.abort(); clearTimeout(debounce);
        results.replaceChildren(); root.querySelector('[data-workspace-tasks]').replaceChildren();
        root.querySelector('[data-workspace-task-count]').textContent = '';
        searchStatus.textContent = 'البحث في البيانات يحتاج اتصالًا وجلسة نشطة.';
        taskStatus.textContent = 'عرض المهام يحتاج اتصالًا وجلسة نشطة.';
    };
    window.addEventListener('offline', clearPrivate);
    window.addEventListener('carled:account-invalidated', () => { clearPrivate(); root.querySelectorAll('dialog[open]').forEach(dialog => dialog.close()); });
});
