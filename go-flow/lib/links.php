<?php
/* Трекинг-ссылки для каналов и отчёт по источникам. */
declare(strict_types=1);

const LINK_MEDIUMS = [
    'outreach' => 'Личный аутрич',
    'channel'  => 'Свой канал',
    'seeding'  => 'Посев (платный)',
    'chat'     => 'Чат',
    'cpc'      => 'Таргет / контекст',
    'social'   => 'Соцсети',
];

function link_code(): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $st = db()->prepare('SELECT 1 FROM links WHERE code = ?');
        $st->execute([$code]);
    } while ($st->fetch());
    return $code;
}

function link_find(string $code): ?array
{
    $st = db()->prepare('SELECT * FROM links WHERE code = ?');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function link_short_url(array $l): string
{
    return base_url() . '/' . (cfg('pretty_urls', true) ? 'r/' . $l['code'] : 'r.php?c=' . $l['code']);
}

/** Куда ведёт ссылка, со всеми UTM. */
function link_target_url(array $l, bool $absolute = true): string
{
    $p = $l['project_id'] ? project_find((int)$l['project_id']) : null;
    $base = $p ? project_url($p, $absolute) : ($absolute ? base_url() . '/' : url(''));
    $q = array_filter([
        'utm_source' => $l['utm_source'], 'utm_medium' => $l['utm_medium'],
        'utm_campaign' => $l['utm_campaign'], 'utm_content' => $l['utm_content'],
        'lc' => $l['code'],
    ], fn($v) => $v !== '' && $v !== null);
    return $base . (strpos($base, '?') === false ? '?' : '&') . http_build_query($q);
}

function link_track_click(string $code): void
{
    $visitor = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Y-m-d')), 0, 16);
    $st = db()->prepare('INSERT INTO clicks (link_code, visitor) VALUES (?, ?)');
    $st->execute([$code, $visitor]);
}

/** Текст поста для Telegram по проекту. */
function tg_post_text(array $p, string $link, array $l = []): string
{
    $lines = [];
    $rate = project_rate($p);
    $lines[] = '🔹 ' . $p['title'] . ($rate ? ' · ' . str_replace("\u{00A0}", ' ', $rate) : '');
    if ($p['domain']) $lines[] = 'Домен: ' . $p['domain'];
    if ($p['stack_required']) $lines[] = 'Стек: ' . implode(', ', $p['stack_required']);
    $meta = array_filter([$p['format'], $p['workload'], $p['duration']]);
    if ($meta) $lines[] = 'Формат: ' . implode(' · ', $meta);
    if ($p['tasks_list']) {
        $lines[] = '';
        $lines[] = 'Задачи:';
        foreach (array_slice($p['tasks_list'], 0, 3) as $t) $lines[] = '— ' . $t;
    }
    $lines[] = '';
    $lines[] = 'Ставка и задачи — по ссылке. Регистрация и отклик:';
    $lines[] = $link;
    if (!empty($l['paid'])) {
        $lines[] = '';
        $lines[] = 'Реклама. ' . cfg('operator.ad_name', 'ООО «…»') . ', ИНН ' . cfg('operator.inn', '…') . '. erid: ' . ($l['erid'] ?: '______');
    }
    return implode("\n", $lines);
}

/**
 * Отчёт по источникам: клики, анкеты, скоринг, расход, CPL/CPA.
 * Группировка по utm_source + utm_campaign. Расход — сумма cost по ссылкам этой пары.
 */
function sources_report(string $from, string $to): array
{
    $pdo = db();
    $st = $pdo->prepare("SELECT COALESCE(NULLIF(utm_source,''),'(прямой / без UTM)') src, COALESCE(utm_campaign,'') camp,
            COUNT(*) step1,
            SUM(stage = 'step2') step2,
            SUM(score = 'pass') pass,
            SUM(score = 'border') border,
            SUM(score = 'fail') fail
        FROM applications WHERE date(created_at) BETWEEN ? AND ? GROUP BY src, camp");
    $st->execute([$from, $to]);
    $rows = [];
    foreach ($st->fetchAll() as $r) $rows[$r['src'] . '|' . $r['camp']] = $r + ['clicks' => 0, 'visitors' => 0, 'cost' => 0];

    $st = $pdo->prepare("SELECT l.utm_source src, l.utm_campaign camp, COUNT(c.id) clicks, COUNT(DISTINCT c.visitor) visitors
        FROM links l LEFT JOIN clicks c ON c.link_code = l.code AND date(c.created_at) BETWEEN ? AND ?
        GROUP BY src, camp");
    $st->execute([$from, $to]);
    foreach ($st->fetchAll() as $r) {
        $k = $r['src'] . '|' . $r['camp'];
        $rows[$k] = ($rows[$k] ?? ['src' => $r['src'], 'camp' => $r['camp'], 'step1' => 0, 'step2' => 0, 'pass' => 0, 'border' => 0, 'fail' => 0, 'cost' => 0]);
        $rows[$k]['clicks'] = (int)$r['clicks'];
        $rows[$k]['visitors'] = (int)$r['visitors'];
    }
    // Расход считаем целиком по ссылке, без разбивки по датам: стоимость размещения фиксированная.
    foreach ($pdo->query('SELECT utm_source src, utm_campaign camp, SUM(cost) cost FROM links GROUP BY src, camp')->fetchAll() as $r) {
        $k = $r['src'] . '|' . $r['camp'];
        if (isset($rows[$k])) $rows[$k]['cost'] = (int)$r['cost'];
    }
    foreach ($rows as &$r) {
        $r['cpl'] = ($r['cost'] && $r['step1']) ? (int)round($r['cost'] / $r['step1']) : null;
        $r['cpa'] = ($r['cost'] && $r['pass']) ? (int)round($r['cost'] / $r['pass']) : null;
        $r['cr'] = $r['visitors'] ? round($r['step1'] / $r['visitors'] * 100, 1) : null;
    }
    unset($r);
    uasort($rows, fn($a, $b) => [$b['pass'], $b['step2'], $b['clicks']] <=> [$a['pass'], $a['step2'], $a['clicks']]);
    return array_values($rows);
}
