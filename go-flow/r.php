<?php
/* Короткая ссылка для каналов: считает клик и ведёт на лендинг или проект с UTM. */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
$code = preg_replace('/[^a-z0-9]/', '', strtolower((string)($_GET['c'] ?? '')));
$l = $code !== '' ? link_find($code) : null;
if (!$l) { header('Location: ' . url(''), true, 302); exit; }
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
// Превью-боты Telegram, VK и т.п. не считаем кликами.
if (!preg_match('/TelegramBot|vkShare|facebookexternalhit|Twitterbot|bot|crawler|spider/i', $ua)) link_track_click($code);
header('Cache-Control: no-store');
header('Location: ' . link_target_url($l, false), true, 302);
