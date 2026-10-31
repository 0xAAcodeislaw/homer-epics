#!/bin/zsh
# 一键部署静态镜像版到 Cloudflare Pages（免费托管，当前线上方案）
# 用法: ./scripts/deploy-pages.sh
# 前提: 本机已安装 docker 与 wrangler，且已 `wrangler login`。
set -e
PROD_URL="${PROD_URL:-https://homer-epics.pages.dev}"
PORT="${PORT:-8088}"

echo "==> 1/5 构建站点镜像（构建期自动安装 Typecho + 灌入 content/ 全部内容）"
docker build -q --build-arg SITE_URL="$PROD_URL" -t homer-final . >/dev/null

echo "==> 2/5 启动本地容器"
docker rm -f homer-final >/dev/null 2>&1 || true
docker run -d --name homer-final -p "$PORT:80" homer-final >/dev/null
sleep 3
code=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/")
[ "$code" = "200" ] || { echo "容器启动异常: HTTP $code"; exit 1; }

echo "==> 3/5 wget 镜像站点"
MIRROR=$(mktemp -d)/mirror
mkdir -p "$MIRROR"
cd "$MIRROR"
wget -q -r -np -N --convert-links --adjust-extension --page-requisites -e robots=off --no-host-directories "http://localhost:$PORT/" || echo "（个别 JS 模板链接跳过，可忽略）"
rm -rf admin action

echo "==> 4/5 补齐主题 CSS 并重写绝对链接"
for f in grid.css normalize.css style.css appearance.css appearance.js; do
  mkdir -p usr/themes/default
  curl -s -o "usr/themes/default/$f" "http://localhost:$PORT/usr/themes/default/$f"
done
find . -type f \( -name "*.html" -o -name "*.xml" \) -exec sed -i '' \
  "s|http://localhost:$PORT|$PROD_URL|g; s|homer-epics\.0xaa-codeislaw\.workers\.dev|$PROD_URL|g; s|https://homer-epics\.pages\.dev|$PROD_URL|g" {} +

echo "==> 4a/5 内链去 index.html 化（消除 308 永久重定向一跳，浏览器不会缓存到旧跳转）"
find . -type f -name "*.html" -not -path "./apps/*" -exec sed -i '' -E \
  -e 's|href="([^"]*)/index\.html"|href="\1/"|g' \
  -e 's|href="index\.html"|href="./"|g' {} +
printf '/index.php/*\n  Cache-Control: no-cache\n' > _headers

echo "==> 4b/5 拷贝工具页数据与阅读追踪脚本，并注入全站"
mkdir -p apps
curl -sf -o apps/search-data.json  "http://localhost:$PORT/apps/search-data.json"
curl -sf -o apps/gallery-data.json "http://localhost:$PORT/apps/gallery-data.json"
curl -sf -o apps/tracker.js        "http://localhost:$PORT/apps/tracker.js"
# apps 工具页用仓库原件覆盖：wget --convert-links 会把 JS 字符串拼接
# （如 ' + it.u + '）误当链接改写，注入 &#32; 导致整页脚本解析失败
for f in relations map search quiz reader gallery; do
  curl -sf -o "apps/$f.html" "http://localhost:$PORT/apps/$f.html"
done
find . -name "*.html" -not -path "./apps/*" -exec sed -i '' \
  "s|</body>|<script src=\"/apps/tracker.js\"></script></body>|" {} +

echo "==> 5/5 部署到 Cloudflare Pages"
npx wrangler@4.156.0 pages deploy . --project-name homer-epics --branch main
echo "完成: $PROD_URL"
