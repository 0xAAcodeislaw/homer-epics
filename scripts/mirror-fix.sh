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

echo "    [fix] WebP 转换"
if command -v cwebp >/dev/null 2>&1; then
  find usr/uploads/homer -name '*.jpg' | while read -r f; do
    [ -f "${f%.jpg}.webp" ] || cwebp -quiet "$f" -o "${f%.jpg}.webp" >/dev/null 2>&1 || true
  done
  # 仅重写 <img> 引用与数据 JSON；og:image/twitter:image 保留 jpg 原件
  find . -type f -name '*.html' -exec sed "${SI[@]}" -E \
    -e 's@(<img[^>]*src="[^"]*usr/uploads/homer/[A-Za-z0-9_]+)\.jpg@\1.webp@g' {} +
  find . -type f -name '*.json' -exec sed "${SI[@]}" -E \
    -e 's@("img":"[^"]*usr/uploads/homer/[A-Za-z0-9_]+)\.jpg@\1.webp@g' {} +
else
  echo "    [fix] 跳过 WebP（未安装 cwebp）"
fi

echo "    [fix] 图片懒加载"
find . -type f -name '*.html' -exec sed "${SI[@]}" \
  -e 's@<img @<img loading="lazy" @g' \
  -e 's@<img loading="lazy" loading="lazy" @<img loading="lazy" @g' {} +

echo "    [fix] 404 页面"
cp "$SELF_DIR/../apps/404.html" 404.html
sed "${SI[@]}" "s~@BASE@~$BASE~g" 404.html

echo "    [fix] giscus 评论"
if [ -n "$GISCUS_REPO" ] && [ -d index.php/archives ]; then
  for f in $(find index.php/archives -name 'index.html'); do
    cid=$(echo "$f" | sed -E 's@index.php/archives/([0-9]+)/index.html@\1@')
    grep -q 'giscus.app' "$f" && continue
    GISCUS_HTML='<div style="max-width:680px;margin:30px auto 0;padding:0 14px;"><script src="https://giscus.app/client.js" data-repo="0xAAcodeislaw/homer-epics" data-repo-id="'$GISCUS_REPO'" data-category="General" data-category-id="'$GISCUS_CAT_ID'" data-mapping="specific" data-term="/index.php/archives/'$cid'/" data-reactions-enabled="1" data-emit-metadata="0" data-input-position="top" data-theme="noborder_light" data-lang="zh-CN" crossorigin="anonymous" async></script></div>'
    sed "${SI[@]}" "s@</body>@$GISCUS_HTML</body>@" "$f"
  done
fi

echo "    [fix] canonical 归一化"
if [ -n "$CANONICAL" ]; then
  for f in $(find . -name '*.html' -not -path './apps/*'); do
    case "$f" in
      ./index.html) P="/" ;;
      */index.html) P="/$(echo "$f" | sed -E 's@^\./@@; s@/index\.html$@/@')" ;;
      *) P="/$(echo "$f" | sed -E 's@^\./@@; s@\.html$@/')" ;;
    esac
    grep -q 'rel="canonical"' "$f" || sed "${SI[@]}" "s@</head>@<link rel=\"canonical\" href=\"$CANONICAL$P\"></head>@" "$f"
  done
fi
echo "    [fix] 完成"
