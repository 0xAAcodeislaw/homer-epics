#!/bin/zsh
# 移植：构建静态镜像并发布到 GitHub Pages（项目子路径 /homer-epics/）
# 用法: ./scripts/deploy-ghpages.sh
# 前提: docker；gh 已 `gh auth login`；仓库 0xAAcodeislaw/homer-epics 需为 public（Pages 要求）
set -e
GH_BASE="${GH_BASE:-https://0xaacodeislaw.github.io/homer-epics}"
SUBPATH="/homer-epics"
PORT=8089
OWNER_REPO=0xAAcodeislaw/homer-epics
BRANCH=gh-pages
REPO=$(git rev-parse --show-toplevel)

echo "==> 1/7 构建（SITE_URL=$GH_BASE，管理员密码随机生成）"
PASS=$(openssl rand -hex 8)
docker build -q --build-arg SITE_URL="$GH_BASE" --build-arg TYPECHO_USER_PASSWORD="$PASS" -t homer-gh . >/dev/null
echo "    本次构建管理员密码: admin / $PASS（仅存镜像内 /root/admin-password.txt）"

echo "==> 2/7 启动容器"
docker rm -f homer-gh >/dev/null 2>&1 || true
docker run -d --name homer-gh -p $PORT:80 homer-gh >/dev/null
sleep 3
code=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/")
[ "$code" = "200" ] || { echo "容器启动异常: HTTP $code"; exit 1; }

echo "==> 3/7 wget 镜像"
MIRROR=$(mktemp -d)/mirror
mkdir -p "$MIRROR"
cd "$MIRROR"
wget -q -r -np -N --convert-links --adjust-extension --page-requisites -e robots=off --no-host-directories "http://localhost:$PORT/" || echo "（个别 JS 模板链接跳过，可忽略）"
rm -rf admin action

echo "==> 4/7 补齐主题/工具页并做子路径适配"
for f in grid.css normalize.css style.css appearance.css appearance.js; do
  mkdir -p usr/themes/default
  curl -s -o "usr/themes/default/$f" "http://localhost:$PORT/usr/themes/default/$f"
done
mkdir -p apps
curl -sf -o apps/search-data.json  "http://localhost:$PORT/apps/search-data.json"
curl -sf -o apps/gallery-data.json "http://localhost:$PORT/apps/gallery-data.json"
curl -sf -o apps/tracker.js        "http://localhost:$PORT/apps/tracker.js"
for f in relations map search quiz reader gallery; do
  curl -sf -o "apps/$f.html" "http://localhost:$PORT/apps/$f.html"
done
# 画廊场景图等仅存在于数据文件、未嵌入任何页面的图片，从仓库直接补齐
rsync -a "$REPO/usr/uploads/homer/" usr/uploads/homer/
# 域名改写（Typecho 绝对地址 meta/feed 已随 SITE_URL 带子路径）
find . -type f \( -name "*.html" -o -name "*.xml" \) -exec sed -i '' \
  "s|http://localhost:$PORT|$GH_BASE|g" {} +
# 根相对路径补子路径前缀（双引号属性）
find . -type f \( -name "*.html" -o -name "*.xml" \) -exec sed -i '' -E \
  -e 's@"/(usr|apps|index\.php|feed)@"/homer-epics/\1@g' {} +
# 工具页 JS 内单引号绝对路径（fetch('/apps/...')、CATURL 等）
find . -type f -name "*.html" -path "./apps/*" -exec sed -i '' \
  -e "s@'/apps/@'/homer-epics/apps/@g" \
  -e "s@'/index.php/@'/homer-epics/index.php/@g" {} +
# 数据文件键值
find . -type f -name "*.json" -exec sed -i '' \
  -e 's@"/index.php/@"/homer-epics/index.php/@g' \
  -e 's@"/usr/uploads/@"/homer-epics/usr/uploads/@g' {} +
# sitemap/robots 由 seed 生成（绝对地址已带子路径）
for f in sitemap.xml robots.txt; do
  curl -sf -o "$f" "http://localhost:$PORT/$f" || true
done
# 注入阅读追踪（子路径）
find . -name "*.html" -not -path "./apps/*" -exec sed -i '' \
  "s@</body>@<script src=\"/homer-epics/apps/tracker.js\"></script></body>@" {} +

echo "==> 4b/7 共享后处理（WebP/懒加载/404/canonical/giscus）"
# canonical 指向 CF 主站，避免双站重复内容分摊搜索权重
MIRROR="$(pwd)" BASE="/homer-epics" CANONICAL="https://homer-epics.pages.dev" REPO_SRC="$REPO" \
GISCUS_REPO="${GISCUS_REPO:-R_kgDOVdTxYg}" GISCUS_CAT_ID="${GISCUS_CAT_ID:-DIC_kwDOVdTxYs4DKJX5}" \
"$REPO/scripts/mirror-fix.sh"

# GH Pages 资产缓存 max-age=600：数据请求加构建级缓存戳，发布后立即可见
TS=$(date +%s)
sed -i '' "s@apps/search-data.json@apps/search-data.json?v=$TS@g" apps/search.html
sed -i '' "s@apps/gallery-data.json@apps/gallery-data.json?v=$TS@g" apps/gallery.html
touch .nojekyll

echo "==> 5/7 写入 $BRANCH 分支"
cd "$REPO"
WT=/tmp/ghpages-wt
git worktree remove --force "$WT" >/dev/null 2>&1 || true
git worktree add -B "$BRANCH" "$WT" >/dev/null
rsync -a --delete --exclude='.git' "$MIRROR/" "$WT/"
cd "$WT"
git add -A
git commit -q -m "GitHub Pages 镜像（构建于 $(date '+%F %T')）" || echo "（无变更）"
git push -q --force origin "$BRANCH"
cd "$REPO"
git worktree remove "$WT" >/dev/null
docker rm -f homer-gh >/dev/null

echo "==> 6/7 启用 GitHub Pages（如已启用则跳过）"
if ! gh api "repos/$OWNER_REPO/pages" >/dev/null 2>&1; then
  gh api -X POST "repos/$OWNER_REPO/pages" \
    -f "source[branch]=$BRANCH" -f "source[path]=/" >/dev/null
  echo "    已启用：分支 $BRANCH / 根目录"
fi

echo "==> 7/7 完成: $GH_BASE"
echo "    （GitHub 构建约 1-3 分钟，稍候即可访问）"
