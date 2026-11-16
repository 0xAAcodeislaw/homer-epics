#!/usr/bin/env bash
# 镜像后处理：两个部署脚本（CF Pages / GH Pages）与 CI 共用的适配层
# 用法（环境变量）:
#   MIRROR        镜像目录（必填）
#   BASE          子路径前缀，如 /homer-epics（CF 传空串）
#   CANONICAL     规范化地址（GH 镜像传 CF 主站；空则不注入）
#   GISCUS_REPO   giscus 仓库 node id（空则跳过评论注入）
#   GISCUS_CAT_ID giscus 讨论分类 node id
set -e
MIRROR="${MIRROR:?MIRROR 未设置}"
BASE="${BASE:-}"
CANONICAL="${CANONICAL:-}"
GISCUS_REPO="${GISCUS_REPO:-}"
GISCUS_CAT_ID="${GISCUS_CAT_ID:-}"
SELF_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$MIRROR"

# BSD 与 GNU 的 sed -i 语义不同：数组方式传参，双平台通吃
if sed --version >/dev/null 2>&1; then SI=(-i); else SI=(-i ''); fi

echo "    [fix] 清理无用派生页（feed 派生 / action / author / 评论翻页）"
# wget 会顺着每页的 feed 链接爬出 348 个静态垃圾页（每篇文章的 rss/atom
# 评论 feed、分类 feed、作者页、xmlrpc/rsd 等）。静态站无动态 feed，
# 这些页面无人访问却占 72% 文件数，还可能被搜索引擎当重复内容。
# 保留主 feed（index.php/feed/index.html，侧栏「文章 RSS」在用）。
BEFORE=$(find . -type f | wc -l | tr -d ' ')
# 主 feed（index.php/feed/index.html）保留：侧栏「文章 RSS」在用
find index.php/feed -mindepth 1 -type d -exec rm -rf {} + 2>/dev/null || true
find index.php/feed -mindepth 1 -maxdepth 1 -type f ! -name 'index.html' -delete 2>/dev/null || true
rm -rf index.php/action index.php/author 2>/dev/null || true
find . -type d -empty -delete 2>/dev/null || true
# 每篇文章的评论翻页（评论已全站关闭）
find index.php/archives -type f \( -name 'comment-page-*' -o -name '*respond*' \) -delete 2>/dev/null || true
# 去掉指向已删除页面的 <link rel="alternate"> feed 元标签，避免死链
find . -type f -name '*.html' -exec sed "${SI[@]}" -E \
  -e 's@<link[^>]*rel="alternate"[^>]*type="application/(rss|atom)\+xml"[^>]*>@@g' {} +
AFTER=$(find . -type f | wc -l | tr -d ' ')
echo "    文件数 ${BEFORE} -> ${AFTER} [删除 $((BEFORE - AFTER)) 个]"

echo "    [fix] WebP 转换"
if command -v cwebp >/dev/null 2>&1; then
  find usr/uploads/homer -name '*.jpg' | while read -r f; do
    [ -f "${f%.jpg}.webp" ] || \
      cwebp -quiet -q 70 -resize 1400 0 "$f" -o "${f%.jpg}.webp" >/dev/null 2>&1 || true
  done
  # -resize 1400 0：宽度上限 1400、等比缩高；竖长神像不再以 3400px 高度部署
  # 仅重写 <img> 引用与数据 JSON；og:image/twitter:image 保留 jpg 原件
  find . -type f -name '*.html' -exec sed "${SI[@]}" -E \
    -e 's@(<img[^>]*src="[^"]*usr/uploads/homer/[A-Za-z0-9_]+)\.jpg@\1.webp@g' {} +
  find . -type f -name '*.json' -exec sed "${SI[@]}" -E \
    -e 's@("img":"[^"]*usr/uploads/homer/[A-Za-z0-9_]+)\.jpg@\1.webp@g' {} +
else
  echo "    [fix] 跳过 WebP（未安装 cwebp）"
fi

