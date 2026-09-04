/**
 * 可搜索下拉：input 过滤 + select 选择
 */
function initSearchSelect(inputId, selectId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    if (!input || !select) return;

    const allOptions = Array.from(select.options).map(opt => ({
        value: opt.value,
        text: opt.textContent,
        price: opt.dataset.price || '',
        element: opt,
    }));

    function renderFilter(keyword) {
        const kw = keyword.trim().toLowerCase();
        let firstVisible = null;

        allOptions.forEach(item => {
            if (item.value === '') {
                item.element.hidden = false;
                item.element.disabled = false;
                return;
            }
            const match = !kw || item.text.toLowerCase().includes(kw);
            item.element.hidden = !match;
            item.element.disabled = !match;
            if (match && !firstVisible && item.value !== '') {
                firstVisible = item;
            }
        });

        if (kw && firstVisible && select.value === '') {
            select.value = firstVisible.value;
            select.dispatchEvent(new Event('change'));
        }
    }

    input.addEventListener('input', () => renderFilter(input.value));
    select.addEventListener('change', () => {
        const selected = allOptions.find(o => o.value === select.value);
        if (selected && selected.value !== '') {
            input.value = selected.text;
        }
    });
}
