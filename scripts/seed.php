<?php
/**
 * 构建期内容灌库脚本：读取 content/ 下的 Markdown 章节，写入 SQLite。
 * 由 Dockerfile 在 `php install.php` 之后调用，需设置 SITE_URL 环境变量。
 */

$root = '/var/www/html';
$siteUrl = getenv('SITE_URL') ?: 'https://homer-epics.0xaa-codeislaw.workers.dev';

$dbFile = getenv('TYPECHO_DB_FILE');
if (!$dbFile || !file_exists($dbFile)) {
    $hits = glob($root . '/usr/*.db');
    $dbFile = $hits[0] ?? '';
}
if (!$dbFile || !file_exists($dbFile)) {
    fwrite(STDERR, "seed: SQLite 数据库未找到\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$P = 'typecho_';

/* ---------- 站点选项 ---------- */
$optionUpdates = [
    'title'                => '荷马史诗',
    'description'          => '《伊利亚特》与《奥德赛》：以中国连环画风重现的荷马史诗经典故事',
    'keywords'             => '荷马史诗,伊利亚特,奥德赛,希腊神话,特洛伊战争,连环画',
    'pageSize'             => '10',
    'siteUrl'              => $siteUrl,
    'defaultAllowComment'  => '0',
];
$stmt = $pdo->prepare("UPDATE {$P}options SET value = ? WHERE user = 0 AND name = ?");
foreach ($optionUpdates as $name => $value) {
    $stmt->execute([$value, $name]);
}

$pdo->prepare("UPDATE {$P}users SET url = ? WHERE uid = 1")->execute([$siteUrl]);

/* ---------- 分类：默认分类改为伊利亚特，并新增奥德赛、解读 ---------- */
$pdo->prepare("UPDATE {$P}metas SET name = ?, slug = ?, description = ? WHERE mid = 1")
    ->execute(['伊利亚特', 'iliad', '阿喀琉斯的愤怒——特洛伊战争最后一年的五十天']);
$pdo->prepare("INSERT INTO {$P}metas (name, slug, type, description, count) VALUES (?, ?, 'category', ?, 0)")
    ->execute(['奥德赛', 'odyssey', '奥德修斯的归乡十年漂流']);
$odysseyMid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO {$P}metas (name, slug, type, description, count) VALUES (?, ?, 'category', ?, 0)")
    ->execute(['解读', 'analysis', '读懂史诗：主题、结构与两千年回声']);
$analysisMid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO {$P}metas (name, slug, type, description, count) VALUES (?, ?, 'category', ?, 0)")
    ->execute(['知识点', 'knowledge', '人物、神谱、地名、名物、术语、年表与名句速查']);
$knowledgeMid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO {$P}metas (name, slug, type, description, count) VALUES (?, ?, 'category', ?, 0)")
    ->execute(['扩展阅读', 'extended', '失传史诗、后世回声、名场面细读与条漫试笔']);
$extendedMid = (int)$pdo->lastInsertId();
$midMap = ['iliad' => 1, 'odyssey' => $odysseyMid, 'analysis' => $analysisMid, 'knowledge' => $knowledgeMid, 'extended' => $extendedMid];

/* ---------- 清理默认内容 ---------- */
$pdo->exec("DELETE FROM {$P}comments WHERE cid = 1");
$pdo->exec("DELETE FROM {$P}relationships WHERE cid = 1");
$pdo->exec("DELETE FROM {$P}contents WHERE cid = 1");

/* ---------- 关于页面 ---------- */
$aboutText = "<!--markdown-->\n\n"
    . "本站收录荷马史诗《伊利亚特》与《奥德赛》的经典故事，全部篇章配以仿七十年代中国连环画（小人书）风格的插图。\n\n"
    . "- 《伊利亚特》讲述特洛伊战争最后一年的故事，围绕希腊第一勇士阿喀琉斯的愤怒展开。\n"
    . "- 《奥德赛》讲述伊萨卡国王奥德修斯战后历经十年漂泊、智斗妖魔、终于归乡团圆的旅程。\n"
    . "- 「解读」板块收录赏析文章：作者之谜、特洛伊考古、主题分析、叙事艺术与两千年接受史；「知识点」板块提供速查工具：人物谱、神谱、地名志、名物志、术语表、大事年表与名句选。\n\n"
    . "站点基于 Typecho 构建，部署于 Cloudflare。\n";
$pdo->prepare("UPDATE {$P}contents SET title = ?, slug = ?, text = ?, modified = ? WHERE cid = 2 AND type = 'page'")
    ->execute(['关于本站', 'about', $aboutText, time()]);

/* ---------- 章节 ---------- */
function parseChapter(string $file): ?array
{
    $raw = file_get_contents($file);
    if ($raw === false || strncmp($raw, '---', 3) !== 0) {
        return null;
    }
    $end = strpos($raw, "\n---", 3);
    if ($end === false) {
        return null;
    }
    $meta = [];
    foreach (explode("\n", trim(substr($raw, 3, $end - 3))) as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $meta[trim($parts[0])] = trim($parts[1]);
        }
    }
    $meta['_body'] = trim(substr($raw, $end + 4));
    return $meta;
}

$chapters = [];
foreach (['content/iliad' => 'iliad', 'content/odyssey' => 'odyssey', 'content/analysis' => 'analysis', 'content/knowledge' => 'knowledge', 'content/extended' => 'extended'] as $dir => $cat) {
    foreach (glob($root . '/' . $dir . '/*.md') ?: [] as $file) {
        $meta = parseChapter($file);
        if ($meta && isset($meta['title'], $meta['slug'], $meta['order'])) {
            $meta['_category'] = $cat;
            $chapters[] = $meta;
        }
    }
}
usort($chapters, fn($a, $b) => (int)$a['order'] <=> (int)$b['order']);

$base = time();
$insert = $pdo->prepare(
    "INSERT INTO {$P}contents (title, slug, created, modified, text, authorId, type, status,
     commentsNum, allowComment, allowPing, allowFeed, parent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$relate = $pdo->prepare("INSERT INTO {$P}relationships (cid, mid) VALUES (?, ?)");

$count = 0;
foreach ($chapters as $ch) {
    $count++;
    $created = $base - (int)$ch['order'] * 86400; // 越靠前章节时间越新，首页即阅读顺序
    $text = "<!--markdown-->\n\n";
    if (!empty($ch['image'])) {
        $text .= "![](/usr/uploads/homer/{$ch['image']})\n\n<!--more-->\n\n";
        $text .= $ch['_body'];
    } else {
        // 无图章节：摘要切在第一段之后，保证首页可读
        $pos = strpos($ch['_body'], "\n\n");
        if ($pos !== false) {
            $text .= substr($ch['_body'], 0, $pos) . "\n\n<!--more-->\n\n" . substr($ch['_body'], $pos + 2);
        } else {
            $text .= $ch['_body'];
        }
    }

    $insert->execute([
        $ch['title'], $ch['slug'], $created, $created, $text,
        1, 'post', 'publish', 0, 0, 1, 1, 0,
    ]);
    $relate->execute([(int)$pdo->lastInsertId(), $midMap[$ch['_category']]]);
}

/* ---------- 分类计数 ---------- */
foreach ($midMap as $mid) {
    $pdo->prepare("UPDATE {$P}metas SET count = (SELECT COUNT(*) FROM {$P}relationships WHERE mid = ?) WHERE mid = ?")
        ->execute([$mid, $mid]);
}

/* ---------- 全站目录页（随内容自动生成） ---------- */
$catalog = "<!--markdown-->\n\n"
    . "全站共 {$count} 篇，按阅读顺序排列。点击标题即可进入对应章节。\n\n";
foreach ($midMap as $catSlug => $mid) {
    $catName = $pdo->prepare("SELECT name, description FROM {$P}metas WHERE mid = ?");
    $catName->execute([$mid]);
    $cat = $catName->fetch(PDO::FETCH_ASSOC);
    $catalog .= "## {$cat['name']}\n\n{$cat['description']}\n\n";
    $list = $pdo->prepare(
        "SELECT c.cid, c.title FROM {$P}contents c
         JOIN {$P}relationships r ON r.cid = c.cid
         WHERE r.mid = ? AND c.type = 'post' AND c.status = 'publish'
         ORDER BY c.created DESC" // created 越大序号越靠前 = 阅读顺序
    );
    $list->execute([$mid]);
    $n = 0;
    while ($row = $list->fetch(PDO::FETCH_ASSOC)) {
        $n++;
        $catalog .= "{$n}. [{$row['title']}](/index.php/archives/{$row['cid']}/)\n";
    }
    $catalog .= "\n";
}

$stmt = $pdo->query("SELECT cid FROM {$P}contents WHERE slug = 'catalog' AND type = 'page'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pdo->prepare("UPDATE {$P}contents SET title = ?, text = ?, modified = ? WHERE cid = ?")
        ->execute(['全站目录', $catalog, time(), $row['cid']]);
} else {
    $pdo->prepare(
        "INSERT INTO {$P}contents (title, slug, created, modified, text, authorId, type, status,
         commentsNum, allowComment, allowPing, allowFeed, parent, `order`)
         VALUES ('全站目录', 'catalog', ?, ?, ?, 1, 'page', 'publish', 0, 0, 1, 1, 0, 2)"
    )->execute([time(), time(), $catalog]);
}
// 「扩展阅读」聚合页（导航第二项）：内容三辑 + 工具箱
$extList = $pdo->prepare(
    "SELECT c.cid, c.title FROM {$P}contents c
     JOIN {$P}relationships r ON r.cid = c.cid
     WHERE r.mid = ? AND c.type = 'post' AND c.status = 'publish'
     ORDER BY c.created DESC"
);
$extList->execute([$extendedMid]);
$extItems = [];
while ($row = $extList->fetch(PDO::FETCH_ASSOC)) {
    $extItems[] = $row;
}
$hub = "<!--markdown-->\n\n"
    . "这里是故事的延伸书架与工具箱，与正文互相成全。\n\n"
    . "### 第一辑 · 循环史诗\n\n"
    . "荷马之外，古代还流传着六部同题材史诗，如今仅存梗概。本辑以白话复原它们，补全特洛伊战争的来龙去脉：\n\n";
$n = 0;
foreach ($extItems as $row) {
    if ($n >= 6) break;
    $n++;
    $hub .= "{$n}. [{$row['title']}](/index.php/archives/{$row['cid']}/)\n";
}
$hub .= "\n### 第二辑 · 后世回声\n\n从维吉尔、但丁到乔伊斯与流行文化，看两千年里荷马被怎样一次次重写：\n\n";
foreach (array_slice($extItems, 6, 5) as $i => $row) {
    $hub .= ($i + 1) . ". [{$row['title']}](/index.php/archives/{$row['cid']}/)\n";
}
$hub .= "\n### 第三辑 · 名场面细读与条漫\n\n逐句拆解荷马的笔法，并试写四格连环画：\n\n";
foreach (array_slice($extItems, 11) as $i => $row) {
    $hub .= ($i + 1) . ". [{$row['title']}](/index.php/archives/{$row['cid']}/)\n";
}
$hub .= "\n### 工具箱\n\n"
    . "- [人物关系图](/apps/relations.html) —— 三大阵营一张图，点击人物直达词条\n"
    . "- [航海地图](/apps/map.html) —— 奥德修斯十年漂泊的十二站\n"
    . "- [全文搜索](/apps/search.html) —— 站内 72+ 篇一键检索\n"
    . "- [名场面画廊](/apps/gallery.html) —— 全部插图按卷排列\n"
    . "- [知识小测](/apps/quiz.html) —— 二十四道题，试试你像哪位英雄\n"
    . "- [朗读版](/apps/reader.html) —— 浏览器合成语音，把史诗念给你听\n\n";

$stmt = $pdo->query("SELECT cid FROM {$P}contents WHERE slug = 'extended' AND type = 'page'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pdo->prepare("UPDATE {$P}contents SET title = ?, text = ?, modified = ? WHERE cid = ?")
        ->execute(['扩展阅读', $hub, time(), $row['cid']]);
} else {
    $pdo->prepare(
        "INSERT INTO {$P}contents (title, slug, created, modified, text, authorId, type, status,
         commentsNum, allowComment, allowPing, allowFeed, parent, `order`)
         VALUES ('扩展阅读', 'extended', ?, ?, ?, 1, 'page', 'publish', 0, 0, 1, 1, 0, 1)"
    )->execute([time(), time(), $hub]);
}

