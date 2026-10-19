<?php
/* Приём анкеты: шаг 1 создаёт заявку, шаг 2 дополняет её по токену. */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/notify.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'method'], 405);
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) json_out(['ok' => false, 'error' => 'bad_json'], 400);
if (!empty($in['website'])) json_out(['ok' => true, 'token' => bin2hex(random_bytes(8))]); // honeypot

$s = fn($k, $max = 255) => isset($in[$k]) ? mb_substr(trim((string)$in[$k]), 0, $max) : null;
$enum = function ($k, array $allowed) use ($in) { $v = (string)($in[$k] ?? ''); return in_array($v, $allowed, true) ? $v : null; };

$stage = ($in['stage'] ?? '') === 'step2' ? 'step2' : 'step1';
$row = [
    'stage' => $stage,
    'direction' => $enum('direction', array_merge(array_keys(DIRECTIONS), ['other'])),
    'years' => $enum('years', ['lt3', '3-5', '6-8', '9+']),
    'contact' => $s('contact', 120),
    'consent_pd' => !empty($in['consent_pd']) ? 1 : 0,
    'consent_ads' => !empty($in['consent_ads']) ? 1 : 0,
];
if (!$row['consent_pd']) json_out(['ok' => false, 'error' => 'consent'], 422);
if (!$row['direction'] || !$row['years'] || !$row['contact']) json_out(['ok' => false, 'error' => 'required'], 422);

if ($stage === 'step2') {
    $stack = array_slice(array_values(array_filter(array_map(fn($v) => mb_substr(trim((string)$v), 0, 40), (array)($in['stack'] ?? [])))), 0, 30);
    $row += [
        'stack' => json_encode($stack, JSON_UNESCAPED_UNICODE),
        'handson' => $enum('handson', ['18-24', '12-17', 'lt12']),
        'tenure' => $enum('tenure', ['lt12', '12-17', '18-35', '36+']),
        'legal' => $enum('legal', ['npd_ru', 'ip_ru', 'other', 'none']),
        'overlap' => $enum('overlap', ['full', '6+', '4-5', 'lt4']),
        'rate' => ($r = (int)preg_replace('/\D/', '', (string)($in['rate'] ?? ''))) > 0 ? min($r, 100000) : null,
        'workload' => $enum('load', ['full', '20-30', 'lt20']),
        'resume' => $s('resume', 500),
    ];
}

$pid = (int)($in['project_id'] ?? 0);
if ($pid && !project_find($pid)) $pid = 0;
$row['project_id'] = $pid ?: null;
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'referrer', 'landing_url', 'variant', 'page_dir'] as $k) {
    $row[$k] = $s($k, 500);
}
$row['link_code'] = $s('lc', 16);
$row['user_agent'] = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);

[$score, $reasons] = score_application($row);
$row['score'] = $score ?: null;
$row['score_reasons'] = json_encode($reasons, JSON_UNESCAPED_UNICODE);

$pdo = db();
$token = preg_replace('/[^a-f0-9]/', '', (string)($in['token'] ?? ''));
$existing = null;
if ($token !== '') {
    $st = $pdo->prepare('SELECT id, project_id, utm_source, utm_medium, utm_campaign, utm_content, utm_term, link_code FROM applications WHERE token = ?');
    $st->execute([$token]);
    $existing = $st->fetch() ?: null;
}

if ($existing) {
    // Источник фиксируем по первому шагу: на втором не перетираем пустыми значениями.
    foreach (['project_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'link_code'] as $k) {
        if (empty($row[$k]) && !empty($existing[$k])) unset($row[$k]);
    }
    $row['updated_at'] = date('Y-m-d H:i:s');
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($row)));
    $st = $pdo->prepare("UPDATE applications SET $set WHERE id = :id");
    $params = [];
    foreach ($row as $k => $v) $params[":$k"] = $v;
    $params[':id'] = $existing['id'];
    $st->execute($params);
    $id = (int)$existing['id'];
} else {
    $token = bin2hex(random_bytes(16));
    $row['token'] = $token;
    $cols = implode(', ', array_keys($row));
    $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($row)));
    $st = $pdo->prepare("INSERT INTO applications ($cols) VALUES ($ph)");
    $params = [];
    foreach ($row as $k => $v) $params[":$k"] = $v;
    $st->execute($params);
    $id = (int)$pdo->lastInsertId();
}

if ($stage === 'step2' || $score === 'fail') notify_application($id);

$cabinet = null;
if ($stage === 'step2' && $score === 'pass') $cabinet = platform_register($id);

json_out(['ok' => true, 'token' => $token, 'score' => $score ?: null, 'cabinetUrl' => $cabinet]);