echo "    [fix] CSS 合并（4 请求 → 1）"
# 把 normalize/grid/appearance 追加进 style.css，保留 style.css 文件名
# （styleswitch.js 靠 link[href*="style.css"] 推导主题目录基址），
# 再删掉另外三个 link 标签。追加顺序按原加载顺序。
if [ -f usr/themes/default/style.css ]; then
  BUNDLE=$(mktemp)
  cat usr/themes/default/normalize.css usr/themes/default/grid.css \
      usr/themes/default/style.css usr/themes/default/appearance.css \
      > "$BUNDLE" 2>/dev/null || true
  [ -s "$BUNDLE" ] && cp "$BUNDLE" usr/themes/default/style.css
  rm -f "$BUNDLE"
fi
find . -type f -name '*.html' -exec sed "${SI[@]}" -E \
  -e 's@<link[^>]*href="[^"]*normalize\.css"[^>]*>@@g' \
  -e 's@<link[^>]*href="[^"]*grid\.css"[^>]*>@@g' \
  -e 's@<link[^>]*href="[^"]*appearance\.css"[^>]*>@@g' {} +
# CF Pages 增量上传不会删除旧资产：把被合并的三个 CSS 写成 0 字节占位，
# 避免历史部署残留的旧内容继续被抓取（GH Pages 无此问题）
for css in normalize.css grid.css appearance.css; do
  : > "usr/themes/default/$css"
done

echo "    [fix] CSS 压缩"
python3 - <<'PYCSS'
import re
f = 'usr/themes/default/style.css'
s = open(f, encoding='utf-8').read()
n0 = len(s)
# 去注释（保留 /*! 许可注释）、压缩空白、去掉规则内多余空格
s = re.sub(r'/\*(?!!)[\s\S]*?\*/', '', s)
s = re.sub(r'\s+', ' ', s)
s = re.sub(r'\s*([{};:,>])\s*', r'\1', s)
s = s.replace(';}', '}')
s = s.replace('@media (', '@media(') if False else s
open(f, 'w', encoding='utf-8').write(s)
print('    style.css %d -> %d 字节 (-%.0f%%)' % (n0, len(s), (1 - len(s)/n0)*100))
PYCSS

echo "    [fix] 静态资源构建戳（配合长缓存）"
TS=$(date +%s)
python3 - "$TS" <<'PYEOF'
import glob, re, sys, os
ts = sys.argv[1]
n = 0
for f in glob.glob('**/*.html', recursive=True):
    s = open(f, encoding='utf-8', errors='ignore').read()
    s2 = re.sub(r'(href="[^"]*style\.css)(?:\?v=\d+)?(")', r'\1?v=' + ts + r'\2', s)
    if s2 != s:
        open(f, 'w', encoding='utf-8').write(s2)
        n += 1
print('    CSS 戳 ?v=%s（%d 页）' % (ts, n))
PYEOF

echo "    [fix] 可访问性：空 alt 回填"
python3 - "$PWD" <<'PYALT'
import glob, re, sys
os_dir = sys.argv[1]
n = 0
for f in glob.glob('**/*.html', recursive=True):
    s = open(f, encoding='utf-8', errors='ignore').read()
    m = re.search(r'<h1[^>]*>(.*?)</h1>', s, re.S) or re.search(r'<title>(.*?)</title>', s, re.S)
    title = re.sub(r'<[^>]*>', '', m.group(1)).strip().split(' - ')[0] if m else '荷马史诗'
    alt = title.replace('"', '').replace('<', '').replace('>', '') + ' 的插图'
    s2 = re.sub(r'<img([^>]*)\s+alt=""([^>]*)>', lambda mm: '<img' + mm.group(1) + ' alt="' + alt + '"' + mm.group(2) + '>', s)
    if s2 != s:
        open(f, 'w', encoding='utf-8').write(s2)
        n += 1
print('    回填 %d 页的空 alt' % n)
PYALT

