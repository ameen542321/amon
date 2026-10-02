// يمنع عجلة الماوس من تعديل أي حقل رقمي مركّز، بما في ذلك الحقول المضافة ديناميكيًا.
document.addEventListener('wheel', (event) => {
    const input = event.target.closest?.('input[type="number"]');
    if (!input || document.activeElement !== input || input.disabled || input.readOnly) return;

    event.preventDefault();
    input.blur();
}, { capture: true, passive: false });
