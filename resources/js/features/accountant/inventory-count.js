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
    document.querySelectorAll('[data-inventory-count-item]').forEach((item) => {
        const update = () => updateKitBreakdown(item);
        item.querySelector('[data-inventory-count-quantity]')?.addEventListener('input', update);
        item.querySelector('[data-inventory-count-unit]')?.addEventListener('change', update);
        update();
    });
});
