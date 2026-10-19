<?php
/* Карта сайта для поисковиков: лендинг, направления, открытые проекты. */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$urls = [[base_url() . '/', date('Y-m-d'), '1.0']];
foreach (array_keys(DIRECTIONS) as $d) $urls[] = [dir_url($d, true), date('Y-m-d'), '0.8'];
foreach (projects_open() as $p) $urls[] = [project_url($p, true), date('Y-m-d', strtotime($p['updated_at'])), '0.7'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $prio]) echo '  <url><loc>' . h($loc) . '</loc><lastmod>' . $mod . '</lastmod><priority>' . $prio . "</priority></url>\n";
echo '</urlset>';
