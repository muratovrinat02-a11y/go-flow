<?php
/* Админка GO Flow: проекты, заявки, ссылки для каналов, источники и CPA. */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/auth.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$page = (string)($_GET['page'] ?? 'dashboard');
$self = url('admin/');
function aurl(array $q = []): string { return url('admin/') . ($q ? '?' . http_build_query($q) : ''); }
function redirect(array $q = [], string $flash = ''): void
{
    if ($flash !== '') $_SESSION['flash'] = $flash;
    header('Location: ' . aurl($q));
    exit;
}

/* ---------- вход ---------- */
admin_session();
if ($page === 'logout') { admin_logout(); header('Location: ' . aurl()); exit; }
if (!admin_logged_in()) {
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_check();
        if (login_blocked()) $error = 'Слишком много попыток. Попробуйте через 15 минут.';
        elseif (admin_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) { header('Location: ' . aurl()); exit; }
        else $error = 'Неверный e-mail или пароль.';
    }
    admin_head('Вход');
    ?>
    <main class="login">
      <form class="login__box" method="post" autocomplete="on">
        <div class="login__logo"><?= logo_svg("alogo alogo--dark") ?> GO Flow · админка</div>
        <?= csrf_field() ?>
        <label class="af"><span>E-mail</span><input type="email" name="email" required autofocus autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label class="af"><span>Пароль</span><input type="password" name="password" required autocomplete="current-password"></label>
        <?php if ($error): ?><p class="aerr"><?= h($error) ?></p><?php endif; ?>
        <button class="abtn abtn--primary abtn--block" type="submit">Войти</button>
      </form>
    </main>
    <?php
    echo '</body></html>';
    exit;
}

