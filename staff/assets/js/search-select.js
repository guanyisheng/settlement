/**
 * 可搜索下拉：搜索框过滤 + 原生 select 点选
 * 提交值走隐藏域，避免 mobile 上 option[disabled] 导致选中项不入库
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
                item.element.style.display = '';
                return;
            }
            const match = !kw || item.text.toLowerCase().includes(kw);
            item.element.hidden = !match;
            item.element.disabled = false;
            // 部分手机 WebView 不认 option[hidden]，用 display 双保险
            item.element.style.display = match ? '' : 'none';
        });
        if (select.value) {
            const current = allOptions.find(o => o.value === select.value);
            if (current) {
                current.element.hidden = false;
                current.element.disabled = false;
                current.element.style.display = '';
            }
        }
    }

    input.disabled = false;
    input.readOnly = false;
    input.removeAttribute('disabled');
    input.removeAttribute('readonly');
    input.style.pointerEvents = 'auto';
    input.tabIndex = 0;

    input.addEventListener('input', () => renderFilter(input.value));
    input.addEventListener('focus', () => {
        if (select.value) {
            input.value = '';
            renderFilter('');
        }
    });
    input.addEventListener('click', function (e) {
        e.stopPropagation();
        try { input.focus(); } catch (err) {}
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

    // 部分浏览器 size>1 时点 option 不触发 change，补一次 click
    select.addEventListener('click', () => {
        syncHidden();
        syncInputFromSelect();
    });

    const form = select.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            allOptions.forEach(item => {
                item.element.hidden = false;
                item.element.disabled = false;
                item.element.style.display = '';
            });
            syncHidden();
        });
    }

    syncHidden();
    syncInputFromSelect();
    renderFilter('');
}
