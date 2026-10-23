<?php
/* Проекты: справочник направлений, чтение, сохранение, синхронизация с платформой. */
declare(strict_types=1);

const DIRECTIONS = [
    'python'  => ['role' => 'dev', 'label' => 'Python',             'noun' => 'Python-разработчиков',  'stack' => ['Python', 'FastAPI', 'Django', 'PostgreSQL', 'Docker', 'Kafka', 'Redis', 'Celery']],
    'go'      => ['role' => 'dev', 'label' => 'Go',                 'noun' => 'Go-разработчиков',      'stack' => ['Go', 'PostgreSQL', 'Kafka', 'gRPC', 'Kubernetes', 'Redis', 'ClickHouse']],
    'dotnet'  => ['role' => 'dev', 'label' => '.NET',               'noun' => '.NET-разработчиков',    'stack' => ['C#', '.NET', 'ASP.NET Core', 'EF Core', 'PostgreSQL', 'MS SQL', 'RabbitMQ']],
    'react'   => ['role' => 'dev', 'label' => 'React',              'noun' => 'React-разработчиков',   'stack' => ['React', 'TypeScript', 'Next.js', 'Redux', 'Storybook', 'Jest']],
    'angular' => ['role' => 'dev', 'label' => 'Angular',            'noun' => 'Angular-разработчиков', 'stack' => ['Angular', 'TypeScript', 'RxJS', 'NgRx', 'Jest']],
    'sa'      => ['role' => 'analyst', 'label' => 'Системный аналитик', 'noun' => 'системных аналитиков',  'stack' => ['BPMN', 'UML', 'REST', 'SQL', 'Kafka', 'OpenAPI']],
    'ba'      => ['role' => 'analyst', 'label' => 'Бизнес-аналитик',    'noun' => 'бизнес-аналитиков',     'stack' => ['BPMN', 'User Stories', 'SQL', 'BI', 'CJM']],
];

const PROJECT_STATUSES = ['draft' => 'Черновик', 'open' => 'Открыт', 'paused' => 'На паузе', 'closed' => 'Закрыт'];

/** Поля, которые редактируются в админке и приходят из API платформы. */
const PROJECT_FIELDS = [
    'slug', 'status', 'direction', 'role', 'grade', 'domain', 'summary', 'description',
    'stack_required', 'stack_nice', 'tasks', 'requirements', 'conditions', 'process',
    'format', 'workload', 'duration', 'start_date', 'team', 'rate_min', 'rate_max', 'rate_note', 'sort',
];

function project_decode(array $p): array
{
    $p['stack_required'] = json_decode($p['stack_required'] ?: '[]', true) ?: [];
    $p['stack_nice'] = json_decode($p['stack_nice'] ?: '[]', true) ?: [];
    foreach (['tasks', 'requirements', 'conditions', 'process'] as $k) {
        $p[$k . '_list'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$p[$k]))));
    }
    $p['direction_label'] = DIRECTIONS[$p['direction']]['label'] ?? $p['direction'];
    $p['title'] = ($p['grade'] ? $p['grade'] . ' ' : '') . $p['role'];
    return $p;
}

function projects_open(): array
{
    $rows = db()->query("SELECT * FROM projects WHERE status = 'open' ORDER BY sort, published_at DESC, id DESC")->fetchAll();
    return array_map('project_decode', $rows);
}

function projects_all(): array
{
    $rows = db()->query('SELECT p.*, (SELECT COUNT(*) FROM applications a WHERE a.project_id = p.id) AS apps
        FROM projects p ORDER BY CASE status WHEN \'open\' THEN 0 WHEN \'draft\' THEN 1 WHEN \'paused\' THEN 2 ELSE 3 END, sort, id DESC')->fetchAll();
    return array_map('project_decode', $rows);
}

function project_find(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM projects WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? project_decode($r) : null;
}

function project_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM projects WHERE slug = ?');
    $st->execute([$slug]);
    $r = $st->fetch();
    return $r ? project_decode($r) : null;
}

