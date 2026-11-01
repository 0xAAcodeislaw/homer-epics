<?php
/**
 * PHP 内置服务器路由脚本（CI 渲染镜像用）：
 *   php -S 127.0.0.1:8088 scripts/ci-router.php
 * 真实存在的文件直接放行；其余交给 index.php 并伪造 PATH_INFO，
 * 等效于 Apache 的 AcceptPathInfo，让 /index.php/archives/3/ 正常路由。
 */
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path === '/' || $path === '') {
    $_SERVER['PATH_INFO'] = '/';
    require __DIR__ . '/../index.php';
    return true;
}
$file = __DIR__ . '/../' . ltrim($path, '/');
if (!is_dir($file) && file_exists($file)) {
    return false; // 静态资源（图片/CSS/JS/JSON）直出
}
$_SERVER['PATH_INFO'] = '/';
if (preg_match('#^/index\.php(/|$)#', $path)) {
    $info = substr($path, strlen('/index.php'));
    $_SERVER['PATH_INFO'] = $info === '' ? '/' : $info;
}
require __DIR__ . '/../index.php';
return true;
