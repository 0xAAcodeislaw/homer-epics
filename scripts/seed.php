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

/* ---------- 分类：默认分类改为伊利亚特，并新增奥德赛 ---------- */
$pdo->prepare("UPDATE {$P}metas SET name = ?, slug = ?, description = ? WHERE mid = 1")
    ->execute(['伊利亚特', 'iliad', '阿喀琉斯的愤怒——特洛伊战争最后一年的五十天']);
$pdo->prepare("INSERT INTO {$P}metas (name, slug, type, description, count) VALUES (?, ?, 'category', ?, 0)")
    ->execute(['奥德赛', 'odyssey', '奥德修斯的归乡十年漂流']);
$odysseyMid = (int)$pdo->lastInsertId();
$midMap = ['iliad' => 1, 'odyssey' => $odysseyMid];

/* ---------- 清理默认内容 ---------- */
$pdo->exec("DELETE FROM {$P}comments WHERE cid = 1");
$pdo->exec("DELETE FROM {$P}relationships WHERE cid = 1");
$pdo->exec("DELETE FROM {$P}contents WHERE cid = 1");

/* ---------- 关于页面 ---------- */
$aboutText = "<!--markdown-->\n\n"
    . "本站收录荷马史诗《伊利亚特》与《奥德赛》的经典故事，全部篇章配以仿七十年代中国连环画（小人书）风格的插图。\n\n"
    . "- 《伊利亚特》讲述特洛伊战争最后一年的故事，围绕希腊第一勇士阿喀琉斯的愤怒展开。\n"
    . "- 《奥德赛》讲述伊萨卡国王奥德修斯战后历经十年漂泊、智斗妖魔、终于归乡团圆的旅程。\n\n"
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
foreach (['content/iliad' => 'iliad', 'content/odyssey' => 'odyssey'] as $dir => $cat) {
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
    } else {
        $text .= "<!--more-->\n\n";
    }
    $text .= $ch['_body'];

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

echo "seed: 已写入 {$count} 章、2 个分类，站点 URL: {$siteUrl}\n";
