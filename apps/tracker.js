/* 荷马史诗 · 阅读进度追踪（纯前端，localStorage） */
(function () {
  var KEY = 'homer-read-v1';
  var path = location.pathname;
  var isPost = /\/index\.php\/archives\/\d+(\/|\/index\.html)?$/.test(path) ||
               /\/index\.php\/archives\/\d+\.html$/.test(path);
  var store = [];
  try { store = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { store = []; }
  if (!Array.isArray(store)) store = [];

  function norm(href) {
    var m = String(href || '').match(/archives\/(\d+)/);
    return m ? '/index.php/archives/' + m[1] + '/' : null;
  }
  var thisPost = norm(path);
  if (thisPost && store.indexOf(thisPost) < 0) {
    store.push(thisPost);
    try { localStorage.setItem(KEY, JSON.stringify(store.slice(-500))); } catch (e) {}
  }

  function decorate() {
    var visited = {};
    store.forEach(function (p) {
      var n = norm(p);
      if (n) visited[n] = 1;
    });
    var marks = document.querySelectorAll('a[href*="archives/"]');
    var total = 0, done = 0;
    marks.forEach(function (a) {
      var n = norm(a.getAttribute('href'));
      if (!n) return;
      total++;
      if (visited[n]) {
        done++;
        if (a.getAttribute('data-tracked') !== '1') {
          a.setAttribute('data-tracked', '1');
          a.style.color = '#9a9a9a';
          a.insertAdjacentHTML('afterbegin', '✓ ');
        }
      }
    });
    var title = document.title || '';
    if (title.indexOf('全站目录') >= 0) {
      var info = document.createElement('p');
      info.style.cssText = 'color:#999;font-size:13px;margin:6px 0 18px;';
      info.textContent = '你的阅读进度：已读 ' + done + ' 篇 / 共 ' + total +
        ' 篇（带 ✓ 的灰色条目为已读，本机记录，不上传）';
      var h1 = document.querySelector('h1');
      if (h1 && h1.parentNode) h1.parentNode.insertBefore(info, h1.nextSibling);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', decorate);
  } else {
    decorate();
  }
})();
