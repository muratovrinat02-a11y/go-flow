<?php
/* Анкета: специальность, специализация, опыт → запись в базу и ссылка на регистрацию на платформе. */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/notify.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'method'], 405);
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) json_out(['ok' => false, 'error' => 'bad_json'], 400);
if (!empty($in['website'])) json_out(['ok' => true, 'score' => 'pass', 'redirect' => register_url()]); // honeypot

$s = fn($k, $max = 255) => isset($in[$k]) ? mb_substr(trim((string)$in[$k]), 0, $max) : null;
$direction = (string)($in['direction'] ?? '');
if (!isset(ROLES[$direction])) json_out(['ok' => false, 'error' => 'direction'], 422);
$years = (string)($in['years'] ?? '');
if (!isset(YEARS_OPTIONS[$years])) json_out(['ok' => false, 'error' => 'years'], 422);

$stack = array_slice(array_values(array_filter(array_map(fn($v) => mb_substr(trim((string)$v), 0, 40), (array)($in['stack'] ?? [])))), 0, 30);
$pid = (int)($in['project_id'] ?? 0);
$project = $pid ? project_find($pid) : null;

$row = [
    'token' => bin2hex(random_bytes(16)),
    'stage' => 'step2',
    'direction' => $direction,
    'years' => $years,
    'stack' => json_encode($stack, JSON_UNESCAPED_UNICODE),
    'project_id' => $project ? (int)$project['id'] : null,
    'link_code' => $s('lc', 16),
    'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'referrer', 'landing_url', 'variant', 'page_dir'] as $k) {
    $row[$k] = $s($k, 500);
}
[$score, $reasons] = score_application($row);
$row['score'] = $score;
$row['score_reasons'] = json_encode($reasons, JSON_UNESCAPED_UNICODE);

$cols = implode(', ', array_keys($row));
$ph = implode(', ', array_map(fn($k) => ":$k", array_keys($row)));
$params = [];
foreach ($row as $k => $v) $params[":$k"] = $v;
db()->prepare("INSERT INTO applications ($cols) VALUES ($ph)")->execute($params);
$id = (int)db()->lastInsertId();

notify_application($id);

$redirect = null;
if ($score === 'pass') {
    $redirect = register_url([
        'utm_source' => $row['utm_source'] ?: 'spec_landing',
        'utm_medium' => $row['utm_medium'], 'utm_campaign' => $row['utm_campaign'],
        'utm_content' => $row['utm_content'], 'utm_term' => $row['utm_term'],
        'ref' => 'spec', 'lead' => $id, 'direction' => $direction, 'years' => $years,
        'project' => $project ? ($project['external_id'] ?: $project['slug']) : null,
    ]);
}
json_out(['ok' => true, 'score' => $score, 'redirect' => $redirect]);
