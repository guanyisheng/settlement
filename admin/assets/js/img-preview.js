/**
 * 点击缩略图全屏预览（管理端）
 */
(function () {
    function ensureLightbox() {
        var box = document.getElementById('imgLightbox');
        if (box) return box;
        box = document.createElement('div');
        box.id = 'imgLightbox';
        box.className = 'img-lightbox';
        box.hidden = true;
        box.innerHTML = '<button type="button" class="img-lightbox-close" aria-label="关闭">×</button><img alt="预览">';
        document.body.appendChild(box);
        box.addEventListener('click', function (e) {
            if (e.target === box || e.target.classList.contains('img-lightbox-close')) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeLightbox();
        });
        return box;
    }

    function openLightbox(src) {
        if (!src) return;
        var box = ensureLightbox();
        var img = box.querySelector('img');
        img.src = src;
        box.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        var box = document.getElementById('imgLightbox');
        if (!box) return;
        box.hidden = true;
        var img = box.querySelector('img');
        if (img) img.removeAttribute('src');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-img-preview');
        if (!btn) return;
        e.preventDefault();
        var src = btn.getAttribute('data-src')
            || (btn.tagName === 'A' ? btn.getAttribute('href') : '')
            || (btn.tagName === 'IMG' ? btn.getAttribute('src') : '')
            || (btn.querySelector && btn.querySelector('img') ? btn.querySelector('img').getAttribute('src') : '');
        openLightbox(src);
    });
})();
