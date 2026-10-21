<?php
/* GO Flow — общий загрузчик: конфиг, база, хелперы. */
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

mb_internal_encoding('UTF-8');
// Сжатие ответа средствами PHP, если сервер не сжимает сам (Apache не сожмёт повторно).
if (PHP_SAPI !== 'cli' && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', '1');
}
date_default_timezone_set('Europe/Moscow');

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Нет config.php. Скопируйте config.sample.php в config.php и заполните.');
}
$GLOBALS['GF_CONFIG'] = require $configFile;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/scoring.php';
require_once __DIR__ . '/links.php';

function cfg(string $key, $default = null)
{
    $c = $GLOBALS['GF_CONFIG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($c) || !array_key_exists($k, $c)) return $default;
        $c = $c[$k];
    }
    return $c;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL-путь папки приложения, например "/go-flow". */
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $root = str_replace('\\', '/', realpath(APP_ROOT));
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    $rel = ($script !== '' && strpos($script, $root) === 0) ? substr($script, strlen($root)) : '';
    $name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = ($rel !== '' && substr($name, -strlen($rel)) === $rel) ? substr($name, 0, -strlen($rel)) : rtrim(dirname($name), '/');
    return $base;
}

/** Абсолютный адрес приложения, например "https://murx.io/go-flow". */
function base_url(): string
{
    $fixed = (string)cfg('base_url', '');
    if ($fixed !== '') return rtrim($fixed, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . base_path();
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function project_url(array $p, bool $absolute = false): string
{
    $path = cfg('pretty_urls', true) ? 'p/' . rawurlencode($p['slug']) : 'project.php?slug=' . rawurlencode($p['slug']);
    return $absolute ? base_url() . '/' . $path : url($path);
}

function dir_url(string $dir, bool $absolute = false): string
{
    $path = cfg('pretty_urls', true) ? $dir . '/' : '?dir=' . rawurlencode($dir);
    return $absolute ? base_url() . '/' . $path : url($path);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? (string)filemtime($file) : '1';
    return url('assets/' . $path) . '?v=' . $v;
}

/** CSS для встраивания в страницу: без комментариев и лишних пробелов. Кэшируется в data/ по дате файла. */
function inline_css(string $file): string
{
    $src = APP_ROOT . '/assets/' . $file;
    $cache = dirname((string)cfg('db_path', APP_ROOT . '/data/goflow.sqlite')) . '/' . basename($file) . '.' . filemtime($src) . '.min';
    if (is_file($cache)) return (string)file_get_contents($cache);
    $css = (string)file_get_contents($src);
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);
    $css = str_replace([';}', '</'], ['}', '<\/'], trim($css));
    foreach (glob(dirname($cache) . '/' . basename($file) . '.*.min') ?: [] as $old) @unlink($old);
    @file_put_contents($cache, $css, LOCK_EX);
    return $css;
}

/** Знак GF (без фона), вставляется прямо в разметку. */
function logo_svg(string $class = 'logo__mark'): string
{
    return '<svg class="' . h($class) . '" viewBox="96 165 456 302" aria-hidden="true" focusable="false">'
        . '<defs><mask id="gfm"><polygon points="0,0 640,0 640,640 522,640 0,118" fill="#fff"/><polygon points="218,319 420,250 470,250 470,320 218,320" fill="#000"/></mask></defs>'
        . '<g mask="url(#gfm)" fill="currentColor"><path fill-rule="evenodd" d="M76 320a142 142 0 1 0 284 0a142 142 0 1 0 -284 0Z M130 320a88 88 0 1 0 176 0a88 88 0 1 0 -176 0Z"/><rect x="190" y="320" width="164" height="42"/></g>'
        . '<path fill="currentColor" d="M390 178H540V232H446V296H514V350H446V462H390Z"/>'
        . '<polygon points="118,337 226,446 108,455" fill="#2CDB94"/></svg>';
}

/** Ссылка на регистрацию на платформе с UTM и контекстом анкеты. */
function register_url(array $params = []): string
{
    $base = (string)cfg('register_url', 'https://goflow.godigital.rocks/start');
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $params ? $base . (strpos($base, '?') === false ? '?' : '&') . http_build_query($params) : $base;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_demo(): bool
{
    return (bool)cfg('demo', false);
}

/** Пометка «нужно подтвердить»: видна только в демо-режиме. */
function tbd(string $text): string
{
    return is_demo() ? '<span class="tbd">' . h($text) . '</span>' : '';
}

function fmt_rub($n): string
{
    return number_format((int)$n, 0, ',', "\u{00A0}");
}

/** Публичная конфигурация для фронтенда. */
function public_config(): array
{
    return [
        'demo' => is_demo(),
        'apiApply' => url('api/apply.php'),
        'ymId' => (string)cfg('ym_id', ''),
        'vkPixelId' => (string)cfg('vk_pixel_id', ''),
        'registerUrl' => (string)cfg('register_url', 'https://goflow.godigital.rocks/start'),
        'analyticsAfterConsent' => (bool)cfg('analytics_after_consent', false),
    ];
}
