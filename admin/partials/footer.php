<?php
require_once __DIR__ . '/../../includes/brand.php';
?>
</div><!-- .content -->
</div><!-- .main -->
</div><!-- .layout -->
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
<div class="update-banner" id="assetUpdateBanner" hidden>
    <span data-banner-text>新版本已准备好，点击刷新以加载最新样式与脚本</span>
    <button type="button" data-banner-refresh>立即刷新</button>
</div>
<p style="text-align:center;color:var(--text-muted);font-size:12px;padding:8px 0 20px">
    <?= e(brandName()) ?> · v<?= e(appVersion()) ?> · <?= e(assetBuildId()) ?>
</p>
<script>
(function () {
    var layout = document.querySelector('.layout');
    var toggle = document.getElementById('mobileNavToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    function closeNav() {
        layout && layout.classList.remove('nav-open');
        if (backdrop) backdrop.hidden = true;
    }
    function openNav() {
        layout && layout.classList.add('nav-open');
        if (backdrop) backdrop.hidden = false;
    }
    toggle && toggle.addEventListener('click', function () {
        if (layout && layout.classList.contains('nav-open')) closeNav();
        else openNav();
    });
    backdrop && backdrop.addEventListener('click', closeNav);

    // 委托：AJAX 换页后侧栏 HTML 会替换，仍能点折叠 / 关菜单
    var sideNav = document.querySelector('.sidebar-nav');
    if (sideNav) {
        sideNav.addEventListener('click', function (e) {
            if (e.target.closest('a')) closeNav();
            var btn = e.target.closest('.nav-group-toggle');
            if (!btn || !sideNav.contains(btn)) return;
            var group = btn.closest('.nav-group');
            if (!group) return;
            var open = group.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            try {
                var key = group.getAttribute('data-nav-group');
                if (key) localStorage.setItem('admin-nav-' + key, open ? '1' : '0');
            } catch (err) {}
        });
        sideNav.querySelectorAll('.nav-group').forEach(function (group) {
            if (group.classList.contains('open')) return;
            try {
                var key = group.getAttribute('data-nav-group');
                if (key && localStorage.getItem('admin-nav-' + key) === '1') {
                    group.classList.add('open');
                    var btn = group.querySelector('.nav-group-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'true');
                }
            } catch (err) {}
        });
    }
})();
</script>
<script src="<?= e(adminAssetUrl('/admin/assets/js/img-preview.js')) ?>"></script>
<script src="<?= e(adminAssetUrl('/admin/assets/js/ajax-nav.js')) ?>"></script>
<script src="<?= e(adminAssetUrl('/admin/assets/js/asset-refresh.js')) ?>"></script>
</body>
</html>
