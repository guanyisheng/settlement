<?php
require_once __DIR__ . '/../../includes/brand.php';
?>
</div><!-- .content -->
</div><!-- .main -->
</div><!-- .layout -->
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
<p style="text-align:center;color:var(--text-muted);font-size:12px;padding:8px 0 20px">
    <?= e(brandName()) ?> · v<?= e(appVersion()) ?>
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
    document.querySelectorAll('.sidebar-nav a').forEach(function (a) {
        a.addEventListener('click', closeNav);
    });
})();
</script>
</body>
</html>
