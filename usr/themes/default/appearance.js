/* 外观模式（下拉菜单）：跟随系统 / 浅色 / 深色（存于本机 localStorage） */
(function () {
  var KEY = 'homer-appearance';
  var MODES = ['auto', 'light', 'dark'];
  var LABELS = { auto: '跟随系统', light: '浅色', dark: '深色' };

  function pref() {
    try {
      var v = localStorage.getItem(KEY);
      return MODES.indexOf(v) >= 0 ? v : 'auto';
    } catch (e) { return 'auto'; }
  }

  function resolve(p) {
    if (p !== 'auto') return p;
    try {
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark' : 'light';
    } catch (e) { return 'light'; }
  }

  function apply() {
    var mode = resolve(pref());
    document.documentElement.setAttribute('data-appearance', mode);
    var sel = document.getElementById('appearance-toggle');
    if (sel) sel.value = pref();
  }

  function inject() {
    var sel = document.getElementById('appearance-toggle');
    if (!sel) return;
    sel.addEventListener('change', function () {
      try { localStorage.setItem(KEY, sel.value); } catch (e) {}
      apply();
    });
    apply();
  }

  /* 尽早应用，避免深色用户看到白屏闪烁 */
  apply();

  try {
    if (window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      var handler = function () { if (pref() === 'auto') apply(); };
      if (mq.addEventListener) mq.addEventListener('change', handler);
      else if (mq.addListener) mq.addListener(handler);
    }
  } catch (e) {}

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
