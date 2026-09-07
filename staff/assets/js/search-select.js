/**
 * 可搜索下拉：搜索框过滤 + 原生 select 点选
 * 提交值走隐藏域，避免 mobile 上 option[disabled]/hidden] 导致选中项不入库
 */
function initSearchSelect(inputId, selectId, hiddenId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    const hidden = hiddenId ? document.getElementById(hiddenId) : null;
    if (!input || !select) return;

    const allOptions = Array.from(select.options).map(opt => ({
        value: opt.value,
        text: (opt.textContent || '').trim(),
        price: opt.dataset.price || '',
        element: opt,
    }));

    function syncHidden() {
        if (hidden) {
            hidden.value = select.value || '';
        }
    }

    function syncInputFromSelect() {
        const selected = allOptions.find(o => o.value === select.value);
        if (selected && selected.value !== '') {
            input.value = selected.text;
        }
    }

    function renderFilter(keyword) {
        const kw = keyword.trim().toLowerCase();

        allOptions.forEach(item => {
            if (item.value === '') {
                item.element.hidden = false;
                item.element.disabled = false;
                return;
            }
            const match = !kw || item.text.toLowerCase().includes(kw);
            // 只隐藏，绝不 disabled——disabled 的 option 提交时会被浏览器丢掉
            item.element.hidden = !match;
            item.element.disabled = false;
        });

        // 当前选中项若被过滤隐藏，仍保持选中，并强制显示
        if (select.value) {
            const current = allOptions.find(o => o.value === select.value);
            if (current) {
                current.element.hidden = false;
                current.element.disabled = false;
            }
        }
    }

    input.addEventListener('input', () => renderFilter(input.value));
    input.addEventListener('focus', () => {
        // 聚焦搜索时清空关键字，展示完整列表，避免沿用已选文案造成“双显”
        if (select.value) {
            input.value = '';
            renderFilter('');
        }
    });
    input.addEventListener('blur', () => {
        setTimeout(syncInputFromSelect, 150);
    });

    select.addEventListener('change', () => {
        syncHidden();
        syncInputFromSelect();
        renderFilter('');
        select.dispatchEvent(new Event('searchselect:change'));
    });

    const form = select.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            allOptions.forEach(item => {
                item.element.hidden = false;
                item.element.disabled = false;
            });
            syncHidden();
            if (hidden && !hidden.value) {
                // 兜底：若 select 有值但 hidden 空
                hidden.value = select.value || '';
            }
        });
    }

    // 初始同步（含报错回填）
    syncHidden();
    syncInputFromSelect();
    renderFilter('');
}
