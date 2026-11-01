/* 荷马史诗 · 站点增强：预取 / 键盘翻页 / 返回顶部 / 阅读进度 / 图片灯箱 */
(function () {
  'use strict';

  /* ---- 悬停预取：鼠标停 60ms 即预载目标页，翻章零等待 ---- */
  var prefetched = {};
  document.addEventListener('mouseover', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || prefetched[href]) return;
    if (a.origin && a.origin !== location.origin) return;
    prefetched[href] = 1;
    setTimeout(function () {
      if (a.isConnected) {
        var l = document.createElement('link');
        l.rel = 'prefetch';
        l.href = href;
        document.head.appendChild(l);
      }
    }, 60);
  }, { passive: true });

  /* ---- 键盘翻页：文章页 ← → 切换上下篇 ---- */
  var pagerLinks = {};
  document.querySelectorAll('.page-navigator li, .post ul li').forEach(function (li) {
    var a = li.querySelector('a');
    if (!a) return;
    var t = li.textContent;
    if (t.indexOf('上一篇') >= 0) pagerLinks.prev = a.getAttribute('href');
    if (t.indexOf('下一篇') >= 0) pagerLinks.next = a.getAttribute('href');
  });
  if (pagerLinks.prev || pagerLinks.next) {
    document.addEventListener('keydown', function (e) {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      if (e.key === 'ArrowLeft' && pagerLinks.prev) location.href = pagerLinks.prev;
      if (e.key === 'ArrowRight' && pagerLinks.next) location.href = pagerLinks.next;
    });
  }

  /* ---- 阅读进度条 + 返回顶部 ---- */
  var bar = document.createElement('div');
  bar.id = 'read-progress';
  bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0;background:#c9a24b;z-index:99;transition:width .15s;';
  document.body.appendChild(bar);
  var toTop = document.createElement('button');
  toTop.id = 'to-top';
  toTop.textContent = '↑';
  toTop.title = '返回顶部';
  toTop.style.cssText = 'position:fixed;right:18px;bottom:22px;width:40px;height:40px;border-radius:50%;' +
    'border:1px solid #d8cdb4;background:#f7f4ec;color:#7a6c4f;font-size:18px;cursor:pointer;z-index:98;' +
    'opacity:0;pointer-events:none;transition:opacity .25s;';
  document.body.appendChild(toTop);
  var art = document.querySelector('.post-content') || document.body;
  function onScroll() {
    var total = art.scrollHeight - innerHeight;
    var p = total > 0 ? Math.min(1, Math.max(0, scrollY / total)) : 0;
    bar.style.width = (p * 100).toFixed(1) + '%';
    var show = scrollY > 400;
    toTop.style.opacity = show ? '.92' : '0';
    toTop.style.pointerEvents = show ? 'auto' : 'none';
  }
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  toTop.addEventListener('click', function () { scrollTo({ top: 0, behavior: 'smooth' }); });

  /* ---- 图片灯箱：点正文插图看原图，Esc / 点击遮罩关闭 ---- */
  /* ---- giscus 主题跟随站点明暗与风格 ---- */
  function giscusTheme() {
    var st = document.documentElement.getAttribute('data-style');
    var dark = document.documentElement.getAttribute('data-appearance') === 'dark' ||
               st === 'terminal' || st === 'code';
    var theme = dark ? (st === 'classic' ? 'dark' : 'noborder_dark') : 'noborder_light';
    var frame = document.querySelector('iframe.giscus-frame');
    if (frame) frame.contentWindow.postMessage({
      giscus: { setConfig: { theme: theme } }
    }, 'https://giscus.app');
  }
  new MutationObserver(giscusTheme).observe(document.documentElement, {
    attributes: true, attributeFilter: ['data-appearance', 'data-style']
  });
  setTimeout(giscusTheme, 1500);
  setTimeout(giscusTheme, 3500);

  var box = null;
  document.querySelectorAll('.post-content img, .post img').forEach(function (img) {
    img.style.cursor = 'zoom-in';
    img.addEventListener('click', function (e) {
      e.preventDefault();
      if (!box) {
        box = document.createElement('div');
        box.style.cssText = 'position:fixed;inset:0;background:rgba(12,12,14,.88);display:flex;' +
          'align-items:center;justify-content:center;z-index:100;cursor:zoom-out;';
        var bImg = document.createElement('img');
        bImg.style.cssText = 'max-width:94vw;max-height:92vh;border:2px solid #fff2;';
        box.appendChild(bImg);
        box.addEventListener('click', close);
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
        function close() { box.style.display = 'none'; }
        box._close = close;
        document.body.appendChild(box);
      }
      var b = box.querySelector('img');
      b.src = img.currentSrc || img.src;
      b.alt = img.alt || '';
      box.style.display = 'flex';
    });
  });
})();
