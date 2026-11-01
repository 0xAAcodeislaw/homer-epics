# 荷马史诗 · Homer Epics

《伊利亚特》与《奥德赛》经典故事站点，Typecho 源码 + 仿七十年代中国连环画（小人书）风格插图，另收维基共享资源艺术史名作。全站 89 篇：伊利亚特 27、奥德赛 25、解读 12、知识点 8、扩展阅读 17，插图 45 幅。

## 在线地址

- **Cloudflare Pages（主站）**: https://homer-epics.pages.dev
- **GitHub Pages（镜像）**: https://0xaacodeislaw.github.io/homer-epics/
- **Typecho 动态版（未启用）**: 需 Cloudflare Workers Paid（$5/月，Containers 功能），开通后 `npx wrangler deploy` 一条命令重建（配置见 `wrangler.jsonc` + `worker/index.ts`）

## 仓库结构

```
├── Dockerfile              # php:8.3-apache + 构建期自动安装 Typecho(SQLite) + 灌入内容
│                           #   管理员密码构建期随机生成（存镜像 /root/admin-password.txt），不入库
├── wrangler.jsonc          # CF Containers 动态版配置（Worker + 容器类，需 Workers Paid）
├── worker/index.ts         # 动态版路由 Worker
├── scripts/
│   ├── seed.php            # 内容灌库（content/*.md → SQLite，生成目录/聚合页/搜索画廊数据）
│   ├── deploy-pages.sh     # 一键部署 Cloudflare Pages（构建→容器→wget 镜像→适配→发布）
│   ├── deploy-ghpages.sh   # 一键部署 GitHub Pages（子路径 /homer-epics/ 适配 → gh-pages 分支）
│   └── imggen/             # 插图生成脚本归档（pollinations 文案，历史参考）
├── content/                # 89 篇正文（Markdown + frontmatter：title/slug/order/image）
│   ├── iliad/ odyssey/ analysis/ knowledge/ extended/
├── apps/                   # 静态工具页：关系图/航海地图/全文搜索/画廊/小测/朗读版 + 数据 JSON
├── usr/uploads/homer/      # 45 幅插图（连环画风格 + 维基共享名作，含授权清单 wiki_credits.json）
└── usr/themes/ usr/plugins/ var/ admin/   # Typecho v1.3.0 原版
```

## 内容管线

章节为 Markdown + frontmatter（title/slug/image/order），`scripts/seed.php` 在 Docker 构建期读取并写入 SQLite，同时生成全站目录、扩展阅读聚合页、搜索索引与画廊数据。frontmatter 的 order 1–89 即全站阅读顺序。

## 部署

```bash
# Cloudflare Pages（主站）
./scripts/deploy-pages.sh

# GitHub Pages（镜像，发布到 0xaacodeislaw.github.io/homer-epics/）
#   前提：仓库为 public（Pages 要求）；gh 已登录
./scripts/deploy-ghpages.sh
```

两套脚本都会：构建镜像（含自动装站灌库）→ wget 抓取静态镜像 → 修补主题/工具页与路径 → 发布。GH 版额外做子路径适配（根相对路径统一补 `/homer-epics` 前缀）并 `--force` 更新 gh-pages 分支。

## 说明

- 静态镜像无后台/评论；管理员密码仅存在于构建产物内部（`docker exec <容器> cat /root/admin-password.txt`），公开仓库不含任何凭据。
- 改内容以仓库为准：改 `content/` → 重跑对应部署脚本。
- 部分插图由 Pollinations 免费通道（sana 模型）生成，构图与《荷马史诗》场景偶有出入，属已知限制；维基共享资源图片的作者与授权标注在各图图注及 `usr/uploads/homer/wiki_credits.json`。