/** Ставка строкой: «4 000 – 6 000 ₽/час», «до 6 000 ₽/час» или null. */
function project_rate(array $p): ?string
{
    $min = $p['rate_min'] ? (int)$p['rate_min'] : 0;
    $max = $p['rate_max'] ? (int)$p['rate_max'] : 0;
    if ($min && $max) return fmt_rub($min) . "\u{00A0}–\u{00A0}" . fmt_rub($max) . "\u{00A0}₽/час";
    if ($max) return "до\u{00A0}" . fmt_rub($max) . "\u{00A0}₽/час";
    if ($min) return "от\u{00A0}" . fmt_rub($min) . "\u{00A0}₽/час";
    return null;
}

/** Данные проекта для фронтенда. */
function project_public(array $p): array
{
    return [
        'id' => (int)$p['id'], 'slug' => $p['slug'], 'url' => project_url($p),
        'direction' => $p['direction'], 'directionLabel' => $p['direction_label'],
        'role' => $p['role'], 'grade' => $p['grade'], 'title' => $p['title'], 'domain' => $p['domain'],
        'summary' => $p['summary'], 'stack' => $p['stack_required'], 'stackNice' => $p['stack_nice'],
        'tasks' => array_slice($p['tasks_list'], 0, 3), 'format' => $p['format'], 'workload' => $p['workload'],
        'duration' => $p['duration'], 'rate' => project_rate($p), 'rateMin' => $p['rate_min'] ? (int)$p['rate_min'] : null,
        'rateMax' => $p['rate_max'] ? (int)$p['rate_max'] : null, 'isExample' => (bool)$p['is_example'],
        'publishedAt' => $p['published_at'],
    ];
}

/** Диапазон ставок по открытым проектам — для первого экрана. Только реальные данные. */
function rates_range(array $projects): ?array
{
    $vals = [];
    foreach ($projects as $p) {
        if ($p['rate_min']) $vals[] = (int)$p['rate_min'];
        if ($p['rate_max']) $vals[] = (int)$p['rate_max'];
    }
    return $vals ? ['min' => min($vals), 'max' => max($vals), 'hasExamples' => (bool)array_filter($projects, fn($p) => $p['is_example'])] : null;
}

function slugify(string $s): string
{
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = strtr(mb_strtolower($s), $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr($s, 0, 60), '-') ?: 'project';
}

function unique_slug(string $base, ?int $exceptId = null): string
{
    $slug = $base; $i = 2;
    $st = db()->prepare('SELECT id FROM projects WHERE slug = ? AND id IS NOT ?');
    while (true) {
        $st->execute([$slug, $exceptId]);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . $i++;
    }
}

/** Приводит входные данные (форма админки или API) к строке таблицы. */
function project_normalize(array $in): array
{
    $list = function ($v): string {
        if (is_array($v)) $v = implode(',', $v);
        $items = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$v)))));
        return json_encode($items, JSON_UNESCAPED_UNICODE);
    };
    $text = function ($v): string {
        if (is_array($v)) $v = implode("\n", $v);
        return trim(str_replace("\r", '', (string)$v));
    };
    $out = [];
    foreach (PROJECT_FIELDS as $f) {
        if (!array_key_exists($f, $in)) continue;
        $v = $in[$f];
        switch ($f) {
            case 'stack_required': case 'stack_nice': $out[$f] = $list($v); break;
            case 'rate_min': case 'rate_max': $n = (int)preg_replace('/\D/', '', (string)$v); $out[$f] = $n > 0 ? $n : null; break;
            case 'sort': $out[$f] = (int)$v; break;
            case 'status': $out[$f] = isset(PROJECT_STATUSES[$v]) ? $v : 'draft'; break;
            case 'direction': $out[$f] = isset(DIRECTIONS[$v]) ? $v : 'python'; break;
            case 'slug': $out[$f] = trim((string)$v) === '' ? '' : slugify((string)$v); break;
            default: $out[$f] = $text($v);
        }
    }
    return $out;
}

