import { onPageReady } from '../page-ready';
import { accountReady, canUseCurrentPage } from '../pwa/account-context';
import { workspaceStorage, readWorkspaceState, saveWorkspaceState } from './interface';

export const csvCell = value => {
    let text = String(value ?? '').replace(/\r?\n/g, ' ');
    if (/^[\s]*[=+\-@\t\r]/.test(text)) text = `'${text}`;
    return `"${text.replaceAll('"', '""')}"`;
};
export const compareCells = (a, b) => {
    const number = value => Number(String(value).replaceAll(',', '').trim());
    const x = number(a), y = number(b);
    return a.trim() && b.trim() && Number.isFinite(x) && Number.isFinite(y) ? x - y : a.localeCompare(b, 'ar', { numeric: true });
};
const button = (label, callback) => {
    const element = document.createElement('button'); element.type = 'button'; element.className = 'ui-btn ui-btn-secondary'; element.textContent = label; element.addEventListener('click', callback); return element;
};
const exportCsv = rows => {
    if (!canUseCurrentPage()) return;
    const file = new Blob(['\uFEFF', rows.map(row => row.map(csvCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(file); const link = document.createElement('a'); link.href = url; link.download = 'selected-records.csv'; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
};

onPageReady(async () => {
    await accountReady;
    const root = document.querySelector('[data-workspace-tools]');
    if (!root) return;
    const store = root.dataset.workspaceStore;
    document.querySelectorAll('[data-workspace-record-list]').forEach(list => {
        const records = [...list.querySelectorAll('[data-workspace-record]')];
        if (!records.length) return;
        const selected = new Set();
        const toolbar = document.createElement('div'); toolbar.className = 'ui-workspace-data-toolbar';
        const count = document.createElement('span'); count.setAttribute('aria-live', 'polite');
        const all = document.createElement('input'); all.type = 'checkbox'; all.setAttribute('aria-label', 'تحديد الطلبيات المعروضة');
        const label = document.createElement('label'); label.className = 'ui-cost-select'; label.append(all, ' تحديد الكل');
        const exportButton = button('تصدير المحدد CSV', () => exportCsv([['المرجع', 'الطلبية', 'التاريخ', 'المرحلة'], ...records.filter(record => selected.has(record)).map(record => [record.dataset.recordReference, record.dataset.recordTitle, record.dataset.recordDate, record.dataset.recordStatus])]));
        const update = () => { count.textContent = `${selected.size} محدد`; exportButton.disabled = !selected.size; all.checked = selected.size === records.length; all.indeterminate = selected.size > 0 && selected.size < records.length; };
        records.forEach(record => {
            const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.dataset.workspaceRecordSelect = ''; checkbox.setAttribute('aria-label', `تحديد ${record.dataset.recordReference}`);
            const choice = document.createElement('label'); choice.className = 'ui-cost-select ui-workspace-record-select'; choice.append(checkbox, ' تحديد الطلبية'); record.prepend(choice);
            checkbox.addEventListener('change', () => { if (checkbox.checked) selected.add(record); else selected.delete(record); record.classList.toggle('ui-workspace-selected', checkbox.checked); update(); });
        });
        all.addEventListener('change', () => { const checked = all.checked; records.forEach(record => { const checkbox = record.querySelector('[data-workspace-record-select]'); checkbox.checked = checked; checkbox.dispatchEvent(new Event('change')); }); });
        const sort = document.createElement('select'); sort.className = 'ui-input'; sort.setAttribute('aria-label', 'ترتيب الطلبيات المعروضة');
        [['', 'ترتيب السجلات المعروضة'], ['date-desc', 'الأحدث أولًا'], ['date-asc', 'الأقدم أولًا'], ['title-asc', 'الاسم']].forEach(([value, title]) => sort.add(new Option(title, value)));
        sort.addEventListener('change', () => {
            const [field, direction] = sort.value.split('-'); if (!field) return;
            records.sort((a, b) => (direction === 'desc' ? -1 : 1) * compareCells(a.dataset[field === 'date' ? 'recordDate' : 'recordTitle'], b.dataset[field === 'date' ? 'recordDate' : 'recordTitle'])).forEach(record => list.append(record));
        });
        toolbar.append(label, count, exportButton, sort); list.before(toolbar); update();
        window.addEventListener('carled:account-invalidated', () => { selected.clear(); exportButton.disabled = true; });
    });
    document.querySelectorAll('form[data-workspace-filters]').forEach((form, formIndex) => {
        if (form.method.toUpperCase() !== 'GET') return;
        form.classList.add('ui-workspace-filter-form');
        const allowed = new Set(form.dataset.workspaceFilters.split(','));
        const key = workspaceStorage(`filters:${location.pathname}:${formIndex}`, store);
        let saved = readWorkspaceState(key);
        if (!Array.isArray(saved)) saved = [];
        saved = saved.filter(item => typeof item?.name === 'string' && Array.isArray(item.values)).slice(0, 8);
        const toolbar = document.createElement('div'); toolbar.className = 'ui-workspace-data-toolbar';
        const name = document.createElement('input'); name.className = 'ui-input'; name.placeholder = 'اسم الفلتر'; name.maxLength = 40; name.setAttribute('aria-label', 'اسم الفلتر المحفوظ');
        const select = document.createElement('select'); select.className = 'ui-input'; select.setAttribute('aria-label', 'الفلاتر المحفوظة');
        const status = document.createElement('span'); status.className = 'ui-text-soft'; status.setAttribute('role', 'status');
        const refresh = () => {
            select.replaceChildren(new Option('اختر فلترًا محفوظًا', ''));
            saved.forEach((item, index) => select.add(new Option(item.name, String(index))));
        };
        const persist = () => { status.textContent = saveWorkspaceState(key, saved) ? 'تم حفظ الفلاتر لهذا الحساب والمتجر.' : 'تعذر الحفظ على الجهاز.'; refresh(); };
        toolbar.append(name, button('حفظ الفلتر', () => {
            if (!canUseCurrentPage()) return;
            const label = name.value.trim();
            if (!label) { status.textContent = 'اكتب اسمًا للفلتر.'; name.focus(); return; }
            const values = [...new FormData(form)].filter(([field, value]) => allowed.has(field) && typeof value === 'string').map(([field, value]) => [field, value.slice(0, 200)]);
            saved = [{ name: label, values }, ...saved.filter(item => item.name !== label)].slice(0, 8); persist(); name.value = '';
        }), select, button('تطبيق المحفوظ', () => {
            if (!canUseCurrentPage() || select.value === '') return;
            const item = saved[Number(select.value)]; if (!item) return;
            for (const field of allowed) {
                const controls = [...form.elements].filter(control => control.name === field);
                const values = item.values.filter(pair => Array.isArray(pair) && pair[0] === field).map(pair => String(pair[1]));
                controls.forEach(control => {
                    if (control.type === 'checkbox' || control.type === 'radio') control.checked = values.includes(control.value);
                    else control.value = values[0] ?? '';
                    control.dispatchEvent(new Event('input', { bubbles: true }));
                    control.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
            form.requestSubmit();
        }), button('حذف المحفوظ', () => { if (!canUseCurrentPage() || select.value === '') return; saved.splice(Number(select.value), 1); persist(); }), status);
        refresh(); form.append(toolbar);
    });

    document.querySelectorAll('table[data-workspace-table]').forEach((table, tableIndex) => {
        const heads = [...(table.tHead?.rows[0]?.cells ?? [])];
        const body = table.tBodies[0];
        if (!body || heads.length < 2 || table.tHead.rows.length !== 1) return;
        const rows = [...body.rows].filter(row => row.cells.length === heads.length && [...row.cells].every(cell => cell.colSpan === 1 && cell.rowSpan === 1));
        if (!rows.length) return;
        table.classList.add('ui-workspace-table');
        table.setAttribute('role', 'table'); body.setAttribute('role', 'rowgroup');
        rows.forEach(row => { row.setAttribute('role', 'row'); [...row.cells].forEach(cell => cell.setAttribute('role', 'cell')); });
        heads.forEach(head => head.setAttribute('role', 'columnheader'));
        rows.forEach(row => [...row.cells].forEach((cell, index) => { cell.dataset.columnLabel = heads[index].textContent.trim(); }));
        const toolbar = document.createElement('div'); toolbar.className = 'ui-workspace-data-toolbar';
        const note = document.createElement('span'); note.className = 'ui-text-muted'; note.textContent = 'الترتيب والتحديد للسجلات المعروضة فقط';
        const count = document.createElement('span'); count.className = 'ui-text-soft'; count.setAttribute('aria-live', 'polite');
        const selected = new Set();
        const update = () => { count.textContent = `${selected.size} محدد`; exportButton.disabled = selected.size === 0; all.checked = selected.size === rows.length; all.indeterminate = selected.size > 0 && selected.size < rows.length; };
        const all = document.createElement('input'); all.type = 'checkbox'; all.setAttribute('aria-label', 'تحديد كل السجلات المعروضة');
        const label = document.createElement('label'); label.className = 'ui-cost-select'; label.append(all, ' تحديد الكل');
        const exportButton = button('تصدير المحدد CSV', () => exportCsv([heads.map(head => head.dataset.columnTitle), ...rows.filter(row => selected.has(row)).map(row => [...row.cells].slice(1).map(cell => cell.textContent.trim()))]));
        toolbar.append(label, count, exportButton, note);
        const wrapper = table.closest('.ui-table-wrap');
        if (wrapper) wrapper.before(toolbar); else table.before(toolbar);
        const selectionHead = document.createElement('th'); selectionHead.textContent = 'تحديد'; table.tHead.rows[0].prepend(selectionHead);
        rows.forEach(row => {
            const cell = document.createElement('td'); cell.dataset.columnLabel = 'تحديد'; cell.className = 'ui-workspace-row-selection';
            const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.setAttribute('aria-label', `تحديد ${row.cells[0].textContent.trim()}`);
            checkbox.addEventListener('change', () => { if (checkbox.checked) selected.add(row); else selected.delete(row); row.classList.toggle('ui-workspace-selected', checkbox.checked); update(); });
            cell.append(checkbox); row.prepend(cell);
        });
        all.addEventListener('change', () => { const checked = all.checked; rows.forEach(row => { const checkbox = row.cells[0].querySelector('input'); checkbox.checked = checked; checkbox.dispatchEvent(new Event('change')); }); });
        // Non-data rows retain their place and span the newly added selection column.
        [...body.rows].filter(row => !rows.includes(row)).forEach(row => { if (row.cells.length === 1) row.cells[0].colSpan += 1; });
        if (table.tFoot) [...table.tFoot.rows].forEach(row => { const cell = document.createElement('td'); row.prepend(cell); });
        const columns = document.createElement('details'); const summary = document.createElement('summary'); summary.className = 'ui-btn ui-btn-secondary'; summary.textContent = 'الأعمدة'; columns.append(summary); toolbar.append(columns);
        const sortSelect = document.createElement('select'); sortSelect.className = 'ui-input'; sortSelect.setAttribute('aria-label', 'ترتيب السجلات المعروضة'); sortSelect.add(new Option('ترتيب حسب…', ''));
        toolbar.append(sortSelect);
        const key = workspaceStorage(`columns:${location.pathname}:${table.dataset.workspaceTable || tableIndex}`, store);
        const hidden = new Set(readWorkspaceState(key)?.filter?.(value => Number.isInteger(value) && value > 0 && value < heads.length) ?? []);
        heads.forEach((head, index) => {
            const title = head.textContent.trim(); head.dataset.columnTitle = title;
            sortSelect.add(new Option(title, String(index)));
            let ascending = true;
            const sorter = button(title, () => {
                if (!canUseCurrentPage()) return;
                heads.forEach(other => other.removeAttribute('aria-sort'));
                head.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
                rows.sort((a, b) => (ascending ? 1 : -1) * compareCells(a.cells[index + 1].textContent.trim(), b.cells[index + 1].textContent.trim())).forEach(row => body.append(row));
                ascending = !ascending;
            }); sorter.title = 'ترتيب السجلات المعروضة'; head.replaceChildren(sorter);
            if (index === 0) return; // Identifier stays visible.
            const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = !hidden.has(index);
            const applyColumn = () => {
                head.hidden = !checkbox.checked;
                rows.forEach(row => { row.cells[index + 1].hidden = !checkbox.checked; });
                if (table.tFoot) [...table.tFoot.rows].forEach(row => { if (row.cells.length === heads.length + 1) row.cells[index + 1].hidden = !checkbox.checked; });
            };
            const columnLabel = document.createElement('label'); columnLabel.className = 'ui-workspace-column-choice'; columnLabel.append(checkbox, title); columns.append(columnLabel);
            checkbox.addEventListener('change', () => { if (checkbox.checked) hidden.delete(index); else hidden.add(index); applyColumn(); saveWorkspaceState(key, [...hidden]); }); applyColumn();
        });
        sortSelect.addEventListener('change', () => { if (sortSelect.value !== '') heads[Number(sortSelect.value)].querySelector('button').click(); });
        update();
        window.addEventListener('carled:account-invalidated', () => { selected.clear(); exportButton.disabled = true; });
    });
});