/* ---------- действия (POST) ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    switch ($action) {
        case 'project_save':
            $id = (int)($_POST['id'] ?? 0) ?: null;
            $data = array_intersect_key($_POST, array_flip(PROJECT_FIELDS));
            $extra = ['sync_lock' => !empty($_POST['sync_lock']) ? 1 : 0];
            if (!$id) $extra['is_example'] = 0;
            if ($id && !empty($_POST['clear_example'])) $extra['is_example'] = 0;
            if (trim((string)($data['role'] ?? '')) === '') redirect(['page' => 'project', 'id' => $id ?: 'new'], 'Укажите роль');
            $newId = project_save($data, $id, $extra);
            redirect(['page' => 'project', 'id' => $newId], 'Проект сохранён');
        case 'project_delete':
            db()->prepare('DELETE FROM projects WHERE id = ?')->execute([(int)$_POST['id']]);
            redirect(['page' => 'projects'], 'Проект удалён');
        case 'project_status':
            $st = (string)$_POST['status'];
            if (isset(PROJECT_STATUSES[$st])) project_save(['status' => $st], (int)$_POST['id']);
            redirect(['page' => 'projects'], 'Статус обновлён');
        case 'sync':
            try { $s = platform_sync(); $msg = "Синхронизация: новых {$s['created']}, обновлено {$s['updated']}, закрыто {$s['closed']}, пропущено {$s['skipped']}"; }
            catch (Throwable $e) { $msg = 'Ошибка синхронизации: ' . $e->getMessage(); }
            redirect(['page' => 'projects'], $msg);
        case 'app_update':
            db()->prepare('UPDATE applications SET notes = ?, updated_at = datetime(\'now\',\'localtime\') WHERE id = ?')
                ->execute([mb_substr((string)$_POST['notes'], 0, 5000), (int)$_POST['id']]);
            redirect(['page' => 'application', 'id' => (int)$_POST['id']], 'Заявка обновлена');
        case 'link_save':
            $src = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_POST['utm_source']));
            if ($src === '') redirect(['page' => 'links'], 'Укажите канал (utm_source)');
            $clean = fn($v) => strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$v));
            $pid = (int)($_POST['project_id'] ?? 0) ?: null;
            $id = (int)($_POST['id'] ?? 0);
            $vals = [
                mb_substr(trim((string)$_POST['label']), 0, 200), $src,
                isset(LINK_MEDIUMS[$_POST['utm_medium'] ?? '']) ? $_POST['utm_medium'] : 'outreach',
                $clean($_POST['utm_campaign'] ?? ''), $clean($_POST['utm_content'] ?? ''), $pid,
                !empty($_POST['paid']) ? 1 : 0, (int)preg_replace('/\D/', '', (string)($_POST['cost'] ?? '0')),
                mb_substr(trim((string)($_POST['erid'] ?? '')), 0, 100),
            ];
            if ($id) {
                db()->prepare('UPDATE links SET label=?, utm_source=?, utm_medium=?, utm_campaign=?, utm_content=?, project_id=?, paid=?, cost=?, erid=? WHERE id=?')
                    ->execute(array_merge($vals, [$id]));
            } else {
                $code = link_code();
                db()->prepare('INSERT INTO links (label, utm_source, utm_medium, utm_campaign, utm_content, project_id, paid, cost, erid, code) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute(array_merge($vals, [$code]));
                $id = (int)db()->lastInsertId();
            }
            redirect(['page' => 'link', 'id' => $id], 'Ссылка сохранена');
        case 'link_delete':
            db()->prepare('DELETE FROM links WHERE id = ?')->execute([(int)$_POST['id']]);
            redirect(['page' => 'links'], 'Ссылка удалена');
    }
    redirect();
}

/* ---------- CSV ---------- */
if ($page === 'applications' && ($_GET['export'] ?? '') === 'csv') {
    [$rows] = applications_query($_GET, 100000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="goflow-applications-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cols = ['id', 'created_at', 'score', 'direction', 'years', 'stack', 'project', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'link_code', 'variant', 'landing_url', 'notes'];
    fputcsv($out, $cols, ';');
    foreach ($rows as $r) {
        $r['stack'] = implode(', ', json_decode($r['stack'] ?: '[]', true) ?: []);
        $r['project'] = $r['project_title'] ?? '';
        fputcsv($out, array_map(fn($c) => $r[$c] ?? '', $cols), ';');
    }
    exit;
}

function applications_query(array $f, int $limit = 200): array
{
    $where = []; $args = [];
    foreach (['score', 'status', 'direction', 'stage', 'utm_source'] as $k) {
        if (($f[$k] ?? '') !== '') {
            if ($k === 'score' && $f[$k] === 'none') { $where[] = 'a.score IS NULL'; continue; }
            $where[] = "a.$k = ?"; $args[] = $f[$k];
        }
    }
    if (($f['project_id'] ?? '') !== '') { $where[] = 'a.project_id = ?'; $args[] = (int)$f['project_id']; }
    if (($f['q'] ?? '') !== '') { $where[] = '(a.stack LIKE ? OR a.notes LIKE ? OR a.utm_campaign LIKE ?)'; $q = '%' . $f['q'] . '%'; array_push($args, $q, $q, $q); }
    if (($f['from'] ?? '') !== '') { $where[] = 'date(a.created_at) >= ?'; $args[] = $f['from']; }
    if (($f['to'] ?? '') !== '') { $where[] = 'date(a.created_at) <= ?'; $args[] = $f['to']; }
    $sql = 'SELECT a.*, TRIM(COALESCE(p.grade,\'\') || \' \' || COALESCE(p.role,\'\')) project_title, p.slug project_slug FROM applications a LEFT JOIN projects p ON p.id = a.project_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.id DESC LIMIT ' . (int)$limit;
    $st = db()->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
    $cnt = db()->prepare('SELECT COUNT(*) FROM applications a' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
    $cnt->execute($args);
    return [$rows, (int)$cnt->fetchColumn()];
}

/* ---------- разметка ---------- */
function admin_head(string $title): void
{
    ?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> — GO Flow админка</title>
<link rel="stylesheet" href="<?= h(asset('admin.css')) ?>">
</head><body><?php
}

const SCORE_LABELS = ['pass' => 'На регистрацию', 'fail' => 'Меньше 2 лет'];
const APP_STATUSES = ['new' => 'Новая', 'review' => 'В работе', 'invited' => 'Приглашён', 'rejected' => 'Отказ', 'archived' => 'Архив'];

function score_pill(?string $s): string
{
    if (!$s) return '<span class="pill pill--muted">шаг 1</span>';
    return '<span class="pill pill--' . h($s) . '">' . h(SCORE_LABELS[$s] ?? $s) . '</span>';
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$nav = ['dashboard' => 'Сводка', 'projects' => 'Проекты', 'applications' => 'Анкеты', 'links' => 'Ссылки для каналов', 'sources' => 'Источники и CPA'];
$navActive = ['project' => 'projects', 'application' => 'applications', 'link' => 'links'][$page] ?? $page;
$newApps = (int)db()->query("SELECT COUNT(*) FROM applications WHERE date(created_at) = date('now','localtime')")->fetchColumn();

admin_head($nav[$navActive] ?? 'Админка');
?>
<div class="shell">
  <aside class="side">
    <a class="side__logo" href="<?= h($self) ?>"><?= logo_svg("alogo") ?>GO Flow</a>
    <nav>
      <?php foreach ($nav as $k => $label): ?>
        <a class="<?= $navActive === $k ? 'is-on' : '' ?>" href="<?= h(aurl($k === 'dashboard' ? [] : ['page' => $k])) ?>"><?= h($label) ?><?php if ($k === 'applications' && $newApps): ?> <b class="badge"><?= $newApps ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <a href="<?= h(url('')) ?>" target="_blank">Открыть лендинг ↗</a>
      <a href="<?= h(aurl(['page' => 'logout'])) ?>">Выйти</a>
    </div>
  </aside>
  <main class="main">
    <?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>
<?php
switch ($page) {

/* ================= СВОДКА ================= */
default:
case 'dashboard':
    $q = fn($sql) => (int)db()->query($sql)->fetchColumn();
    $d7 = "date(created_at) >= date('now','localtime','-6 days')";
    $stats = [
        ['Открытых проектов', $q("SELECT COUNT(*) FROM projects WHERE status='open'")],
        ['Анкет за 7 дней', $q("SELECT COUNT(*) FROM applications WHERE $d7")],
        ['Ушли на регистрацию', $q("SELECT COUNT(*) FROM applications WHERE score='pass' AND $d7")],
        ['Меньше 2 лет опыта', $q("SELECT COUNT(*) FROM applications WHERE score='fail' AND $d7")],
    ];
    $recent = db()->query("SELECT a.*, TRIM(COALESCE(p.grade,'') || ' ' || COALESCE(p.role,'')) project_title FROM applications a LEFT JOIN projects p ON p.id=a.project_id ORDER BY a.id DESC LIMIT 8")->fetchAll();
    $top = array_slice(sources_report(date('Y-m-d', strtotime('-29 days')), date('Y-m-d')), 0, 6);
    ?>
    <div class="ahead"><h1>Сводка</h1><a class="abtn abtn--primary" href="<?= h(aurl(['page' => 'project', 'id' => 'new'])) ?>">+ Новый проект</a></div>
    <div class="kpis"><?php foreach ($stats as [$l, $v]): ?><div class="kpi"><b><?= $v ?></b><span><?= h($l) ?></span></div><?php endforeach; ?></div>
    <p class="hint">Анкета на лендинге — два вопроса (направление и опыт). С опытом от 2 лет человек уходит на регистрацию на платформе goflow.godigital.rocks с UTM-метками. Сама регистрация видна уже в платформе.</p>
    <div class="grid2">
      <section class="card">
        <div class="card__head"><h2>Последние анкеты</h2><a href="<?= h(aurl(['page' => 'applications'])) ?>">Все →</a></div>
        <?php if (!$recent): ?><p class="muted">Анкет пока нет.</p><?php else: ?>
        <table class="t"><tbody>
          <?php foreach ($recent as $r): ?>
          <tr onclick="location='<?= h(aurl(['page' => 'application', 'id' => $r['id']])) ?>'">
            <td><?= score_pill($r['score']) ?></td>
            <td><b><?= h(role_label($r['direction'])) ?> · <?= h(answer_label('years', $r['years'])) ?></b><?= $r['project_title'] ? '<div class="muted">' . h($r['project_title']) . '</div>' : '' ?></td>
            <td class="muted nowrap"><?= h($r['utm_source'] ?: '—') ?></td>
            <td class="muted nowrap"><?= h(date('d.m H:i', strtotime($r['created_at']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
      </section>
      <section class="card">
        <div class="card__head"><h2>Источники за 30 дней</h2><a href="<?= h(aurl(['page' => 'sources'])) ?>">Отчёт →</a></div>
        <?php if (!$top): ?><p class="muted">Данных пока нет. Создайте <a href="<?= h(aurl(['page' => 'links'])) ?>">ссылку для канала</a>.</p><?php else: ?>
        <table class="t"><thead><tr><th>Канал</th><th class="r">Клики</th><th class="r">Анкеты</th><th class="r">На регистрацию</th><th class="r">Цена перехода</th></tr></thead><tbody>
          <?php foreach ($top as $r): ?>
          <tr><td><b><?= h($r['src']) ?></b><?= $r['camp'] ? '<div class="muted">' . h($r['camp']) . '</div>' : '' ?></td><td class="r"><?= (int)$r['clicks'] ?></td><td class="r"><?= (int)$r['step1'] ?></td><td class="r"><?= (int)$r['pass'] ?></td><td class="r"><?= $r['cpa'] ? fmt_rub($r['cpa']) . ' ₽' : '—' ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
      </section>
    </div>
    <?php
    break;

/* ================= ПРОЕКТЫ ================= */
case 'projects':
    $list = projects_all();
    ?>
    <div class="ahead">
      <h1>Проекты</h1>
      <div class="ahead__actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sync">
          <button class="abtn" type="submit" <?= cfg('platform.enabled') ? '' : 'disabled title="Включите platform.enabled в config.php"' ?>>⟳ Синхронизировать с платформой</button></form>
        <a class="abtn abtn--primary" href="<?= h(aurl(['page' => 'project', 'id' => 'new'])) ?>">+ Новый проект</a>
      </div>
    </div>
    <?php if (!cfg('platform.enabled')): ?><p class="hint">Синхронизация с платформой GO Flow выключена — проекты ведутся вручную. Когда у платформы появится API, заполните блок <code>platform</code> в config.php: проекты будут подтягиваться автоматически.</p><?php endif; ?>
    <section class="card card--flush">
      <table class="t t--hover">
        <thead><tr><th>Статус</th><th>Проект</th><th>Ставка</th><th>Источник</th><th class="r">Анкеты</th><th>Обновлён</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $p): ?>
          <tr>
            <td>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="project_status"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <select name="status" class="st st--<?= h($p['status']) ?>" onchange="this.form.submit()"><?php foreach (PROJECT_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= $k === $p['status'] ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
              </form>
            </td>
            <td><a class="strong" href="<?= h(aurl(['page' => 'project', 'id' => $p['id']])) ?>"><?= h($p['title']) ?></a>
              <div class="muted"><?= h($p['direction_label']) ?><?= $p['domain'] ? ' · ' . h($p['domain']) : '' ?> · <?= h(implode(', ', array_slice($p['stack_required'], 0, 4))) ?><?php if ($p['is_example']): ?> · <span class="pill pill--warn">пример</span><?php endif; ?></div></td>
            <td class="nowrap"><?= h(project_rate($p) ?? '—') ?></td>
            <td><?= $p['source'] === 'platform' ? '<span class="pill pill--info">платформа</span>' . ($p['sync_lock'] ? ' 🔒' : '') : '<span class="muted">вручную</span>' ?></td>
            <td class="r"><a href="<?= h(aurl(['page' => 'applications', 'project_id' => $p['id']])) ?>"><?= (int)$p['apps'] ?></a></td>
            <td class="muted nowrap"><?= h(date('d.m.Y', strtotime($p['updated_at']))) ?></td>
            <td class="nowrap r"><a class="abtn abtn--sm" href="<?= h(project_url($p)) ?>" target="_blank">↗</a> <a class="abtn abtn--sm" href="<?= h(aurl(['page' => 'links', 'project_id' => $p['id']])) ?>">Ссылка для TG</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7" class="muted">Проектов нет.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
    <?php
    break;

case 'project':
    $isNew = ($_GET['id'] ?? '') === 'new';
    $p = $isNew ? null : project_find((int)($_GET['id'] ?? 0));
    if (!$isNew && !$p) { echo '<p>Проект не найден.</p>'; break; }
    $v = function (string $k, $def = '') use ($p) {
        if (!$p) return $def;
        $x = $p[$k] ?? $def;
        return is_array($x) ? implode(', ', $x) : (string)$x;
    };
    $fromPlatform = $p && $p['source'] === 'platform';
    ?>
    <div class="ahead">
      <h1><?= $isNew ? 'Новый проект' : h($p['title']) ?></h1>
      <?php if ($p): ?><div class="ahead__actions"><a class="abtn" href="<?= h(project_url($p)) ?>" target="_blank">Открыть страницу ↗</a><a class="abtn" href="<?= h(aurl(['page' => 'links', 'project_id' => $p['id']])) ?>">Ссылка для TG</a></div><?php endif; ?>
    </div>
    <?php if ($fromPlatform): ?><p class="hint">Проект пришёл с платформы (id <?= h($p['external_id']) ?>, синхронизирован <?= h($p['synced_at']) ?>). Ручные правки перезапишутся при следующей синхронизации, если не включить «Не перезаписывать».</p><?php endif; ?>
    <?php if ($p && $p['is_example']): ?><p class="hint hint--warn">Это пример карточки из макета. Замените данные на реальные или удалите проект.</p><?php endif; ?>
    <form method="post" class="pform">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="project_save">
      <input type="hidden" name="id" value="<?= $p ? (int)$p['id'] : '' ?>">
      <div class="pform__main">
        <section class="card">
          <h2>Основное</h2>
          <div class="frow3">
            <label class="af"><span>Роль *</span><input name="role" required value="<?= h($v('role')) ?>" placeholder="Backend-разработчик"></label>
            <label class="af"><span>Грейд</span><input name="grade" value="<?= h($v('grade', 'Senior')) ?>"></label>
            <label class="af"><span>Направление</span><select name="direction"><?php foreach (DIRECTIONS as $k => $d): ?><option value="<?= $k ?>"<?= $v('direction') === $k ? ' selected' : '' ?>><?= h($d['label']) ?></option><?php endforeach; ?></select></label>
          </div>
          <div class="frow2">
            <label class="af"><span>Домен / индустрия</span><input name="domain" value="<?= h($v('domain')) ?>" placeholder="Финтех, Ритейл… (компанию не называем)"></label>
            <label class="af"><span>Адрес страницы (slug)</span><input name="slug" value="<?= h($v('slug')) ?>" placeholder="создастся сам"></label>
          </div>
          <label class="af"><span>Коротко о проекте (1–2 предложения, видно в карточке)</span><input name="summary" value="<?= h($v('summary')) ?>" maxlength="200"></label>
          <label class="af"><span>Описание проекта</span><textarea name="description" rows="5"><?= h($v('description')) ?></textarea></label>
        </section>
        <section class="card">
          <h2>Стек</h2>
          <label class="af"><span>Обязательный стек — через запятую</span><input name="stack_required" value="<?= h($v('stack_required')) ?>" placeholder="Python, FastAPI, PostgreSQL, Docker"></label>
          <label class="af"><span>Будет плюсом — через запятую</span><input name="stack_nice" value="<?= h($v('stack_nice')) ?>" placeholder="Kafka, Redis"></label>
        </section>
        <section class="card">
          <h2>Содержание <small>каждый пункт с новой строки</small></h2>
          <label class="af"><span>Задачи</span><textarea name="tasks" rows="5"><?= h($v('tasks')) ?></textarea></label>
          <label class="af"><span>Что важно (требования)</span><textarea name="requirements" rows="5"><?= h($v('requirements')) ?></textarea></label>
          <label class="af"><span>Условия</span><textarea name="conditions" rows="4"><?= h($v('conditions')) ?></textarea></label>
          <label class="af"><span>Этапы отбора</span><textarea name="process" rows="4"><?= h($v('process')) ?></textarea></label>
        </section>
      </div>
      <aside class="pform__side">
        <section class="card">
          <h2>Публикация</h2>
          <label class="af"><span>Статус</span><select name="status"><?php foreach (PROJECT_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= $v('status', 'draft') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
          <label class="af"><span>Порядок (меньше — выше)</span><input name="sort" type="number" value="<?= h($v('sort', '100')) ?>"></label>
          <?php if ($fromPlatform): ?><label class="acheck"><input type="checkbox" name="sync_lock" value="1"<?= $p['sync_lock'] ? ' checked' : '' ?>> Не перезаписывать при синхронизации</label><?php endif; ?>
          <?php if ($p && $p['is_example']): ?><label class="acheck"><input type="checkbox" name="clear_example" value="1"> Снять пометку «пример»</label><?php endif; ?>
          <button class="abtn abtn--primary abtn--block" type="submit">Сохранить</button>
        </section>
        <section class="card">
          <h2>Ставка, ₽/час</h2>
          <div class="frow2">
            <label class="af"><span>От</span><input name="rate_min" inputmode="numeric" value="<?= h($v('rate_min')) ?>"></label>
            <label class="af"><span>До</span><input name="rate_max" inputmode="numeric" value="<?= h($v('rate_max')) ?>"></label>
          </div>
          <label class="af"><span>Комментарий к ставке</span><input name="rate_note" value="<?= h($v('rate_note')) ?>" placeholder="Зависит от результатов интервью"></label>
        </section>
        <section class="card">
          <h2>Формат</h2>
          <label class="af"><span>Формат</span><input name="format" value="<?= h($v('format', 'Удалённо')) ?>"></label>
          <label class="af"><span>Занятость</span><input name="workload" value="<?= h($v('workload')) ?>" placeholder="40 ч/нед"></label>
          <label class="af"><span>Длительность</span><input name="duration" value="<?= h($v('duration')) ?>" placeholder="6 мес с продлением"></label>
          <label class="af"><span>Старт</span><input name="start_date" value="<?= h($v('start_date')) ?>" placeholder="Ноябрь"></label>
          <label class="af"><span>Команда</span><input name="team" value="<?= h($v('team')) ?>" placeholder="5 разработчиков, PM, QA"></label>
        </section>
      </aside>
    </form>
    <?php if ($p): ?>
      <form method="post" class="danger" onsubmit="return confirm('Удалить проект? Заявки останутся, но без привязки к проекту.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="project_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="abtn abtn--danger" type="submit">Удалить проект</button>
      </form>
    <?php endif;
    break;

/* ================= ЗАЯВКИ ================= */
case 'applications':
    [$rows, $total] = applications_query($_GET);
    $projectsAll = projects_all();
    $sources = db()->query("SELECT DISTINCT utm_source FROM applications WHERE utm_source <> '' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
    $sel = function (string $name, array $opts, string $empty) {
        $cur = (string)($_GET[$name] ?? '');
        $o = '<select name="' . $name . '" onchange="this.form.submit()"><option value="">' . h($empty) . '</option>';
        foreach ($opts as $k => $l) $o .= '<option value="' . h((string)$k) . '"' . ((string)$k === $cur ? ' selected' : '') . '>' . h($l) . '</option>';
        return $o . '</select>';
    };
    $dirOpts = array_map(fn($d) => $d['label'], ROLES);
    $projOpts = [];
    foreach ($projectsAll as $p) $projOpts[$p['id']] = $p['title'] . ' (' . $p['direction_label'] . ')';
    ?>
    <div class="ahead"><h1>Анкеты <span class="muted">· <?= $total ?></span></h1>
      <a class="abtn" href="<?= h(aurl(array_merge($_GET, ['page' => 'applications', 'export' => 'csv']))) ?>">Скачать CSV</a></div>
    <form class="filters" method="get">
      <input type="hidden" name="page" value="applications">
      <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Стек, кампания, заметки…">
      <?= $sel('score', SCORE_LABELS, 'Результат: все') ?>
      <?= $sel('direction', $dirOpts, 'Специальность: все') ?>
      <?= $sel('project_id', $projOpts, 'Проект: все') ?>
      <?= $sel('utm_source', array_combine($sources, $sources) ?: [], 'Канал: все') ?>
      <input type="date" name="from" value="<?= h($_GET['from'] ?? '') ?>" onchange="this.form.submit()">
      <input type="date" name="to" value="<?= h($_GET['to'] ?? '') ?>" onchange="this.form.submit()">
      <button class="abtn" type="submit">Найти</button>
      <?php if (count($_GET) > 1): ?><a class="abtn abtn--ghost" href="<?= h(aurl(['page' => 'applications'])) ?>">Сбросить</a><?php endif; ?>
    </form>
    <section class="card card--flush">
      <table class="t t--hover">
        <thead><tr><th>#</th><th>Результат</th><th>Специальность / проект</th><th>Опыт</th><th>Специализация</th><th>Канал</th><th>Дата</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $st = json_decode($r['stack'] ?: '[]', true) ?: []; ?>
          <tr onclick="location='<?= h(aurl(['page' => 'application', 'id' => $r['id']])) ?>'">
            <td class="muted"><?= (int)$r['id'] ?></td>
            <td><?= score_pill($r['score']) ?></td>
            <td><b><?= h(role_label($r['direction'])) ?></b><?= $r['project_title'] ? '<div class="muted">' . h($r['project_title']) . '</div>' : '' ?></td>
            <td class="nowrap"><?= h(answer_label('years', $r['years'])) ?></td>
            <td class="muted"><?= h(implode(', ', array_slice($st, 0, 5))) ?: '—' ?></td>
            <td><?= h($r['utm_source'] ?: '—') ?><?= $r['utm_campaign'] ? '<div class="muted">' . h($r['utm_campaign']) . '</div>' : '' ?></td>
            <td class="muted nowrap"><?= h(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="muted">Ничего не найдено.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
    <?php
    break;

case 'application':
    $st = db()->prepare("SELECT a.*, TRIM(COALESCE(p.grade,'') || ' ' || COALESCE(p.role,'')) project_title, p.id pid FROM applications a LEFT JOIN projects p ON p.id=a.project_id WHERE a.id = ?");
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $a = $st->fetch();
    if (!$a) { echo '<p>Анкета не найдена.</p>'; break; }
    $stack = json_decode($a['stack'] ?: '[]', true) ?: [];
    $rows = [
        'Специальность' => role_label($a['direction']),
        'Опыт по специальности' => answer_label('years', $a['years']),
        'Специализация' => $stack ? implode(', ', $stack) : '—',
        'Результат' => SCORE_LABELS[$a['score']] ?? '—',
        'Дата' => $a['created_at'],
    ];
    $src = [
        'utm_source' => $a['utm_source'], 'utm_medium' => $a['utm_medium'], 'utm_campaign' => $a['utm_campaign'],
        'utm_content' => $a['utm_content'], 'Ссылка' => $a['link_code'], 'Вариант оффера' => $a['variant'],
        'Первая страница' => $a['landing_url'], 'Referrer' => $a['referrer'],
    ];
    ?>
    <div class="ahead"><h1>Анкета #<?= (int)$a['id'] ?> <?= score_pill($a['score']) ?></h1><a class="abtn" href="<?= h(aurl(['page' => 'applications'])) ?>">← Все анкеты</a></div>
    <div class="grid2 grid2--wide">
      <section class="card">
        <?php if ($a['project_title']): ?><p>Проект: <a href="<?= h(aurl(['page' => 'project', 'id' => $a['pid']])) ?>"><?= h($a['project_title']) ?></a></p><?php endif; ?>
        <dl class="dl"><?php foreach ($rows as $k => $val): ?><dt><?= h($k) ?></dt><dd><?= h($val) ?></dd><?php endforeach; ?></dl>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="app_update"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <label class="af"><span>Заметки</span><textarea name="notes" rows="4"><?= h($a['notes']) ?></textarea></label>
          <button class="abtn abtn--primary" type="submit">Сохранить</button>
        </form>
      </section>
      <section class="card">
        <h2>Источник</h2>
        <dl class="dl dl--sm"><?php foreach ($src as $k => $val): if (!$val) continue; ?><dt><?= h($k) ?></dt><dd><?= h($val) ?></dd><?php endforeach; ?></dl>
      </section>
    </div>
    <?php
    break;

/* ================= ССЫЛКИ ================= */
case 'links':
    $pre = (int)($_GET['project_id'] ?? 0);
    $preP = $pre ? project_find($pre) : null;
    $edit = !empty($_GET['edit']) ? db()->query('SELECT * FROM links WHERE id = ' . (int)$_GET['edit'])->fetch() : null;
    $links = db()->query("SELECT l.*, TRIM(COALESCE(p.grade,'') || ' ' || COALESCE(p.role,'')) project_title,
        (SELECT COUNT(*) FROM clicks c WHERE c.link_code = l.code) clicks,
        (SELECT COUNT(DISTINCT visitor) FROM clicks c WHERE c.link_code = l.code) visitors,
        (SELECT COUNT(*) FROM applications a WHERE a.link_code = l.code) apps,
        (SELECT COUNT(*) FROM applications a WHERE a.link_code = l.code AND a.score = 'pass') pass
        FROM links l LEFT JOIN projects p ON p.id = l.project_id ORDER BY l.id DESC")->fetchAll();
    $open = projects_all();
    $f = $edit ?: ['label' => $preP ? $preP['title'] . ' → ' : '', 'utm_source' => '', 'utm_medium' => 'outreach', 'utm_campaign' => $preP ? $preP['direction'] : '', 'utm_content' => $preP ? $preP['slug'] : '', 'project_id' => $pre ?: null, 'paid' => 0, 'cost' => 0, 'erid' => '', 'id' => ''];
    ?>
    <div class="ahead"><h1>Ссылки для каналов</h1></div>
    <p class="hint">Для каждого канала, чата или аутрич-рассылки создайте свою ссылку. Она короткая (<code><?= h(base_url()) ?>/r/abc123</code>), считает клики и передаёт UTM в анкету — в отчёте «Источники и CPA» видно, сколько анкет и прошедших скоринг дал каждый канал и сколько стоил один кандидат.</p>
    <section class="card">
      <h2><?= $edit ? 'Изменить ссылку' : 'Новая ссылка' ?></h2>
      <form method="post" class="lform">
        <?= csrf_field() ?><input type="hidden" name="action" value="link_save"><input type="hidden" name="id" value="<?= h((string)$f['id']) ?>">
        <label class="af"><span>Название (для себя)</span><input name="label" value="<?= h($f['label']) ?>" placeholder="Python-джобс, пост 21.10"></label>
        <label class="af"><span>Канал (utm_source) *</span><input name="utm_source" required value="<?= h($f['utm_source']) ?>" placeholder="tg_python_jobs" pattern="[a-zA-Z0-9_\-]+"></label>
        <label class="af"><span>Тип</span><select name="utm_medium"><?php foreach (LINK_MEDIUMS as $k => $l): ?><option value="<?= $k ?>"<?= $f['utm_medium'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
        <label class="af"><span>Кампания (utm_campaign)</span><input name="utm_campaign" value="<?= h($f['utm_campaign']) ?>" placeholder="python"></label>
        <label class="af"><span>Ведёт на</span><select name="project_id"><option value="">Лендинг</option><?php foreach ($open as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$f['project_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= h($p['title'] . ' · ' . $p['direction_label'] . ($p['status'] !== 'open' ? ' (' . PROJECT_STATUSES[$p['status']] . ')' : '')) ?></option><?php endforeach; ?></select></label>
        <label class="af"><span>utm_content</span><input name="utm_content" value="<?= h($f['utm_content']) ?>" placeholder="post_2110"></label>
        <label class="af"><span>Стоимость размещения, ₽</span><input name="cost" inputmode="numeric" value="<?= h((string)$f['cost']) ?>"></label>
        <label class="acheck"><input type="checkbox" name="paid" value="1"<?= $f['paid'] ? ' checked' : '' ?>> Платное размещение (нужна маркировка)</label>
        <label class="af"><span>erid (для платного)</span><input name="erid" value="<?= h($f['erid']) ?>"></label>
        <div class="lform__btn"><button class="abtn abtn--primary" type="submit"><?= $edit ? 'Сохранить' : 'Создать ссылку' ?></button></div>
      </form>
    </section>
    <section class="card card--flush">
      <table class="t t--hover">
        <thead><tr><th>Ссылка</th><th>Канал / кампания</th><th>Ведёт на</th><th class="r">Клики</th><th class="r">Уник.</th><th class="r">Анкеты</th><th class="r">Проходят</th><th class="r">Расход</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($links as $l): ?>
          <tr>
            <td><a class="strong" href="<?= h(aurl(['page' => 'link', 'id' => $l['id']])) ?>"><?= h($l['label'] ?: $l['code']) ?></a><div class="muted mono"><?= h(link_short_url($l)) ?></div></td>
            <td><?= h($l['utm_source']) ?><div class="muted"><?= h(LINK_MEDIUMS[$l['utm_medium']] ?? $l['utm_medium']) ?><?= $l['utm_campaign'] ? ' · ' . h($l['utm_campaign']) : '' ?></div></td>
            <td><?= $l['project_title'] ? h($l['project_title']) : '<span class="muted">Лендинг</span>' ?></td>
            <td class="r"><?= (int)$l['clicks'] ?></td><td class="r"><?= (int)$l['visitors'] ?></td><td class="r"><?= (int)$l['apps'] ?></td><td class="r"><?= (int)$l['pass'] ?></td>
            <td class="r nowrap"><?= $l['cost'] ? fmt_rub($l['cost']) . ' ₽' : '—' ?></td>
            <td class="nowrap r"><a class="abtn abtn--sm" href="<?= h(aurl(['page' => 'link', 'id' => $l['id']])) ?>">Пост</a> <a class="abtn abtn--sm" href="<?= h(aurl(['page' => 'links', 'edit' => $l['id']])) ?>">✎</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$links): ?><tr><td colspan="9" class="muted">Ссылок пока нет.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
    <?php
    break;

case 'link':
    $l = db()->query('SELECT * FROM links WHERE id = ' . (int)($_GET['id'] ?? 0))->fetch();
    if (!$l) { echo '<p>Ссылка не найдена.</p>'; break; }
    $short = link_short_url($l);
    $full = link_target_url($l);
    $p = $l['project_id'] ? project_find((int)$l['project_id']) : null;
    $post = $p ? tg_post_text($p, $short, $l) : "🔹 Проекты для senior-специалистов: Python, Go, .NET, React, Angular, аналитика\nСтавка в ₽/час и стек видны до отклика.\n\nСмотреть проекты и откликнуться — 2 минуты:\n" . $short
        . ($l['paid'] ? "\n\nРеклама. " . cfg('operator.ad_name') . ', ИНН ' . cfg('operator.inn') . '. erid: ' . ($l['erid'] ?: '______') : '');
    $clicks = (int)db()->query("SELECT COUNT(*) FROM clicks WHERE link_code = " . db()->quote($l['code']))->fetchColumn();
    ?>
    <div class="ahead"><h1><?= h($l['label'] ?: $l['code']) ?></h1><div class="ahead__actions"><a class="abtn" href="<?= h(aurl(['page' => 'links', 'edit' => $l['id']])) ?>">Изменить</a><a class="abtn" href="<?= h(aurl(['page' => 'links'])) ?>">← Все ссылки</a></div></div>
    <div class="grid2 grid2--wide">
      <section class="card">
        <h2>Текст поста для Telegram</h2>
        <textarea class="post" id="post" rows="16"><?= h($post) ?></textarea>
        <div class="row"><button class="abtn abtn--primary" type="button" data-copy="#post">Скопировать текст</button><span class="muted">Отредактируйте под канал перед публикацией.</span></div>
        <?php if ($l['paid'] && !$l['erid']): ?><p class="hint hint--warn">Платное размещение без erid публиковать нельзя: получите токен в ОРД и добавьте его в ссылку.</p><?php endif; ?>
      </section>
      <section class="card">
        <h2>Ссылки</h2>
        <label class="af"><span>Короткая (считает клики)</span><div class="copy"><input id="short" readonly value="<?= h($short) ?>"><button class="abtn" type="button" data-copy="#short">Копировать</button></div></label>
        <label class="af"><span>Полная с UTM (без счётчика кликов)</span><div class="copy"><input id="full" readonly value="<?= h($full) ?>"><button class="abtn" type="button" data-copy="#full">Копировать</button></div></label>
        <dl class="dl dl--sm"><dt>Клики</dt><dd><?= $clicks ?></dd><dt>Канал</dt><dd><?= h($l['utm_source']) ?></dd><dt>Тип</dt><dd><?= h(LINK_MEDIUMS[$l['utm_medium']] ?? '') ?></dd><dt>Ведёт на</dt><dd><?= $p ? '<a href="' . h(project_url($p)) . '" target="_blank">' . h($p['title']) . '</a>' : 'Лендинг' ?></dd></dl>
        <form method="post" onsubmit="return confirm('Удалить ссылку? Клики по ней перестанут считаться.')"><?= csrf_field() ?><input type="hidden" name="action" value="link_delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="abtn abtn--danger abtn--sm" type="submit">Удалить ссылку</button></form>
      </section>
    </div>
    <?php
    break;

/* ================= ИСТОЧНИКИ ================= */
case 'sources':
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
    $rep = sources_report($from, $to);
    $tot = ['clicks' => 0, 'visitors' => 0, 'step1' => 0, 'pass' => 0, 'fail' => 0, 'cost' => 0];
    foreach ($rep as $r) foreach ($tot as $k => $_) $tot[$k] += (int)$r[$k];
    $money = fn($v) => $v ? fmt_rub($v) . ' ₽' : '—';
    ?>
    <div class="ahead"><h1>Источники и CPA</h1>
      <form class="filters" method="get"><input type="hidden" name="page" value="sources">
        <input type="date" name="from" value="<?= h($from) ?>"><input type="date" name="to" value="<?= h($to) ?>"><button class="abtn" type="submit">Показать</button></form></div>
    <p class="hint">Клики — по коротким ссылкам. Анкеты — по UTM, с которыми человек пришёл впервые за сессию. «На регистрацию» — ушли на страницу регистрации специалиста на платформе. Цена анкеты = расход / анкеты, цена перехода = расход / ушедшие на регистрацию. Завершённые регистрации считаются в платформе: она получает те же UTM.</p>
    <section class="card card--flush">
      <table class="t">
        <thead><tr><th>Канал</th><th>Кампания</th><th class="r">Клики</th><th class="r">Уник.</th><th class="r">Анкеты</th><th class="r">На регистрацию</th><th class="r">Меньше 2 лет</th><th class="r">Расход</th><th class="r">Цена анкеты</th><th class="r">Цена перехода</th></tr></thead>
        <tbody>
        <?php foreach ($rep as $r): ?>
          <tr>
            <td><a class="strong" href="<?= h(aurl(['page' => 'applications', 'utm_source' => $r['src'] === '(прямой / без UTM)' ? '' : $r['src'], 'from' => $from, 'to' => $to])) ?>"><?= h($r['src']) ?></a></td>
            <td class="muted"><?= h($r['camp'] ?: '—') ?></td>
            <td class="r"><?= (int)$r['clicks'] ?></td><td class="r"><?= (int)$r['visitors'] ?></td><td class="r"><?= (int)$r['step1'] ?></td>
            <td class="r strong"><?= (int)$r['pass'] ?></td><td class="r"><?= (int)$r['fail'] ?></td>
            <td class="r nowrap"><?= $money($r['cost']) ?></td>
            <td class="r nowrap"><?= $money($r['cpl']) ?></td>
            <td class="r nowrap strong"><?= $money($r['cpa']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rep): ?><tr><td colspan="10" class="muted">За период данных нет.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($rep): ?><tfoot><tr><td colspan="2">Итого</td><td class="r"><?= $tot['clicks'] ?></td><td class="r"><?= $tot['visitors'] ?></td><td class="r"><?= $tot['step1'] ?></td><td class="r"><?= $tot['pass'] ?></td><td class="r"><?= $tot['fail'] ?></td><td class="r nowrap"><?= $money($tot['cost']) ?></td><td class="r nowrap"><?= $money(($tot['cost'] && $tot['step1']) ? (int)round($tot['cost'] / $tot['step1']) : 0) ?></td><td class="r nowrap"><?= $money(($tot['cost'] && $tot['pass']) ? (int)round($tot['cost'] / $tot['pass']) : 0) ?></td></tr></tfoot><?php endif; ?>
      </table>
    </section>
    <?php
    break;
}
?>
  </main>
</div>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-copy]');
  if (!b) return;
  var el = document.querySelector(b.getAttribute('data-copy'));
  el.select();
  (navigator.clipboard ? navigator.clipboard.writeText(el.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
  var t = b.textContent; b.textContent = 'Скопировано ✓'; setTimeout(function () { b.textContent = t; }, 1500);
});
</script>
</body></html>
