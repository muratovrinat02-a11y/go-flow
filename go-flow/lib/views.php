<?php
/* Общие куски страниц: head, шапка, подвал, анкета. */
declare(strict_types=1);

function view_head(string $title, string $desc, array $og = []): void
{
    $ogTitle = $og['title'] ?? $title;
    $ogDesc = $og['description'] ?? $desc;
    $canonical = $og['url'] ?? base_url() . '/';
    ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($desc) ?>">
<meta name="theme-color" content="#202020">
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GO Flow">
<meta property="og:title" content="<?= h($ogTitle) ?>">
<meta property="og:description" content="<?= h($ogDesc) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:image" content="<?= h(base_url() . '/assets/og.png') ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= h(asset('favicon.svg')) ?>" type="image/svg+xml">
<style><?= inline_css('styles.css') ?></style>
</head>
<body class="<?= is_demo() ? 'is-demo' : '' ?>">
<?php if (is_demo()): ?>
<div class="demo-bar"><div class="wrap">Макет. Значения с пометкой <span class="tbd">так</span> нужно подтвердить до запуска.</div></div>
<?php endif;
}

function view_header(bool $isLanding = true): void
{
    $home = url('');
    $a = fn($hash) => $isLanding ? $hash : $home . $hash;
    ?>
<header class="top" id="top">
  <div class="wrap top__in">
    <a class="logo" href="<?= h($home) ?>" aria-label="GO Flow — на главную">
      <?= logo_svg() ?>
      <span class="logo__text">GO&nbsp;Flow</span>
      <span class="logo__by">платформа GO&nbsp;Digital</span>
    </a>
    <nav class="nav" aria-label="Разделы">
      <a href="<?= h($a('#projects')) ?>">Проекты</a>
      <a href="<?= h($a('#how')) ?>">Как это работает</a>
      <a href="<?= h($a('#operator')) ?>">О платформе</a>
      <a href="<?= h($a('#faq')) ?>">Вопросы</a>
    </nav>
    <a class="btn btn--sm btn--green" href="#apply" data-cta="header">Зарегистрироваться</a>
  </div>
</header>
<?php
}

function view_footer(): void
{
    ?>
<footer class="foot">
  <div class="wrap foot__in">
    <div>
      <a class="logo logo--foot" href="<?= h(url('')) ?>"><?= logo_svg() ?><span class="logo__text">GO&nbsp;Flow</span></a>
      <p>Платформа проектов для IT-специалистов. Оператор — <?= h(cfg('operator.name', 'GO Digital')) ?>, <?= h(cfg('operator.ad_name', '')) ?>, ИНН&nbsp;<?= h(cfg('operator.inn', '')) ?></p>
    </div>
    <ul class="foot__links foot__dirs">
      <?php foreach (DIRECTIONS as $k => $d): ?><li><a href="<?= h(dir_url($k)) ?>">Проекты для <?= h($d['noun']) ?></a></li><?php endforeach; ?>
    </ul>
    <ul class="foot__links">
      <li><a href="<?= h(url('privacy.html')) ?>">Политика обработки персональных данных</a></li>
      <li><a href="https://godigital.rocks/" target="_blank" rel="noopener">Вы компания? Разместить запрос →</a></li>
    </ul>
  </div>
</footer>

<div class="cookie" id="cookie" role="dialog" aria-label="Cookie" hidden>
  <p>Сайт использует cookie и Яндекс Метрику, чтобы понимать, какие разделы полезны. <a href="<?= h(url('privacy.html')) ?>">Подробнее</a></p>
  <button class="btn btn--sm btn--green" type="button" id="cookieOk">Понятно</button>
</div>
<?php
}

/** Данные для app.js. */
function view_boot(array $page): void
{
    $dirs = [];
    foreach (DIRECTIONS as $k => $d) $dirs[$k] = ['label' => $d['label'], 'stack' => $d['stack']];
    $data = ['config' => public_config(), 'dirs' => $dirs, 'page' => $page];
    echo '<script>window.GF=' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n";
    echo '<script src="' . h(asset('app.js')) . '" defer></script>' . "\n</body>\n</html>";
}

/** Цифры первого экрана: ставка и три УТП. */
function view_facts(string $class = ''): void
{
    $hero = cfg('hero', []);
    ?>
<div class="facts <?= h($class) ?>">
  <div class="facts__rate"><span>до</span> <?= h($hero['rate'] ?? '3 000') ?>&nbsp;₽<small>/час</small></div>
  <ul class="facts__list">
    <li><b>до <?= h($hero['month'] ?? '450 000') ?>&nbsp;₽/мес</b><span>при полной загрузке</span></li>
    <li><b>старт за <?= h($hero['start'] ?? '1–3 недели') ?></b><span>от регистрации до проекта</span></li>
    <li><b>долгосрочные проекты</b><span>у крупных компаний</span></li>
  </ul>
</div>
<?php
}

