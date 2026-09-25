import { deleteDraft, getDraft, pruneDrafts, putDraft } from '../pwa/draft-store';

const formatCount = (value, singular, dual, plural) => {
    if (value === 1) return singular;
    if (value === 2) return dual;
    return `${value} ${plural}`;
};

const updateKitBreakdown = (item) => {
    const quantityInput = item.querySelector('[data-inventory-count-quantity]');
    const unitInput = item.querySelector('[data-inventory-count-unit]');
    const output = item.querySelector('[data-inventory-count-breakdown]');
    const itemsPerUnit = Number.parseInt(item.dataset.itemsPerUnit || '0', 10);

    if (!quantityInput || !unitInput || !output || itemsPerUnit < 1) return;

    const quantity = Number.parseFloat(quantityInput.value);
    if (!Number.isFinite(quantity) || quantity < 0 || unitInput.value !== 'piece' || !Number.isInteger(quantity)) {
        output.textContent = '';
        return;
    }

    const kits = Math.floor(quantity / itemsPerUnit);
    const pieces = quantity % itemsPerUnit;
    const parts = [];

    if (kits > 0) parts.push(formatCount(kits, 'طقم واحد', 'طقمين', 'أطقم'));
    if (pieces > 0) parts.push(formatCount(pieces, 'حبة واحدة', 'حبتان', 'حبات'));

    output.textContent = parts.length > 0 ? `تعادل: ${parts.join(' و')}` : '';
};

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.querySelector('[data-inventory-count-form]');
    if (!form) return;

    const storageKey = form.dataset.inventoryCountStorageKey;
    const serverVersion = form.dataset.inventoryCountVersion;
    const draftStatus = document.querySelector('[data-inventory-count-draft-status]');
    const draftFields = [...form.querySelectorAll('[name^="items["]')];
    const maxDraftAge = 30 * 24 * 60 * 60 * 1000;
    let saveTimer = null;
    let writeQueue = Promise.resolve();

    const setStatus = (text) => {
        if (draftStatus) draftStatus.textContent = text;
    };

    const valuesSnapshot = () => Object.fromEntries(draftFields.map((field) => [field.name, field.value]));

    const migrateLegacyDraft = async () => {
        try {
            const legacyValue = window.localStorage.getItem(storageKey);
            if (!legacyValue) return null;

            const legacyDraft = JSON.parse(legacyValue);
            await putDraft(storageKey, legacyDraft);
            window.localStorage.removeItem(storageKey);
            return { key: storageKey, ...legacyDraft };
        } catch {
            try {
                window.localStorage.removeItem(storageKey);
            } catch {
                // قد يمنع المتصفح التخزين القديم؛ تبقى IndexedDB هي المسار الأساسي.
            }
            return null;
        }
    };

    const restoreDraft = async () => {
        if (!storageKey) return;

        try {
            await pruneDrafts(Date.now() - maxDraftAge);
            const draft = await getDraft(storageKey) ?? await migrateLegacyDraft();
            const isCurrent = draft
                && draft.serverVersion === serverVersion
                && draft.values
                && typeof draft.values === 'object'
                && Date.now() - draft.savedAt <= maxDraftAge;

            if (!isCurrent) {
                if (draft) await deleteDraft(storageKey);
                return;
            }

            draftFields.forEach((field) => {
                if (Object.hasOwn(draft.values, field.name)) field.value = draft.values[field.name];
            });
            setStatus('تمت استعادة مسودة الجرد من قاعدة هذا المتصفح');
        } catch {
            setStatus('تعذر فتح التخزين المحلي؛ احفظ الكميات في الخادم قبل المغادرة');
        }
    };

    const persistDraft = () => {
        if (!storageKey) return Promise.resolve();

        const draft = {
            serverVersion,
            savedAt: Date.now(),
            values: valuesSnapshot(),
        };

        writeQueue = writeQueue
            .catch(() => undefined)
            .then(() => putDraft(storageKey, draft))
            .then(() => setStatus(navigator.onLine
                ? 'حُفظت المسودة محليًا في هذا الجهاز'
                : 'حُفظت المسودة محليًا — يلزم الإنترنت لتثبيتها في الخادم'))
            .catch(() => setStatus('تعذر الحفظ المحلي؛ اضغط حفظ جميع الكميات قبل المغادرة'));

        return writeQueue;
    };

    const scheduleDraftSave = () => {
        clearTimeout(saveTimer);
        setStatus('جارٍ حفظ المسودة محليًا…');
        saveTimer = window.setTimeout(persistDraft, 300);
    };

    const flushDraft = () => {
        if (!saveTimer) return;
        clearTimeout(saveTimer);
        saveTimer = null;
        persistDraft();
    };

    await restoreDraft();

    form.querySelectorAll('[data-inventory-count-item]').forEach((item) => {
        const update = () => updateKitBreakdown(item);
        item.querySelector('[data-inventory-count-quantity]')?.addEventListener('input', () => {
            update();
            scheduleDraftSave();
        });
        item.querySelector('[data-inventory-count-unit]')?.addEventListener('change', () => {
            update();
            scheduleDraftSave();
        });
        item.querySelector('[name$="[accountant_note]"]')?.addEventListener('input', scheduleDraftSave);
        update();
    });

    window.addEventListener('pagehide', flushDraft);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flushDraft();
    });
    window.addEventListener('offline', () => setStatus('أنت دون اتصال — المسودة محلية ولن تُرسل تلقائيًا'));
});
