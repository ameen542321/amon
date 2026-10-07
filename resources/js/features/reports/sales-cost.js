const root = document.querySelector('[data-sales-cost-report]');

if (root) {
    const checkboxes = [...root.querySelectorAll('[data-cost-select]')];
    const selectAll = root.querySelector('[data-cost-select-all]');
    const count = root.querySelector('[data-cost-selection-count]');
    const bulkForm = root.querySelector('[data-cost-bulk-form]');
    const bulkButton = root.querySelector('[data-cost-bulk-save]');
    const values = root.querySelector('[data-cost-bulk-values]');
    const pdfForm = root.querySelector('[data-cost-pdf-form]');
    const pdfButton = root.querySelector('[data-cost-pdf-save]');
    const editableSelected = () => selected().filter(box => root.querySelector(`[data-cost-input="${box.dataset.costSelect}"]`));
    const feedback = root.querySelector('[data-cost-feedback]');
    const selected = () => checkboxes.filter(input => input.checked);
    const showFeedback = message => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.toggle('hidden', !message);
    };
    const update = () => {
        const selection = selected();
        if (count) count.textContent = selection.length ? `${selection.length} عمليات محددة` : 'لم تُحدد عمليات';
        if (selectAll) {
            selectAll.checked = selection.length > 0 && selection.length === checkboxes.length;
            selectAll.indeterminate = selection.length > 0 && selection.length < checkboxes.length;
            selectAll.disabled = !navigator.onLine;
        }
        if (bulkButton) bulkButton.disabled = !navigator.onLine || !editableSelected().length;
        if (pdfButton) pdfButton.disabled = !navigator.onLine || !selection.length;
        root.querySelectorAll('[data-cost-online]').forEach(button => { button.disabled = !navigator.onLine; });
        checkboxes.forEach(input => {
            input.closest('[data-cost-row]')?.setAttribute('data-selected', String(input.checked));
        });
        if (!navigator.onLine) showFeedback('الحفظ متاح عند الاتصال بالإنترنت.');
    };
    checkboxes.forEach(input => input.addEventListener('change', () => {
        showFeedback('');
        if (selected().length > 100) {
            input.checked = false;
            showFeedback('يمكن حفظ 100 عملية كحد أقصى في المرة الواحدة.');
        }
        update();
    }));
    selectAll?.addEventListener('change', () => {
        showFeedback('');
        checkboxes.forEach((input, index) => { input.checked = selectAll.checked && index < 100; });
        if (selectAll.checked && checkboxes.length > 100) showFeedback('تم تحديد أول 100 عملية.');
        update();
    });
    bulkForm?.addEventListener('submit', event => {
        const selection = editableSelected();
        if (!navigator.onLine || !selection.length) {
            event.preventDefault();
            showFeedback(navigator.onLine ? 'حدد عملية واحدة على الأقل.' : 'الحفظ متاح عند الاتصال بالإنترنت.');
            return;
        }
        // تبقى القيم في الصفحة؛ لا تخزين محلي ولا إعادة إرسال Offline.
        values.replaceChildren();
        let lastId;
        for (const checkbox of selection) {
            const id = checkbox.dataset.costSelect;
            const input = root.querySelector(`[data-cost-input="${id}"]`);
            if (!input || !input.reportValidity()) {
                event.preventDefault();
                input?.focus();
                return;
            }
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `costs[${id}]`;
            hidden.value = input.value;
            values.append(hidden);
            lastId = id;
        }
        const anchor = document.createElement('input');
        anchor.type = 'hidden';
        anchor.name = 'return_sale';
        anchor.value = lastId;
        values.append(anchor);
    });
    pdfForm?.addEventListener('submit', event => {
        const selection = selected();
        if (!navigator.onLine || !selection.length) {
            event.preventDefault();
            showFeedback(navigator.onLine ? 'حدد عملية واحدة على الأقل.' : 'التصدير متاح عند الاتصال بالإنترنت.');
            return;
        }
        const fields = pdfForm.querySelector('[data-cost-pdf-values]');
        fields.replaceChildren();
        selection.forEach(box => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'sale_ids[]'; input.value = box.dataset.costSelect;
            fields.append(input);
        });
    });
    root.querySelectorAll('[data-cost-single-form]').forEach(form => form.addEventListener('submit', event => {
        if (!navigator.onLine) {
            event.preventDefault();
            showFeedback('الحفظ متاح عند الاتصال بالإنترنت.');
        }
    }));
    window.addEventListener('online', () => { showFeedback(''); update(); });
    window.addEventListener('offline', update);
    update();

    const focusSavedRow = () => {
        if (!/^#sale-\d+$/.test(window.location.hash)) return;
        const row = document.getElementById(window.location.hash.slice(1));
        if (!row || !root.contains(row)) return;
        row.focus({ preventScroll: true });
        row.scrollIntoView({ block: 'center' });
    };
    (document.fonts?.ready ?? Promise.resolve()).then(focusSavedRow);
}