function project_save(array $data, ?int $id = null, array $extra = []): int
{
    $row = project_normalize($data) + $extra;
    if (array_key_exists('slug', $row) && $row['slug'] === '') unset($row['slug']);
    if (!$id && empty($row['slug'])) $row['slug'] = slugify(($row['role'] ?? 'project') . ' ' . ($row['direction'] ?? ''));
    if (isset($row['slug'])) $row['slug'] = unique_slug($row['slug'], $id);
    $pdo = db();
    if ($id) {
        $old = project_find($id);
        if ($old && $old['status'] !== 'open' && ($row['status'] ?? '') === 'open' && empty($old['published_at'])) {
            $row['published_at'] = date('Y-m-d H:i:s');
        }
        $row['updated_at'] = date('Y-m-d H:i:s');
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($row)));
        $st = $pdo->prepare("UPDATE projects SET $set WHERE id = :__id");
        $st->execute(array_combine(array_map(fn($k) => ":$k", array_keys($row)), array_values($row)) + [':__id' => $id]);
        return $id;
    }
    if (($row['status'] ?? '') === 'open') $row['published_at'] = date('Y-m-d H:i:s');
    $cols = implode(', ', array_keys($row));
    $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($row)));
    $st = $pdo->prepare("INSERT INTO projects ($cols) VALUES ($ph)");
    $st->execute(array_combine(array_map(fn($k) => ":$k", array_keys($row)), array_values($row)));
    return (int)$pdo->lastInsertId();
}

/* ============ Синхронизация с платформой GO Flow ============

   Контракт (предложение для команды платформы, см. README):
   GET {platform.url}  Authorization: Bearer {platform.token}
   Ответ: {"items": [ { "id": "req_123", "status": "open", "direction": "python",
       "role": "Backend-разработчик", "grade": "Senior", "domain": "Финтех",
       "summary": "...", "description": "...",
       "stack_required": ["Python","FastAPI"], "stack_nice": ["Kafka"],
       "tasks": ["..."], "requirements": ["..."], "conditions": ["..."], "process": ["..."],
       "format": "Удалённо", "workload": "40 ч/нед", "duration": "6 мес", "start_date": "ноябрь",
       "rate_min": 4000, "rate_max": 6000, "updated_at": "2026-10-19T10:00:00+03:00" } ] }
   Проекты, которых больше нет в ответе, переводятся в статус closed. */

function platform_fetch(): array
{
    $url = (string)cfg('platform.url', '');
    if (!cfg('platform.enabled') || $url === '') throw new RuntimeException('Синхронизация выключена: заполните platform.url и platform.enabled в config.php');
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => (int)cfg('platform.timeout', 15),
        'header' => "Accept: application/json\r\nAuthorization: Bearer " . cfg('platform.token', '') . "\r\n",
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $hdr) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $hdr, $m)) $code = (int)$m[1];
    if ($raw === false || $code >= 400) throw new RuntimeException("Платформа ответила ошибкой (HTTP $code)");
    $json = json_decode($raw, true);
    $items = $json['items'] ?? $json;
    if (!is_array($items)) throw new RuntimeException('Непонятный ответ платформы: нет массива items');
    return $items;
}

/** @return array{created:int, updated:int, closed:int, skipped:int} */
function platform_sync(): array
{
    $items = platform_fetch();
    $pdo = db();
    $stats = ['created' => 0, 'updated' => 0, 'closed' => 0, 'skipped' => 0];
    $seen = [];
    $find = $pdo->prepare("SELECT id, sync_lock FROM projects WHERE source = 'platform' AND external_id = ?");
    $pdo->beginTransaction();
    try {
        foreach ($items as $it) {
            if (empty($it['id']) || empty($it['role'])) { $stats['skipped']++; continue; }
            $ext = (string)$it['id'];
            $seen[] = $ext;
            $data = $it;
            $data['status'] = ($it['status'] ?? 'open') === 'open' ? 'open' : 'closed';
            unset($data['slug']);
            $find->execute([$ext]);
            $row = $find->fetch();
            $now = date('Y-m-d H:i:s');
            if ($row) {
                if ((int)$row['sync_lock']) { $stats['skipped']++; continue; }
                project_save($data, (int)$row['id'], ['synced_at' => $now]);
                $stats['updated']++;
            } else {
                $data['slug'] = slugify(($it['role'] ?? '') . ' ' . ($it['direction'] ?? '') . ' ' . $ext);
                project_save($data, null, ['source' => 'platform', 'external_id' => $ext, 'synced_at' => $now]);
                $stats['created']++;
            }
        }
        $ph = $seen ? implode(',', array_fill(0, count($seen), '?')) : "''";
        $st = $pdo->prepare("UPDATE projects SET status = 'closed', updated_at = datetime('now','localtime')
            WHERE source = 'platform' AND sync_lock = 0 AND status = 'open' AND external_id NOT IN ($ph)");
        $st->execute($seen);
        $stats['closed'] = $st->rowCount();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $stats;
}
