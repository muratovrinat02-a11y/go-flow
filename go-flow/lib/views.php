<?php
/* Общие куски страниц: head, шапка, подвал, анкета, карточка проекта. */
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
<meta name="theme-color" content="#2A2A2A">
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GO Flow">
<meta property="og:title" content="<?= h($ogTitle) ?>">
<meta property="og:description" content="<?= h($ogDesc) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:image" content="<?= h(base_url() . '/assets/og.png') ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%232A2A2A'/%3E%3Ccircle cx='22' cy='10' r='4' fill='%232CDB94'/%3E%3Cpath d='M8 21h12' stroke='%23F9F8F6' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E">
<style><?= inline_css('styles.css') ?></style>
<?php if (!empty($og['jsonld'])): ?><script type="application/ld+json"><?= json_encode($og['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
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
    <a class="logo" href="<?= h($home) ?>" aria-label="GO Flow">
      <span class="logo__mark" aria-hidden="true"></span>
      <span class="logo__text">GO&nbsp;Flow</span>
      <span class="logo__by">платформа GO&nbsp;Digital</span>
    </a>
    <nav class="nav" aria-label="Разделы">
      <a href="<?= h($a('#projects')) ?>">Проекты</a>
      <a href="<?= h($a('#how')) ?>">Как работает</a>
      <a href="<?= h($a('#criteria')) ?>">Критерии</a>
      <a href="<?= h($a('#faq')) ?>">FAQ</a>
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
      <a class="logo logo--foot" href="<?= h(url('')) ?>"><span class="logo__mark" aria-hidden="true"></span><span class="logo__text">GO&nbsp;Flow</span></a>
      <p>Оператор платформы — <?= h(cfg('operator.name', 'GO Digital')) ?>, <?= h(cfg('operator.ad_name', '')) ?>, ИНН&nbsp;<?= h(cfg('operator.inn', '')) ?> <?= tbd('юрлицо оператора') ?></p>
    </div>
    <ul class="foot__links foot__dirs">
      <?php foreach (DIRECTIONS as $k => $d): ?><li><a href="<?= h(dir_url($k)) ?>">Для <?= h($d['noun']) ?></a></li><?php endforeach; ?>
    </ul>
    <ul class="foot__links">
      <li><a href="<?= h(url('privacy.html')) ?>">Политика обработки персональных данных</a></li>
      <li><a href="<?= h(url('consent.html')) ?>">Согласие на обработку персональных данных</a></li>
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

/** Анкета. $project — если отклик на конкретный проект. */
function view_apply(?array $project = null, string $title = '', string $lead = ''): void
{
    $rate = $project ? project_rate($project) : null;
    ?>
<section class="section section--dark" id="apply">
  <div class="wrap apply">
    <div class="apply__text">
      <p class="eyebrow eyebrow--dark"><?= $project ? 'Отклик на проект' : 'Регистрация' ?></p>
      <h2><?= $title ?: 'Откликнитесь на проект или зарегистрируйтесь — 2&nbsp;минуты' ?></h2>
      <p class="lead lead--dark"><?= $lead ?: 'Шаг 1 — три поля. Результат скоринга придёт в Telegram или на почту.' ?></p>
      <ul class="apply__promise">
        <li>Без звонков до скоринга <?= tbd('подтвердить') ?></li>
        <li>Ставку и стек проекта вы уже видите</li>
        <li>Скоринг проходится один раз — дальше откликаетесь в кабинете</li>
      </ul>
      <a class="link-tg link-tg--dark" href="#" data-tg="final">Пока не готовы? Новые проекты в Telegram →</a>
    </div>

    <div class="formbox" id="formbox">
      <div class="formbox__ctx" id="formCtx"<?= $project ? '' : ' hidden' ?>>
        <span class="formbox__ctx-label">Отклик на</span>
        <b id="formCtxTitle"><?= $project ? h($project['title']) : '' ?></b>
        <span id="formCtxRate"><?= $rate ? h($rate) : '' ?></span>
        <button type="button" class="formbox__ctx-x" id="formCtxClear" aria-label="Убрать проект"<?= $project ? ' hidden' : '' ?>>×</button>
      </div>
      <form id="form" novalidate>
        <input type="hidden" name="project_id" id="fProject" value="<?= $project ? (int)$project['id'] : '' ?>">
        <div class="hp" aria-hidden="true"><label>Сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="progress"><span id="progressLabel">Шаг 1 из 2</span><i><b id="progressBar"></b></i></div>

        <fieldset class="fstep" id="step1">
          <legend class="sr">Шаг 1</legend>
          <label class="field">
            <span>Направление</span>
            <select name="direction" id="fDirection" required>
              <option value="">Выберите</option>
              <?php foreach (DIRECTIONS as $k => $d): ?>
              <option value="<?= h($k) ?>"<?= $project && $project['direction'] === $k ? ' selected' : '' ?>><?= h($d['label']) ?></option>
              <?php endforeach; ?>
              <option value="other">Другое</option>
            </select>
          </label>
          <label class="field">
            <span>Сколько лет на этом стеке</span>
            <select name="years" required>
              <option value="">Выберите</option>
              <option value="lt3">меньше 3</option>
              <option value="3-5">3–5</option>
              <option value="6-8">6–8</option>
              <option value="9+">9 и больше</option>
            </select>
          </label>
          <label class="field">
            <span>Telegram или e-mail</span>
            <input name="contact" type="text" inputmode="email" autocomplete="email" placeholder="@username или name@mail.ru" required>
          </label>
          <label class="check">
            <input type="checkbox" name="consent_pd" required>
            <span>Даю <a href="<?= h(url('consent.html')) ?>" target="_blank">согласие на обработку персональных данных</a> в соответствии с <a href="<?= h(url('privacy.html')) ?>" target="_blank">политикой</a></span>
          </label>
          <label class="check">
            <input type="checkbox" name="consent_ads">
            <span>Хочу получать подборки новых проектов</span>
          </label>
          <p class="ferr" id="err1" role="alert" hidden></p>
          <button class="btn btn--green btn--lg btn--block" type="button" id="next1">Дальше <span aria-hidden="true">→</span></button>
          <p class="fnote">Ещё 8 вопросов на втором шаге — около минуты.</p>
        </fieldset>

        <fieldset class="fstep" id="step2" hidden>
          <legend class="sr">Шаг 2</legend>
          <div class="field">
            <span>Основной стек</span>
            <div class="tagpick" id="stackPick"></div>
            <input name="stack_extra" type="text" placeholder="Что ещё — через запятую">
          </div>
          <div class="field">
            <span>Сколько месяцев за последние 24 вы писали код или вели аналитику на этом стеке руками</span>
            <div class="seg">
              <label><input type="radio" name="handson" value="18-24" required><b>18–24</b></label>
              <label><input type="radio" name="handson" value="12-17"><b>12–17</b></label>
              <label><input type="radio" name="handson" value="lt12"><b>меньше 12</b></label>
            </div>
          </div>
          <div class="field">
            <span>Средний срок на одном месте работы или проекте за последние 5 лет</span>
            <div class="seg">
              <label><input type="radio" name="tenure" value="lt12" required><b>&lt;12 мес</b></label>
              <label><input type="radio" name="tenure" value="12-17"><b>12–17</b></label>
              <label><input type="radio" name="tenure" value="18-35"><b>18–35</b></label>
              <label><input type="radio" name="tenure" value="36+"><b>36+</b></label>
            </div>
          </div>
          <div class="field">
            <span>Как готовы оформить сотрудничество</span>
            <div class="seg seg--wrap">
              <label><input type="radio" name="legal" value="npd_ru" required><b>Самозанятый РФ</b></label>
              <label><input type="radio" name="legal" value="ip_ru"><b>ИП РФ</b></label>
              <label><input type="radio" name="legal" value="other"><b>Другое</b></label>
              <label><input type="radio" name="legal" value="none"><b>Пока не оформлен</b></label>
            </div>
          </div>
          <div class="field">
            <span>Сколько часов в день готовы пересекаться с 10:00–19:00 МСК</span>
            <div class="seg seg--wrap">
              <label><input type="radio" name="overlap" value="full" required><b>Весь день</b></label>
              <label><input type="radio" name="overlap" value="6+"><b>6+ ч</b></label>
              <label><input type="radio" name="overlap" value="4-5"><b>4–5 ч</b></label>
              <label><input type="radio" name="overlap" value="lt4"><b>меньше 4 ч</b></label>
            </div>
          </div>
          <div class="frow">
            <label class="field">
              <span>Ожидаемая ставка, ₽/час</span>
              <input name="rate" type="number" inputmode="numeric" min="0" step="100" placeholder="например, 4500" required>
            </label>
            <div class="field">
              <span>Занятость</span>
              <div class="seg seg--wrap">
                <label><input type="radio" name="load" value="full" required><b>Полная</b></label>
                <label><input type="radio" name="load" value="20-30"><b>20–30 ч/нед</b></label>
                <label><input type="radio" name="load" value="lt20"><b>&lt;20 ч/нед</b></label>
              </div>
            </div>
          </div>
          <label class="field">
            <span>Ссылка на резюме</span>
            <input name="resume" type="url" inputmode="url" placeholder="hh.ru, LinkedIn, GitHub или PDF" required>
          </label>
          <p class="ferr" id="err2" role="alert" hidden></p>
          <div class="factions">
            <button class="btn btn--ghost" type="button" id="back2">Назад</button>
            <button class="btn btn--green btn--lg" type="submit" id="submit2">Отправить анкету</button>
          </div>
        </fieldset>
      </form>

      <div class="result" id="resPass" hidden>
        <span class="result__icon result__icon--ok" aria-hidden="true"></span>
        <h3>Готово — доступ к кабинету открыт</h3>
        <p>Анкета подходит под критерии пилота. В кабинете GO&nbsp;Flow — подборка проектов под ваш стек, откликаться можно в один клик<span id="passProject"></span>.</p>
        <a class="btn btn--green btn--lg btn--block" href="#" id="cabinetLink" data-cta="cabinet">Войти в кабинет GO Flow →</a>
        <p class="fnote">Ссылка для входа продублируется в контакт, который вы указали. <?= tbd('механика входа — см. бриф') ?></p>
      </div>
      <div class="result" id="resBorder" hidden>
        <span class="result__icon result__icon--wait" aria-hidden="true"></span>
        <h3>Спасибо, анкета получена</h3>
        <p>Посмотрим её вручную и ответим за <?= is_demo() ? tbd('N рабочих дней') : 'несколько рабочих дней' ?> в контакт, который вы указали.</p>
        <a class="btn btn--ghost btn--block" href="#" data-tg="result_border">Пока — новые проекты в Telegram</a>
      </div>
      <div class="result" id="resFail" hidden>
        <span class="result__icon result__icon--info" aria-hidden="true"></span>
        <h3>Спасибо, что заполнили анкету</h3>
        <p id="failText">Сейчас пилот работает только с теми, кто проходит по критериям. Если критерии расширятся, напишем первыми. А пока запросы с другими требованиями публикуем в Telegram.</p>
        <a class="btn btn--green btn--lg btn--block" href="#" data-tg="result_fail">Подписаться на канал</a>
      </div>
    </div>
  </div>
</section>
<?php
}
