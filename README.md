# 荷马史诗 · Homer Epics

《伊利亚特》与《奥德赛》经典故事站点，Typecho 源码 + 仿七十年代中国连环画（小人书）风格插图。

## 在线地址

- **静态镜像（当前线上）**: https://homer-epics.pages.dev （Cloudflare Pages 免费托管，由预装完成的 Typecho 实例镜像生成）
- **Typecho 动态版（待启用）**: https://homer-epics.0xaa-codeislaw.workers.dev —— 需要 Cloudflare **Workers Paid** 套餐（$5/月，Containers 功能）。开通后运行 `npx wrangler deploy` 即可，镜像与配置已全部就绪。

## 仓库结构

```
├── Dockerfile            # php:8.3-apache + 构建期自动安装 Typecho(SQLite) + 灌入内容
├── wrangler.jsonc        # CF Containers 部署配置（Worker + 容器类）
├── worker/index.ts       # 路由 Worker（单实例容器）
├── scripts/seed.php      # 内容灌库脚本（读取 content/*.md 写入 SQLite）
├── content/
│   ├── iliad/            # 伊利亚特 7 章（含导读）
│   └── odyssey/          # 奥德赛 7 章（含导读）
├── usr/uploads/homer/    # 14 张终选插图（1024×730，已裁水印）
├── usr/themes/ usr/plugins/ var/ admin/   # Typecho v1.3.0 原版
└── admin-credentials.txt # 管理员凭据（不入库）
```

## 内容管线

章节为 Markdown + frontmatter（title/slug/image/order），`scripts/seed.php` 在 Docker 构建期读取并写入 SQLite；插图拷入 `usr/uploads/homer/` 后在 frontmatter 引用文件名。正文首图后插入 `<!--more-->`，首页即"封面墙"。

## 重新部署

```bash
# Typecho 容器版（需 Workers Paid）
npx wrangler deploy

# 静态镜像版（免费）
docker build -t homer-final --build-arg SITE_URL=https://homer-epics.pages.dev .
docker rm -f homer-final; docker run -d --name homer-final -p 8088:80 homer-final
cd /tmp && rm -rf homer-mirror && mkdir homer-mirror && cd homer-mirror
wget -q -r -np -N --convert-links --adjust-extension --page-requisites -e robots=off --no-host-directories http://localhost:8088/
# 主题 CSS 为绝对链接需手动补齐：
for f in grid.css normalize.css style.css; do mkdir -p usr/themes/default; curl -s -o usr/themes/default/$f http://localhost:8088/usr/themes/default/$f; done
rm -rf admin action
find . -type f \( -name "*.html" -o -name "*.xml" \) -exec sed -i '' 's|http://localhost:8088|https://homer-epics.pages.dev|g; s|https://homer-epics.0xaa-codeislaw.workers.dev|https://homer-epics.pages.dev|g' {} +
npx wrangler pages deploy . --project-name homer-epics --branch main
```

## 说明

- 静态镜像无后台/评论/搜索；动态内容以仓库为准（改 content/ → 重建 → 重部署）。
- 部分插图由 Pollinations 免费通道（sana 模型）生成，构图与《荷马史诗》场景偶有出入，属已知限制。
