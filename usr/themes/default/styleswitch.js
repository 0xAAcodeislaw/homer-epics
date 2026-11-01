/* 风格切换（下拉菜单）：典藏 / 终端 / 代码 / 黑客帝国 / 霓虹（存于本机 localStorage） */
(function () {
  'use strict';
  var KEY = 'homer-theme';
  var THEMES = ['classic', 'terminal', 'code', 'matrix', 'cyber'];
  var FILES = {
    terminal: 'theme-terminal.css',
    code: 'theme-code.css',
    matrix: 'theme-matrix.css',
    cyber: 'theme-cyber.css'
  };
  var RAIN_GLYPHS = 'ハミソナカラヘクモサスンデキリマオエ0123456789ΗΟΜΗΡΟΣ';

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

  /* ---- 黑客帝国：字母雨画布（仅 matrix 风格运行） ---- */
  var rain = null;
  function startRain() {
    if (rain || !window.requestAnimationFrame) return;
    var cv = document.createElement('canvas');
    cv.id = 'matrix-rain';
    cv.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;z-index:-1;opacity:.4;pointer-events:none;';
    document.body.insertBefore(cv, document.body.firstChild);
    var ctx = cv.getContext('2d');
    var fs = 15, cols, drops;
    function size() {
      cv.width = innerWidth;
      cv.height = innerHeight;
      cols = Math.ceil(cv.width / fs);
      drops = [];
      for (var i = 0; i < cols; i++) drops[i] = Math.floor(Math.random() * -60);
    }
    size();
    rain = {
      cv: cv,
      timer: setInterval(function () {
        if (document.hidden) return;
        ctx.fillStyle = 'rgba(0, 5, 1, .12)';
        ctx.fillRect(0, 0, cv.width, cv.height);
        ctx.font = fs + 'px monospace';
        for (var i = 0; i < cols; i++) {
          var ch = RAIN_GLYPHS.charAt(Math.floor(Math.random() * RAIN_GLYPHS.length));
          var y = drops[i] * fs;
          ctx.fillStyle = Math.random() < .04 ? '#ccffcc' : '#00ff41';
          ctx.fillText(ch, i * fs, y);
          if (y > cv.height && Math.random() > .975) drops[i] = Math.floor(Math.random() * -30);
          drops[i]++;
        }
      }, 66),
      onResize: size
    };
    addEventListener('resize', rain.onResize);
  }
  function stopRain() {
    if (!rain) return;
    clearInterval(rain.timer);
    removeEventListener('resize', rain.onResize);
    var cv = document.getElementById('matrix-rain');
    if (cv) cv.parentNode.removeChild(cv);
    rain = null;
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
    if (t === 'matrix') startRain(); else stopRain();
    var sel = document.getElementById('style-toggle');
    if (sel) sel.value = t;
  }

  apply();

  function inject() {
    var sel = document.getElementById('style-toggle');
    if (!sel) return;
    sel.addEventListener('change', function () {
      try { localStorage.setItem(KEY, sel.value); } catch (e) {}
      apply();
    });
    apply();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
