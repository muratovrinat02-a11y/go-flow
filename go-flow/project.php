<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/views.php';

$slug = (string)($_GET['slug'] ?? '');
$p = $slug !== '' ? project_by_slug($slug) : null;
if (!$p || $p['status'] === 'draft') {
    http_response_code(404);
    view_head('Проект не найден — GO Flow', 'Проект не найден');
    view_header(false);
    echo '<main class="section"><div class="wrap"><h1>Проект не найден</h1><p class="lead">Возможно, ссылка устарела. Посмотрите <a href="' . h(url('#projects')) . '">открытые проекты</a>.</p></div></main>';
    view_footer();
    view_boot(['type' => 'notfound', 'projects' => []]);
    exit;
}

$isOpen = $p['status'] === 'open';
$rate = project_rate($p);
$others = array_values(array_filter(projects_open(), fn($x) => (int)$x['id'] !== (int)$p['id']));
usort($others, fn($a, $b) => ($b['direction'] === $p['direction']) <=> ($a['direction'] === $p['direction']));
$others = array_slice($others, 0, 3);

$ogTitle = $p['title'] . ($rate ? ' · ' . str_replace("\u{00A0}", ' ', $rate) : '');
$ogDesc = trim(implode(' · ', array_filter([
    $p['stack_required'] ? implode(', ', $p['stack_required']) : '',
    $p['format'], $p['workload'], $p['duration'],
])) . '. Отклик за 2 минуты на GO Flow.');

view_head($ogTitle . ' — GO Flow', $ogDesc, ['title' => $ogTitle, 'description' => $ogDesc, 'url' => project_url($p, true)]);
view_header(false);

$meta = array_filter([
    'Формат' => $p['format'],
    'Занятость' => $p['workload'],
    'Длительность' => $p['duration'],
    'Старт' => $p['start_date'],
    'Команда' => $p['team'],
    'Направление' => $p['direction_label'],
]);
$sections = [
    'Задачи' => $p['tasks_list'],
    'Что важно' => $p['requirements_list'],
    'Условия' => $p['conditions_list'],
];
?>
<main>
<section class="phero">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="<?= h(url('')) ?>">Все проекты</a> / <a href="<?= h(dir_url($p['direction'])) ?>"><?= h($p['direction_label']) ?></a></nav>
    <?php if (!$isOpen): ?>
      <div class="phero__closed">Набор на этот проект закрыт. Ниже — похожие открытые проекты.</div>
    <?php endif; ?>
    <div class="phero__grid">
      <div>
        <div class="phero__tags">
          <?php if ($p['is_example']): ?><span class="tag tag--warn">Пример карточки</span><?php endif; ?>
          <?php if ($p['domain']): ?><span class="tag"><?= h($p['domain']) ?></span><?php endif; ?>
          <span class="tag"><?= h($p['direction_label']) ?></span>
        </div>
        <h1><?= h($p['title']) ?></h1>
        <?php if ($p['summary']): ?><p class="phero__sub"><?= h($p['summary']) ?></p><?php endif; ?>
        <div class="stack stack--lg">
          <?php foreach ($p['stack_required'] as $s): ?><span class="chip chip--dark"><?= h($s) ?></span><?php endforeach; ?>
          <?php foreach ($p['stack_nice'] as $s): ?><span class="chip chip--dark chip--nice" title="Будет плюсом">+ <?= h($s) ?></span><?php endforeach; ?>
        </div>
        <dl class="pmeta">
          <?php foreach ($meta as $k => $v): ?><div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?>
        </dl>
      </div>
      <aside class="pbox">
        <div class="pbox__rate"><?= $rate ? h($rate) : 'Ставка по запросу ' . tbd('уточнить') ?></div>
        <?php if ($p['is_example'] && $rate) echo tbd('ставка из примера'); ?>
        <?php if ($p['rate_note']): ?><p class="pbox__note"><?= h($p['rate_note']) ?></p><?php endif; ?>
        <?php if ($isOpen): ?>
          <a class="btn btn--green btn--lg btn--block" href="#apply" data-cta="project_hero">Откликнуться</a>
          <p class="pbox__hint">Два вопроса — и регистрация на платформе. Старт проекта за <?= h(cfg('hero.start', '1–3 недели')) ?>.</p>
        <?php else: ?>
          <a class="btn btn--green btn--lg btn--block" href="#apply" data-cta="project_closed">Зарегистрироваться</a>
        <?php endif; ?>
        <?php if ($p['published_at']): ?><p class="pbox__date">Опубликован <?= h(date('d.m.Y', strtotime($p['published_at']))) ?></p><?php endif; ?>
      </aside>
    </div>
  </div>
</section>

<section class="section pbody">
  <div class="wrap pbody__grid">
    <div class="pbody__main">
      <?php if ($p['description']): ?>
        <h2 class="h3">О проекте</h2>
        <div class="prose"><?= nl2br(h($p['description'])) ?></div>
      <?php endif; ?>
      <?php foreach ($sections as $title => $items): if (!$items) continue; ?>
        <h2 class="h3"><?= h($title) ?></h2>
        <ul class="blist"><?php foreach ($items as $it): ?><li><?= h($it) ?></li><?php endforeach; ?></ul>
      <?php endforeach; ?>
      <?php if ($p['process_list']): ?>
        <h2 class="h3">Как стартует проект</h2>
        <ol class="proc"><?php foreach ($p['process_list'] as $i => $it): ?><li><b><?= $i + 1 ?></b><span><?= h($it) ?></span></li><?php endforeach; ?></ol>
      <?php endif; ?>
    </div>
    <aside class="pbody__side">
      <div class="match" id="matchBox">
        <h3>Совпадает ли ваш стек?</h3>
        <p>Отметьте, с чем работаете руками:</p>
        <div class="tagpick tagpick--light" id="matchPick"></div>
        <p class="match__res" id="matchRes"></p>
      </div>
    </aside>
  </div>
</section>

<?php view_apply($isOpen ? $p : null); ?>

<?php if ($others): ?>
<section class="section section--paper2">
  <div class="wrap">
    <p class="eyebrow">Ещё проекты</p>
    <h2>Похожие открытые проекты</h2>
    <div class="cards" id="cards"></div>
  </div>
</section>
<?php endif; ?>
</main>
<?php view_footer(); ?>

<div class="sticky" id="sticky" aria-hidden="true">
  <a class="btn btn--green btn--block" href="#apply" data-cta="sticky_project" tabindex="-1">Откликнуться<?= $rate ? ' · ' . h($rate) : '' ?></a>
</div>
<?php
view_boot([
    'type' => 'project',
    'project' => project_public($p) + ['stackAll' => array_values(array_unique(array_merge($p['stack_required'], $p['stack_nice'])))],
    'projects' => array_map('project_public', $others),
]);
