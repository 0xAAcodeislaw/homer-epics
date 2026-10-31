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
    'pageSize'             => '30',
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
    . "本站收录荷马史诗《伊利亚特》与《奥德赛》的经典故事，各卷配以仿七十年代中国连环画（小人书）风格的插图，并收入维基共享资源提供的艺术史名作。\n\n"
    . "- 《伊利亚特》讲述特洛伊战争最后一年的故事，围绕希腊第一勇士阿喀琉斯的愤怒展开。\n"
    . "- 《奥德赛》讲述伊萨卡国王奥德修斯战后历经十年漂泊、智斗妖魔、终于归乡团圆的旅程。\n"
    . "- 「解读」板块收录赏析文章：作者之谜、特洛伊考古、主题分析、叙事艺术与两千年接受史；「知识点」板块提供速查工具：人物谱、神谱、地名志、名物志、术语表、大事年表与名句选；「扩展阅读」收录失传史诗复原、艺术史名作赏析、名场面细读与条漫。\n\n"
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
    $catalog .= "## [{$cat['name']}](/index.php/category/{$catSlug}/)\n\n{$cat['description']}\n\n";
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
    . "这里是故事的延伸书架与工具箱，与正文互相成全。\n\n";
$groups = [
    ['循环史诗 · ', '第一辑 · 循环史诗', '荷马之外，古代还流传着六部同题材史诗，如今仅存梗概。本辑以白话复原它们，补全特洛伊战争的来龙去脉：'],
    ['后世回声 · ', '第二辑 · 后世回声', '从维吉尔、但丁到乔伊斯，从陶瓶、油画到银幕与游戏，看两千年里荷马被怎样一次次重写：'],
    ['名场面细读 · ', '第三辑 · 名场面细读', '逐句拆解荷马的笔法，看神来之笔如何炼成：'],
    ['条漫试笔 · ', '条漫试笔', '四格连环画，把木马计画给你看：'],
];
foreach ($groups as [$prefix, $gTitle, $gDesc]) {
    $hub .= "### {$gTitle}\n\n{$gDesc}\n\n";
    $n = 0;
    foreach ($extItems as $row) {
        if (strpos($row['title'], $prefix) !== 0) continue;
        $n++;
        $hub .= "{$n}. [{$row['title']}](/index.php/archives/{$row['cid']}/)\n";
    }
    $hub .= "\n";
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

// 关于本站排到导航最后
$pdo->prepare("UPDATE {$P}contents SET `order` = 3 WHERE cid = 2 AND type = 'page'")->execute();

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
/* 独立场景图（无正文挂载的画廊补充）。第三列优先用 slug 定位文章，cid 变动不再失联 */
$extraImgs = [
    ['卷二 · 梦兆与全军检阅', 'ext_iliad_02_review.jpg', 'iliad-b2'],
    ['卷三 · 城头看海伦', 'ext_iliad_03_duel_helen.jpg', 'iliad-b3'],
    ['卷五 · 狄俄墨得斯的荣光', 'ext_iliad_05_diomedes.jpg', 'iliad-b5'],
    ['卷六 · 赫克托耳与安德洛玛刻', 'ext_iliad_06_farewell.jpg', 'iliad-b6'],
    ['卷七 · 埃阿斯单挑赫克托耳', 'ext_iliad_07_ajax_duel.jpg', 'iliad-b7'],
    ['卷九 · 使团求和', 'ext_iliad_09_embassy.jpg', 'iliad-b9'],
    ['卷十八 · 神匠铸甲', 'ext_iliad_18_forge.jpg', 'iliad-b18'],
    ['卷二十三 · 葬礼竞技', 'ext_iliad_23_games.jpg', 'iliad-b23'],
    ['奥德赛卷二 · 出走寻父', 'ext_odyssey_02_night_sail.jpg', 'odyssey-b2'],
    ['奥德赛卷四 · 斯巴达的老王', 'ext_odyssey_04_sparta_feast.jpg', 'odyssey-b4'],
    ['奥德赛卷六 · 瑙西卡公主', 'ext_odyssey_06_nausicaa.jpg', 'odyssey-b6'],
    ['奥德赛卷八 · 盲诗人的歌', 'ext_odyssey_08_bard.jpg', 'odyssey-b8'],
    ['奥德赛卷十一 · 冥府之旅', 'ext_odyssey_11_underworld.jpg', 'odyssey-b11'],
    ['奥德赛卷十三 · 重返伊萨卡', 'ext_odyssey_13_homecoming.jpg', 'odyssey-b13'],
    ['奥德赛卷十四 · 牧猪人的棚屋', 'ext_odyssey_14_swineherd.jpg', 'odyssey-b14'],
    ['奥德赛卷十七 · 老狗阿尔戈斯', 'ext_odyssey_17_argos.jpg', 'odyssey-b17'],
    ['奥德赛卷二十三 · 夫妻相认', 'ext_odyssey_23_reunion.jpg', 'odyssey-b23'],
    ['条漫 · 木马计（一）建马', 'comic_01_horse_built.jpg', 'ext-comic-strip'],
    ['条漫 · 木马计（二）入城', 'comic_02_gate.jpg', 'ext-comic-strip'],
    ['条漫 · 木马计（三）狂欢', 'comic_03_feast.jpg', 'ext-comic-strip'],
    ['条漫 · 木马计（四）开门', 'comic_04_night.jpg', 'ext-comic-strip'],
    /* 维基共享资源搬运的艺术史名作（见「陶瓶与画布」篇） */
    ['杯画 · 为帕特罗克洛斯包扎', 'wiki_achilles_patroclus.jpg', 'art-of-homer'],
    ['巨瓶 · 刺瞎波吕斐摩斯', 'wiki_polyphemus.jpg', 'art-of-homer'],
    ['抄本 · 威尼斯抄本 A', 'wiki_venetus_a.jpg', 'art-of-homer'],
    ['铜版 · 忒提斯送新甲', 'wiki_flaxman_thetis.jpg', 'art-of-homer'],
    ['油画 · 普里阿摩斯求尸', 'wiki_priam_hamilton.jpg', 'art-of-homer'],
    ['油画 · 阿喀琉斯的愤怒', 'wiki_anger_david.jpg', 'art-of-homer'],
    ['油画 · 尤利西斯与塞壬', 'wiki_sirens_waterhouse.jpg', 'art-of-homer'],
];
$slugCid = $pdo->prepare("SELECT cid FROM {$P}contents WHERE slug = ? AND type = 'post' AND status = 'publish'");
foreach ($extraImgs as $e) {
    if (!file_exists(__DIR__ . '/../usr/uploads/homer/' . $e[1])) {
        continue;
    }
    if (ctype_digit((string)$e[2])) {
        $u = '/index.php/archives/' . $e[2] . '/';
    } else {
        $slugCid->execute([$e[2]]);
        $hit = $slugCid->fetch(PDO::FETCH_ASSOC);
        if (!$hit) continue;
        $u = '/index.php/archives/' . $hit['cid'] . '/';
    }
    $imgs[] = ['t' => $e[0], 'u' => $u, 'img' => '/usr/uploads/homer/' . $e[1]];
}
file_put_contents(__DIR__ . '/../apps/search-data.json', json_encode($idx, JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__ . '/../apps/gallery-data.json', json_encode($imgs, JSON_UNESCAPED_UNICODE));

echo "seed: 已写入 {$count} 章、目录与扩展聚合页、搜索索引 " . count($idx) . " 条、画廊 " . count($imgs) . " 图，站点 URL: {$siteUrl}\n";
