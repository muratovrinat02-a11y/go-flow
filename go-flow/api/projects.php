<?php
/* Открытые проекты в JSON — для виджетов, бота, внешних витрин. */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
header('Access-Control-Allow-Origin: *');
$items = array_map(function ($p) { $x = project_public($p); $x['url'] = project_url($p, true); return $x; }, projects_open());
$dir = (string)($_GET['dir'] ?? '');
if ($dir !== '') $items = array_values(array_filter($items, fn($p) => $p['direction'] === $dir));
json_out(['items' => $items, 'count' => count($items)]);