/** Анкета: стек и опыт → регистрация на платформе. $project — если человек откликается на конкретный проект. */
function view_apply(?array $project = null): void
{
    $rate = $project ? project_rate($project) : null;
    $dirSel = $project['direction'] ?? '';
    ?>
<section class="section section--dark" id="apply">
  <div class="wrap apply">
    <div class="apply__text">
      <p class="eyebrow eyebrow--dark"><?= $project ? 'Отклик на проект' : 'Регистрация' ?></p>
      <h2><?= $project ? 'Откликнуться на «' . h($project['title']) . '»' : 'Зарегистрируйтесь и откликайтесь на проекты' ?></h2>
      <p class="lead lead--dark">Два вопроса — и переходите к регистрации на платформе GO&nbsp;Flow.</p>
      <ol class="apply__steps">
        <li><b>Стек и опыт</b><span>здесь, 15 секунд</span></li>
        <li><b>Регистрация</b><span>e-mail или телефон с подтверждением</span></li>
        <li><b>Резюме и документы</b><span>подтверждаем опыт один раз</span></li>
        <li><b>Отклик и ассесмент</b><span>знакомство с заказчиком</span></li>
        <li><b>Старт проекта</b><span>за <?= h(cfg('hero.start', '1–3 недели')) ?>, выплаты раз в месяц</span></li>
      </ol>
    </div>

    <div class="formbox" id="formbox">
      <div class="formbox__ctx" id="formCtx"<?= $project ? '' : ' hidden' ?>>
        <span class="formbox__ctx-label">Проект</span>
        <b id="formCtxTitle"><?= $project ? h($project['title']) : '' ?></b>
        <span id="formCtxRate"><?= $rate ? h($rate) : '' ?></span>
        <button type="button" class="formbox__ctx-x" id="formCtxClear" aria-label="Убрать проект"<?= $project ? ' hidden' : '' ?>>×</button>
      </div>
      <form id="form" novalidate>
        <input type="hidden" name="project_id" id="fProject" value="<?= $project ? (int)$project['id'] : '' ?>">
        <div class="hp" aria-hidden="true"><label>Сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="field">
          <span>Ваше направление</span>
          <div class="seg seg--dirs" id="fDirs">
            <?php foreach (DIRECTIONS as $k => $d): ?>
              <label><input type="radio" name="direction" value="<?= h($k) ?>" required<?= $dirSel === $k ? ' checked' : '' ?>><b><?= h($d['label']) ?></b></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field" id="stackField"<?= $dirSel ? '' : ' hidden' ?>>
          <span>Основной стек <small>— по желанию</small></span>
          <div class="tagpick" id="stackPick"></div>
        </div>
        <div class="field">
          <span>Сколько лет коммерческого опыта на этом стеке</span>
          <div class="seg">
            <?php foreach (YEARS_OPTIONS as $k => $l): ?>
              <label><input type="radio" name="years" value="<?= h($k) ?>" required><b><?= h($l) ?></b></label>
            <?php endforeach; ?>
          </div>
        </div>
        <p class="ferr" id="err" role="alert" hidden></p>
        <button class="btn btn--green btn--lg btn--block" type="submit" id="submitBtn">Перейти к регистрации <span aria-hidden="true">→</span></button>
        <p class="fnote">Регистрация на платформе GO&nbsp;Flow — goflow.godigital.rocks</p>
      </form>

      <div class="result" id="resLow" hidden>
        <span class="result__icon result__icon--info" aria-hidden="true"></span>
        <h3>Пока рано — но мы будем рады вам позже</h3>
        <p>Сейчас проекты на платформе рассчитаны на специалистов с коммерческим опытом от 2 лет на основном стеке. Возвращайтесь, когда наберёте опыт: проекты будут здесь.</p>
        <button class="btn btn--ghost btn--block" type="button" id="resBack">Изменить ответ</button>
      </div>
      <div class="result" id="resGo" hidden>
        <span class="result__icon result__icon--ok" aria-hidden="true"></span>
        <h3>Переходим к регистрации…</h3>
        <p>Если страница не открылась, <a id="goLink" href="<?= h(register_url()) ?>">нажмите здесь</a>.</p>
      </div>
    </div>
  </div>
</section>
<?php
}
