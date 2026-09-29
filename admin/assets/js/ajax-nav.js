/**
 * 管理端同站 AJAX 换页 + 内容区淡入淡出
 */
(function () {
  var FADE_MS = 180;
  var content = document.querySelector('.content');
  if (!content) return;

  var navigating = false;
  var abortCtrl = null;

  if (!history.state || !history.state.ajaxNav) {
    history.replaceState({ ajaxNav: 1 }, '', location.href);
  }

  function sameOriginAdmin(url) {
    try {
      var u = new URL(url, location.origin);
      if (u.origin !== location.origin) return null;
      if (!u.pathname.startsWith('/admin/')) return null;
      if (/\/(switch_dash|logout|asset_build)\.php$/i.test(u.pathname)) return null;
      return u;
    } catch (e) {
      return null;
    }
  }

  function shouldInterceptLink(a, e) {
    if (!a || e.defaultPrevented) return false;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download') || a.dataset.noAjax === '1') return false;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return false;
    var u = sameOriginAdmin(a.href);
    if (!u) return false;
    // 仅 hash 变化：交给浏览器
    if (
      u.pathname === location.pathname &&
      u.search === location.search &&
      u.hash &&
      u.hash !== location.hash
    ) {
      return false;
    }
    return true;
  }

  function closeMobileNav() {
    var layout = document.querySelector('.layout');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (layout) layout.classList.remove('nav-open');
    if (backdrop) backdrop.hidden = true;
  }

  function wait(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  function runScripts(root) {
    var list = root.querySelectorAll('script');
    list.forEach(function (old) {
      var s = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (attr) {
        s.setAttribute(attr.name, attr.value);
      });
      if (!old.src) s.textContent = old.textContent;
      old.parentNode.replaceChild(s, old);
    });
  }

  function syncChrome(doc) {
    document.title = doc.title || document.title;

    var newTitle = doc.querySelector('.topbar-title');
    var curTitle = document.querySelector('.topbar-title');
    if (newTitle && curTitle) curTitle.textContent = newTitle.textContent;

    var newNav = doc.querySelector('.sidebar-nav');
    var curNav = document.querySelector('.sidebar-nav');
    if (newNav && curNav) curNav.innerHTML = newNav.innerHTML;

    var newSwitch = doc.querySelector('.topbar-switch');
    var curSwitch = document.querySelector('.topbar-switch');
    if (newSwitch && curSwitch) {
      curSwitch.href = newSwitch.getAttribute('href') || curSwitch.href;
      curSwitch.innerHTML = newSwitch.innerHTML;
    }

    var build = doc.documentElement.getAttribute('data-asset-build');
    if (build) document.documentElement.setAttribute('data-asset-build', build);
  }

  function scrollToHash(hash) {
    if (!hash || hash === '#') return;
    var id = decodeURIComponent(hash.replace(/^#/, ''));
    var el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function navigate(url, push) {
    var target = sameOriginAdmin(url);
    if (!target) {
      location.href = url;
      return;
    }

    if (navigating && abortCtrl) {
      try {
        abortCtrl.abort();
      } catch (e) {}
    }
    navigating = true;
    abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;

    content.classList.add('ajax-nav-busy');
    content.classList.add('ajax-nav-out');

    try {
      var fetchPromise = fetch(target.href, {
        credentials: 'same-origin',
        headers: {
          Accept: 'text/html',
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: abortCtrl ? abortCtrl.signal : undefined,
      });

      var res = (await Promise.all([fetchPromise, wait(FADE_MS)]))[0];

      // 登录失效等：整页跟过去
      if (res.redirected && !sameOriginAdmin(res.url)) {
        location.href = res.url;
        return;
      }
      if (!res.ok) {
        location.href = target.href;
        return;
      }

      var html = await res.text();
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var next = doc.querySelector('.content');
      if (!next || !doc.querySelector('.layout')) {
        location.href = target.href;
        return;
      }

      content.innerHTML = next.innerHTML;
      syncChrome(doc);
      runScripts(content);
      closeMobileNav();

      if (push) history.pushState({ ajaxNav: 1 }, '', target.href);

      content.classList.remove('ajax-nav-out');
      content.classList.add('ajax-nav-in');
      // 强制回流再淡入
      void content.offsetWidth;
      content.classList.remove('ajax-nav-in');

      if (target.hash) {
        requestAnimationFrame(function () {
          scrollToHash(target.hash);
        });
      } else {
        window.scrollTo(0, 0);
      }

      await wait(FADE_MS);
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      location.href = target.href;
    } finally {
      navigating = false;
      content.classList.remove('ajax-nav-busy', 'ajax-nav-out', 'ajax-nav-in');
    }
  }

  document.addEventListener(
    'click',
    function (e) {
      var a = e.target.closest && e.target.closest('a');
      if (!shouldInterceptLink(a, e)) return;
      e.preventDefault();
      navigate(a.href, true);
    },
    true
  );

  // 筛选等 GET 表单也走淡入淡出
  document.addEventListener(
    'submit',
    function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM') return;
      if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
      if (form.dataset.noAjax === '1') return;
      var action = form.getAttribute('action') || location.href;
      var u = sameOriginAdmin(new URL(action, location.href).href);
      if (!u) return;
      e.preventDefault();
      var qs = new URLSearchParams(new FormData(form)).toString();
      u.search = qs ? '?' + qs : '';
      navigate(u.href, true);
    },
    true
  );

  window.addEventListener('popstate', function () {
    navigate(location.href, false);
  });
})();
