<?php
// Локальный запуск как на хостинге: php -S localhost:8770 -t . tools/dev-router.php → http://localhost:8770/go-flow/
$root = realpath(__DIR__ . '/..');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rewrites = [
    '#^/go-flow/p/([a-z0-9-]+)/?$#' => ['/go-flow/project.php', 'slug'],
    '#^/go-flow/r/([a-z0-9]+)/?$#' => ['/go-flow/r.php', 'c'],
];
foreach ($rewrites as $re => [$script, $param]) {
    if (preg_match($re, $path, $m)) { $_GET[$param] = $m[1]; $path = $script; break; }
}
if (preg_match('#^/go-flow/(lib|data)(/|$)|^/go-flow/config#', $path)) { http_response_code(403); exit('403'); }
if (substr($path, -1) === '/') $path .= 'index.php';
$file = $root . $path;
if (!is_file($file)) return false;
if (substr($file, -4) !== '.php') return false;
$_SERVER['SCRIPT_NAME'] = $path;
$_SERVER['SCRIPT_FILENAME'] = $file;
$_SERVER['PHP_SELF'] = $path;
chdir(dirname($file));
require $file;
