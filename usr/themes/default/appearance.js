/* 外观模式：浅色 / 深色 / 跟随系统（存于本机 localStorage） */
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
    var link = document.getElementById('appearance-toggle');
    if (link) {
      link.textContent = '外观 · ' + LABELS[pref()];
      link.setAttribute('title', '点击切换：跟随系统 → 浅色 → 深色');
    }
  }

  function cycle() {
    var p = pref();
    p = MODES[(MODES.indexOf(p) + 1) % MODES.length];
    try { localStorage.setItem(KEY, p); } catch (e) {}
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

  function inject() {
    var a = document.getElementById('appearance-toggle');
    if (!a) {
      var menu = document.getElementById('nav-menu');
      if (!menu) return;
      a = document.createElement('a');
      a.id = 'appearance-toggle';
      a.href = 'javascript:void(0)';
      a.setAttribute('role', 'button');
      menu.appendChild(a);
    }
    a.addEventListener('click', cycle);
    apply();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
