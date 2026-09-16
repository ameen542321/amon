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

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-inventory-count-form]');
    if (!form) return;

    const storageKey = form.dataset.inventoryCountStorageKey;
    const serverVersion = form.dataset.inventoryCountVersion;
    const draftStatus = document.querySelector('[data-inventory-count-draft-status]');
    const draftFields = [...form.querySelectorAll('[name^="items["]')];
    const maxDraftAge = 30 * 24 * 60 * 60 * 1000;

    // localStorage يبقي العد غير المثبت متاحًا بعد تحديث الصفحة أو إغلاق المتصفح والعودة خلال ثلاثين يومًا.
    const restoreDraft = () => {
        if (!storageKey) return;

        try {
            const draft = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
            const isCurrent = draft
                && draft.serverVersion === serverVersion
                && draft.values
                && typeof draft.values === 'object'
                && Date.now() - draft.savedAt <= maxDraftAge;

            if (!isCurrent) {
                window.localStorage.removeItem(storageKey);
                return;
            }

            draftFields.forEach((field) => {
                if (Object.hasOwn(draft.values, field.name)) field.value = draft.values[field.name];
            });
            if (draftStatus) draftStatus.textContent = 'تمت استعادة مدخلاتك المحفوظة في هذا المتصفح';
        } catch {
            if (draftStatus) draftStatus.textContent = 'تعذر استعادة الحفظ المؤقت من هذا المتصفح';
        }
    };

    const saveDraft = () => {
        if (!storageKey) return;

        const values = Object.fromEntries(draftFields.map((field) => [field.name, field.value]));
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({ serverVersion, savedAt: Date.now(), values }));
            if (draftStatus) draftStatus.textContent = 'حُفظت مدخلاتك مؤقتًا في هذا المتصفح';
        } catch {
            if (draftStatus) draftStatus.textContent = 'تعذر الحفظ المؤقت؛ اضغط حفظ جميع الكميات قبل المغادرة';
        }
    };

    restoreDraft();

    form.querySelectorAll('[data-inventory-count-item]').forEach((item) => {
        const update = () => updateKitBreakdown(item);
        item.querySelector('[data-inventory-count-quantity]')?.addEventListener('input', () => {
            update();
            saveDraft();
        });
        item.querySelector('[data-inventory-count-unit]')?.addEventListener('change', () => {
            update();
            saveDraft();
        });
        item.querySelector('[name$="[accountant_note]"]')?.addEventListener('input', saveDraft);
        update();
    });
});
