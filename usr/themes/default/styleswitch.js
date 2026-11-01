/* 风格切换：典藏（默认）/ 终端 / 代码（存于本机 localStorage） */
(function () {
  'use strict';
  var KEY = 'homer-theme';
  var THEMES = ['classic', 'terminal', 'code'];
  var LABELS = { classic: '典藏', terminal: '终端', code: '代码' };
  var FILES = { terminal: 'theme-terminal.css', code: 'theme-code.css' };

  function pref() {
    try {
      var v = localStorage.getItem(KEY);
      return THEMES.indexOf(v) >= 0 ? v : 'classic';
    } catch (e) { return 'classic'; }
  }

  /* 从 style.css 的链接推导主题目录基址（相对/绝对、子路径均适配） */
  function base() {
    var l = document.querySelector('link[href*="style.css"]');
    return l ? l.getAttribute('href').replace(/style\.css.*$/, '') : '';
  }

  function apply() {
    var t = pref();
    document.documentElement.setAttribute('data-style', t);
    var old = document.getElementById('theme-variant-css');
    if (old) old.parentNode.removeChild(old);
    if (FILES[t]) {
      var l = document.createElement('link');
      l.id = 'theme-variant-css';
      l.rel = 'stylesheet';
      l.href = base() + FILES[t];
      document.head.appendChild(l);
    }
    var a = document.getElementById('style-toggle');
    if (a) {
      a.textContent = '风格 · ' + LABELS[t];
      a.setAttribute('title', '点击切换：典藏 → 终端 → 代码');
    }
  }

  function cycle() {
    var t = pref();
    t = THEMES[(THEMES.indexOf(t) + 1) % THEMES.length];
    try { localStorage.setItem(KEY, t); } catch (e) {}
    apply();
  }

  apply();

  function inject() {
    var a = document.getElementById('style-toggle');
    if (!a) return;
    a.addEventListener('click', cycle);
    apply();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
