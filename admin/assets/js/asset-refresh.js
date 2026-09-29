(function () {
  var BUILD = document.documentElement.getAttribute('data-asset-build') || '';
  var KEY = 'admin-asset-build';
  if (!BUILD) return;

  function hardReload() {
    try {
      localStorage.setItem(KEY, BUILD);
    } catch (e) {}
    var finish = function () {
      var u = new URL(window.location.href);
      u.searchParams.set('_r', String(Date.now()));
      window.location.replace(u.toString());
    };
    if (window.caches && caches.keys) {
      caches.keys()
        .then(function (keys) {
          return Promise.all(keys.map(function (k) { return caches.delete(k); }));
        })
        .catch(function () {})
        .then(finish);
      return;
    }
    finish();
  }

  function showBanner(msg) {
    var el = document.getElementById('assetUpdateBanner');
    if (!el) return;
    var text = el.querySelector('[data-banner-text]');
    if (text && msg) text.textContent = msg;
    el.hidden = false;
    var btn = el.querySelector('[data-banner-refresh]');
    if (btn && !btn.dataset.bound) {
      btn.dataset.bound = '1';
      btn.addEventListener('click', hardReload);
    }
  }

  var prev = null;
  try {
    prev = localStorage.getItem(KEY);
  } catch (e) {}

  // 已记录过旧构建号，且与当前不一致 → 提示刷新拉新 CSS
  if (prev && prev !== BUILD) {
    showBanner('新版本已准备好，点击刷新以加载最新样式与脚本');
  } else if (!prev) {
    try {
      localStorage.setItem(KEY, BUILD);
    } catch (e) {}
  }

  // 财务页：样式未生效时强制提示（线上旧缓存/未部署 CSS）
  function checkFinanceCss() {
    var hero = document.querySelector('.finance-dash .finance-hero');
    if (!hero) return;
    var display = window.getComputedStyle(hero).display;
    if (display !== 'grid') {
      showBanner('检测到样式未更新，点击刷新重新获取全部资源');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkFinanceCss);
  } else {
    checkFinanceCss();
  }

  // 定期探测构建号（部署后无需手动猜）
  setInterval(function () {
    fetch('/admin/asset_build.php?_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.build && data.build !== BUILD) {
          showBanner('新版本已准备好，点击刷新以加载最新样式与脚本');
        }
      })
      .catch(function () {});
  }, 60000);
})();