/* ---------- 搜索索引与画廊数据（供 /apps/ 工具页使用） ---------- */
@mkdir(__DIR__ . '/../apps', 0755, true);
$idx = [];
$imgs = [];
$rows = $pdo->query("SELECT c.cid, c.title, c.slug, c.text, m.slug AS cat FROM {$P}contents c
    JOIN {$P}relationships r ON r.cid = c.cid JOIN {$P}metas m ON m.mid = r.mid
    WHERE c.type = 'post' AND c.status = 'publish' ORDER BY c.created DESC");
while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
    $plain = str_replace(['<!--markdown-->', '<!--more-->'], '', $row['text']);
    $plain = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $plain);
    $plain = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $plain);
    $plain = trim(preg_replace('/<[^>]*>/', '', $plain));
    $idx[] = ['t' => $row['title'], 'u' => '/index.php/archives/' . $row['cid'] . '/',
              'c' => $row['cat'], 'x' => mb_substr($plain, 0, 260)];
    if (preg_match('/\/usr\/uploads\/homer\/([a-z0-9_]+\.jpg)/', $row['text'], $mm)) {
        $imgs[] = ['t' => $row['title'], 'u' => '/index.php/archives/' . $row['cid'] . '/',
                   'img' => '/usr/uploads/homer/' . $mm[1]];
    }
}
/* 独立场景图（无正文挂载的画廊补充） */
$extraImgs = [
    ['卷二 · 梦兆与全军检阅', 'ext_iliad_02_review.jpg', 6],
    ['卷三 · 城头看海伦', 'ext_iliad_03_duel_helen.jpg', 7],
    ['卷五 · 狄俄墨得斯的荣光', 'ext_iliad_05_diomedes.jpg', 9],
    ['卷六 · 赫克托耳与安德洛玛刻', 'ext_iliad_06_farewell.jpg', 10],
    ['卷七 · 埃阿斯单挑赫克托耳', 'ext_iliad_07_ajax_duel.jpg', 11],
    ['卷九 · 使团求和', 'ext_iliad_09_embassy.jpg', 13],
    ['卷十八 · 神匠铸甲', 'ext_iliad_18_forge.jpg', 22],
    ['卷二十三 · 葬礼竞技', 'ext_iliad_23_games.jpg', 27],
    ['奥德赛卷二 · 出走寻父', 'ext_odyssey_02_night_sail.jpg', 32],
    ['奥德赛卷四 · 斯巴达的老王', 'ext_odyssey_04_sparta_feast.jpg', 34],
    ['奥德赛卷六 · 瑙西卡公主', 'ext_odyssey_06_nausicaa.jpg', 36],
    ['奥德赛卷八 · 盲诗人的歌', 'ext_odyssey_08_bard.jpg', 38],
    ['奥德赛卷十一 · 冥府之旅', 'ext_odyssey_11_underworld.jpg', 41],
    ['奥德赛卷十三 · 重返伊萨卡', 'ext_odyssey_13_homecoming.jpg', 43],
    ['奥德赛卷十四 · 牧猪人的棚屋', 'ext_odyssey_14_swineherd.jpg', 44],
    ['奥德赛卷十七 · 老狗阿尔戈斯', 'ext_odyssey_17_argos.jpg', 47],
    ['奥德赛卷二十三 · 夫妻相认', 'ext_odyssey_23_reunion.jpg', 53],
    ['条漫 · 木马计（一）建马', 'comic_01_horse_built.jpg', 90],
    ['条漫 · 木马计（二）入城', 'comic_02_gate.jpg', 90],
    ['条漫 · 木马计（三）狂欢', 'comic_03_feast.jpg', 90],
    ['条漫 · 木马计（四）开门', 'comic_04_night.jpg', 90],
];
foreach ($extraImgs as $e) {
    if (file_exists(__DIR__ . '/../usr/uploads/homer/' . $e[1])) {
        $imgs[] = ['t' => $e[0], 'u' => '/index.php/archives/' . $e[2] . '/', 'img' => '/usr/uploads/homer/' . $e[1]];
    }
}
file_put_contents(__DIR__ . '/../apps/search-data.json', json_encode($idx, JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__ . '/../apps/gallery-data.json', json_encode($imgs, JSON_UNESCAPED_UNICODE));

echo "seed: 已写入 {$count} 章、目录与扩展聚合页、搜索索引 " . count($idx) . " 条、画廊 " . count($imgs) . " 图，站点 URL: {$siteUrl}\n";