echo "    [fix] 清理未被引用的 jpg（WebP 已接管正文，仅留 og:image）"
python3 - <<'PYJPG'
import glob, os, re
# 只收集 html 与 apps 数据 JSON 的引用；credits 元数据里的文件名不算引用
refs = set()
for pat in ('**/*.html', 'apps/*.json'):
    for f in glob.glob(pat, recursive=True):
        try:
            t = open(f, encoding='utf-8', errors='ignore').read()
        except Exception:
            continue
        refs |= set(re.findall(r'([A-Za-z0-9_]+\.jpg)', t))
removed = kept = 0
bytes_freed = 0
for f in glob.glob('usr/uploads/homer/*.jpg'):
    base = os.path.basename(f)
    if base in refs:
        kept += 1
        continue
    bytes_freed += os.path.getsize(f)
    os.remove(f)
    removed += 1
print('    保留 %d 张（被引用），删除 %d 张未引用，释放 %.1f MB' % (kept, removed, bytes_freed/1048576))
PYJPG

echo "    [fix] 图片懒加载"
find . -type f -name '*.html' -exec sed "${SI[@]}" \
  -e 's@<img @<img loading="lazy" @g' \
  -e 's@<img loading="lazy" loading="lazy" @<img loading="lazy" @g' {} +
# 文章首图（题图）在首屏，取消懒加载以免首屏空白；仅处理带 <!--more--> 的文章页
# BSD/GNU sed 都支持：对含 more 标记的文件，用 perl 精确去掉第一个 img 的 loading
for f in $(grep -rl '<!--more-->' index.php/archives --include='*.html' 2>/dev/null); do
  perl -0pi -e 's@<img loading="lazy" @<img @' "$f"
done

echo "    [fix] 404 页面"
cp "$SELF_DIR/../apps/404.html" 404.html
sed "${SI[@]}" "s~@BASE@~$BASE~g" 404.html
[ -f apps/404.html ] && sed "${SI[@]}" "s~@BASE@~$BASE~g" apps/404.html

echo "    [fix] giscus 评论"
if [ -n "$GISCUS_REPO" ] && [ -d index.php/archives ]; then
  for f in $(find index.php/archives -name 'index.html'); do
    cid=$(echo "$f" | sed -E 's@index.php/archives/([0-9]+)/index.html@\1@')
    grep -q 'giscus.app' "$f" && continue
    GISCUS_HTML='<div style="max-width:680px;margin:30px auto 0;padding:0 14px;"><script src="https://giscus.app/client.js" data-repo="0xAAcodeislaw/homer-epics" data-repo-id="'$GISCUS_REPO'" data-category="General" data-category-id="'$GISCUS_CAT_ID'" data-mapping="specific" data-term="/index.php/archives/'$cid'/" data-reactions-enabled="1" data-emit-metadata="0" data-input-position="top" data-theme="noborder_light" data-lang="zh-CN" crossorigin="anonymous" async></script></div>'
    # 优先插到评论区块之前（保持在主栏内）；否则退回 </body> 前
    if grep -q '<div id="comments">' "$f"; then
      sed "${SI[@]}" "s@<div id="comments">@$GISCUS_HTML<div id="comments">@" "$f"
    else
      sed "${SI[@]}" "s@</body>@$GISCUS_HTML</body>@" "$f"
    fi
  done
fi

echo "    [fix] canonical 归一化"
if [ -n "$CANONICAL" ]; then
  for f in $(find . -name '*.html' -not -path './apps/*'); do
    rel=${f#./}
    case "$rel" in
      index.html)   P="/" ;;
      */index.html) P="/${rel%index.html}" ;;
      *)            P="/${rel%.html}/" ;;
    esac
    # 覆写而非跳过：wget 会把 Typecho 自带 canonical 转成相对路径（如 ./），必须替换
    if grep -q 'rel="canonical"' "$f"; then
      sed "${SI[@]}" "s@<link rel=\"canonical\"[^>]*>@<link rel=\"canonical\" href=\"$CANONICAL$P\">@" "$f"
    else
      sed "${SI[@]}" "s@</head>@<link rel=\"canonical\" href=\"$CANONICAL$P\">@" "$f"
    fi
  done
fi
echo "    [fix] 完成"
