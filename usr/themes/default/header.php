<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>

    <!-- 使用url函数转换相关路径 -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('normalize.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('grid.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('style.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('appearance.css'); ?>">
    <?php
    // 社交分享卡片：og:image 用固定主视觉（部署时该图保留 jpg 原件）
    $ogTitle = $this->is('post') ? htmlspecialchars($this->title, ENT_QUOTES) : htmlspecialchars($this->options->title, ENT_QUOTES);
    $ogUrl = $this->is('post') ? htmlspecialchars($this->permalink, ENT_QUOTES) : htmlspecialchars($this->options->siteUrl, ENT_QUOTES);
    $ogDesc = htmlspecialchars($this->options->description, ENT_QUOTES);
    $ogImg = rtrim($this->options->siteUrl, '/') . '/usr/uploads/homer/iliad_07_trojan_horse.jpg';
    ?>
    <meta property="og:type" content="<?php echo $this->is('post') ? 'article' : 'website'; ?>">
    <meta property="og:site_name" content="<?php $this->options->title(); ?>">
    <meta property="og:title" content="<?php echo $ogTitle; ?>">
    <meta property="og:description" content="<?php echo $ogDesc; ?>">
    <meta property="og:url" content="<?php echo $ogUrl; ?>">
    <meta property="og:image" content="<?php echo $ogImg; ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $ogTitle; ?>">
    <meta name="twitter:description" content="<?php echo $ogDesc; ?>">
    <meta name="twitter:image" content="<?php echo $ogImg; ?>">
        <script>
    (function () {
        var KEY = 'homer-appearance', p;
        try { p = localStorage.getItem(KEY); } catch (e) { p = null; }
        if (p !== 'light' && p !== 'dark') {
            p = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        if (p === 'dark') document.documentElement.setAttribute('data-appearance', 'dark');
        /* 风格变体尽早注入，避免闪白（终端/代码/黑客帝国/霓虹） */
        var t = null;
        try { t = localStorage.getItem('homer-theme'); } catch (e) { t = null; }
        var VARIANTS = { terminal: 'theme-terminal.css', code: 'theme-code.css',
                         matrix: 'theme-matrix.css', cyber: 'theme-cyber.css' };
        if (VARIANTS[t]) {
            document.documentElement.setAttribute('data-style', t);
            var bl = document.querySelector('link[href*="style.css"]');
            if (bl) {
                var vl = document.createElement('link');
                vl.rel = 'stylesheet';
                vl.href = bl.getAttribute('href').replace(/style\.css.*$/, '') + VARIANTS[t];
                document.head.appendChild(vl);
            }
        }
    })();
    </script>

    <!-- 通过自有函数输出HTML头部信息 -->
    <?php $this->header(); ?>
    <script src="<?php $this->options->themeUrl('appearance.js'); ?>" defer></script>
    <script src="<?php $this->options->themeUrl('styleswitch.js'); ?>" defer></script>
</head>
<body>

<header id="header" class="clearfix">
    <div class="container">
        <div class="row">
            <div class="site-name col-mb-12 col-9">
                <?php if ($this->options->logoUrl): ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>">
                        <img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>"/>
                    </a>
                <?php else: ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title() ?></a>
                    <p class="description"><?php $this->options->description() ?></p>
                <?php endif; ?>
            </div>
            <div class="site-search col-3 kit-hidden-tb">
                <form id="search" method="get" action="/apps/search.html" role="search">
                    <label for="s" class="sr-only"><?php _e('搜索关键字'); ?></label>
                    <input type="text" id="s" name="q" class="text" placeholder="<?php _e('输入关键字搜索'); ?>"/>
                    <button type="submit" class="submit"><?php _e('搜索'); ?></button>
                </form>
            </div>
            <div class="col-mb-12">
                <nav id="nav-menu" class="clearfix" role="navigation">
                    <a<?php if ($this->is('index')): ?> class="current"<?php endif; ?>
                        href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
                    <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
                    <?php while ($pages->next()): ?>
                        <a<?php if ($this->is('page', $pages->slug)): ?> class="current"<?php endif; ?>
                            href="<?php $pages->permalink(); ?>"
                            title="<?php $pages->title(); ?>"><?php $pages->title(); ?></a>
                    <?php endwhile; ?>
                        <select id="style-toggle" aria-label="选择风格"
                                title="选择风格：典藏 → 终端 → 代码 → 黑客帝国 → 霓虹">
                            <option value="classic">风格 · 典藏</option>
                            <option value="terminal">风格 · 终端</option>
                            <option value="code">风格 · 代码</option>
                            <option value="matrix">风格 · 黑客帝国</option>
                            <option value="cyber">风格 · 霓虹</option>
                        </select>
                        <select id="appearance-toggle" aria-label="选择外观"
                                title="选择外观：跟随系统 / 浅色 / 深色">
                            <option value="auto">外观 · 跟随系统</option>
                            <option value="light">外观 · 浅色</option>
                            <option value="dark">外观 · 深色</option>
                        </select>
                </nav>
            </div>
        </div><!-- end .row -->
    </div>
</header><!-- end #header -->
<div id="body">
    <div class="container">
        <div class="row">

    
    
