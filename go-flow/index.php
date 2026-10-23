<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/views.php';

$projects = projects_open();
$count = count($projects);

// Направление: /python/ (или ?dir=python) — отдельные страницы для SEO и рекламы.
$dirKey = strtolower((string)($_GET['dir'] ?? ''));
if (!isset(DIRECTIONS[$dirKey])) $dirKey = '';
$dir = $dirKey ? DIRECTIONS[$dirKey] : null;

// Вариант оффера для A/B: ?v=a|b|c, иначе случайно A или B и запоминаем в cookie.
$variant = strtolower((string)($_GET['v'] ?? ($_COOKIE['gf_v'] ?? '')));
if (!in_array($variant, ['a', 'b', 'c'], true)) $variant = random_int(0, 1) ? 'a' : 'b';
setcookie('gf_v', $variant, ['expires' => time() + 86400 * 60, 'path' => base_path() ?: '/', 'samesite' => 'Lax']);

$noun = $dir ? 'senior ' . $dir['noun'] : 'senior-разработчиков и аналитиков';
$offers = [
    'a' => ['Проекты для ' . h($noun) . ' <span class="hl">у крупных компаний</span>',
            'Компании публикуют проектные запросы, вы выбираете подходящие. Ставка, стек и задачи видны сразу — без «обсудим позже».'],
    'b' => [$dir ? 'Проекты для ' . h($noun) . ' — <span class="hl">только под ваш стек</span>' : 'Только проекты <span class="hl">под ваш стек</span>',
            'Отметьте стек — и смотрите запросы, где он совпадает. Подтверждаете опыт один раз и откликаетесь туда, где условия подходят.'],
    'c' => ['Заказчики <span class="hl">приглашают вас</span> на проекты сами',
            'Подтвердите опыт, укажите стек и ставку. Компании увидят профиль с отметкой «подтверждённый опыт» и пришлют приглашение.'],
];
[$h1, $sub] = $offers[$variant];

$dirCounts = [];
foreach ($projects as $p) $dirCounts[$p['direction']] = ($dirCounts[$p['direction']] ?? 0) + 1;

function plural(int $n, string $one, string $few, string $many): string
{
    $n10 = $n % 10; $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return $one;
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) return $few;
    return $many;
}
$countText = $count . ' ' . plural($count, 'открытый проект', 'открытых проекта', 'открытых проектов');
$trust = cfg('trust', []);
$rate = cfg('hero.rate', '3 000');

$canonical = base_url() . '/' . ($dirKey ? $dirKey . '/' : '');
$seoTitle = $dir
    ? 'Проекты для senior ' . $dir['noun'] . ' — удалённо, до ' . $rate . ' ₽/час | GO Flow'
    : 'GO Flow — проекты для senior-разработчиков и аналитиков, до ' . $rate . ' ₽/час';
$seoDesc = $dir
    ? 'Проекты для senior ' . $dir['noun'] . ' у крупных компаний: ' . implode(', ', array_slice($dir['stack'], 0, 4)) . '. До ' . $rate . ' ₽/час, старт за ' . cfg('hero.start', '1–3 недели') . '.'
    : 'Проекты у крупных компаний для senior-специалистов: Python, Go, .NET, React, Angular, аналитика. До ' . $rate . ' ₽/час, старт за ' . cfg('hero.start', '1–3 недели') . '.';
view_head($seoTitle, $seoDesc, ['url' => $canonical]);
view_header(true);
?>
<main>

<!-- 1. Первый экран -->
<section class="hero">
  <div class="wrap hero__in">
    <div class="hero__text">
      <p class="live"><span class="live__dot" aria-hidden="true"></span>Платформа GO&nbsp;Flow · <a href="#projects"><?= h($countText) ?></a></p>
      <h1><?= $h1 ?></h1>
      <p class="hero__sub"><?= $sub ?></p>
      <?php view_facts('facts--hero'); ?>

      <div class="picker" role="group" aria-label="Ваш стек">
        <span class="picker__label">Ваш стек:</span>
        <?php foreach (DIRECTIONS as $k => $d): ?>
          <a class="picker__btn<?= $k === $dirKey ? ' is-on' : '' ?>" href="<?= h(dir_url($k)) ?>" data-pick-dir="<?= h($k) ?>"><?= h($d['label']) ?><?php if (!empty($dirCounts[$k])): ?><sup><?= (int)$dirCounts[$k] ?></sup><?php endif; ?></a>
        <?php endforeach; ?>
      </div>

      <div class="hero__cta">
        <a class="btn btn--green btn--lg" href="#projects" data-cta="hero_projects">Смотреть проекты<?= $count ? ' <span class="btn__count">' . $count . '</span>' : '' ?></a>
        <a class="btn btn--ghost btn--lg" href="#apply" data-cta="hero_register">Зарегистрироваться</a>
      </div>
    </div>

    <aside class="trustcard" aria-label="Кто стоит за платформой">
      <div class="trustcard__head"><?= logo_svg('trustcard__logo') ?><div><b>Платформа GO&nbsp;Digital</b><span>IT-аутстаффинг с 2019 года</span></div></div>
      <ul class="trustcard__list">
        <?php if (!empty($trust['rating'])): ?><li><span class="ti ti--star" aria-hidden="true"></span><a href="<?= h($trust['rating_url'] ?? 'https://ratingruneta.ru/') ?>" target="_blank" rel="noopener"><?= h($trust['rating']) ?></a></li><?php endif; ?>
        <?php if (!empty($trust['accredited'])): ?><li><span class="ti ti--check" aria-hidden="true"></span>Аккредитованная IT-компания</li><?php endif; ?>
        <?php if (!empty($trust['skolkovo'])): ?><li><span class="ti ti--check" aria-hidden="true"></span>Участник «Сколково»</li><?php endif; ?>
        <li><span class="ti ti--check" aria-hidden="true"></span>Отчёты, документы и выплаты — в кабинете платформы</li>
      </ul>
      <?php if (!empty($trust['clients'])): ?>
        <p class="trustcard__label">Компании, с которыми работает GO&nbsp;Digital</p>
        <ul class="clients clients--dark"><?php foreach ($trust['clients'] as $c): ?><li><?= h($c) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </aside>
  </div>
</section>

<!-- 2. Проекты -->
<section class="section section--paper2" id="projects">
  <div class="wrap">
    <p class="eyebrow">Открытые проекты</p>
    <h2>Посмотрите, что есть под ваш стек — прямо сейчас</h2>
    <p class="lead">Название заказчика раскрываем после отклика. Ставка, стек, задачи и формат — как в кабинете платформы.</p>

    <div class="filters" id="filters" role="tablist" aria-label="Направление"></div>
    <div class="stackbar" id="stackbar">
      <span class="stackbar__label">Отметьте свой стек — покажем совпадение:</span>
      <div class="stackbar__chips" id="stackChips"></div>
    </div>
    <div class="cards" id="cards"></div>
    <div class="cards__empty" id="cardsEmpty" hidden>
      <p>По этому направлению сейчас нет открытых запросов. Зарегистрируйтесь — новые запросы появятся в кабинете платформы.</p>
    </div>
    <div class="cards__after">
      <p>Не нашли подходящий проект? Зарегистрируйтесь: новые запросы появляются в кабинете каждые несколько минут.</p>
      <a class="btn btn--green" href="#apply" data-cta="after_cards">Зарегистрироваться</a>
    </div>
  </div>
</section>

<!-- 3. Как это работает -->
<section class="section" id="how">
  <div class="wrap">
    <p class="eyebrow">Как это работает</p>
    <h2>От регистрации до первой выплаты — шесть шагов</h2>
    <ol class="steps steps--6">
      <li class="step"><span class="step__n">01</span><h3>Регистрация</h3><p>Отмечаете специальность и опыт здесь, затем регистрируетесь на платформе по e-mail или телефону. Открытые проекты видны сразу.</p></li>
      <li class="step"><span class="step__n">02</span><h3>Подтверждение опыта</h3><p>Загружаете резюме и документы, менеджер верификации их проверяет. От 1 года в крупной IT-компании — откликаться можно сразу, иначе — техническая встреча с экспертом GO&nbsp;Digital.</p></li>
      <li class="step"><span class="step__n">03</span><h3>Отклик и ассесмент</h3><p>Откликаетесь на проект или принимаете приглашение заказчика и проходите ассесмент — знакомство и техническую встречу.</p></li>
      <li class="step"><span class="step__n">04</span><h3>Условия и оформление</h3><p>Согласуете ставку, занятость и сроки. Нет самозанятости или ИП — оформляете на этом шаге: самозанятость регистрируется онлайн в приложении «Мой налог».</p></li>
      <li class="step"><span class="step__n">05</span><h3>Работа и отчёты</h3><p>Работаете над проектом и загружаете отчёты о часах в кабинет платформы.</p></li>
      <li class="step"><span class="step__n">06</span><h3>Документы и выплата</h3><p>Подписываете закрывающие документы и получаете оплату — раз в месяц. Если в проекте другой график, например поквартальный, он указан в запросе.</p></li>
    </ol>
  </div>
</section>

<!-- 4. Требования -->
<section class="section section--dark" id="criteria">
  <div class="wrap crit">
    <div>
      <p class="eyebrow eyebrow--dark">Для кого</p>
      <h2>Платформа для опытных специалистов. Поэтому заказчики приходят за вами</h2>
      <p class="lead lead--dark">Заказчик видит на профиле отметку «подтверждённый опыт» и сколько обязательных условий запроса вы закрываете. Поэтому приглашения приходят по делу.</p>
      <ul class="dirs" aria-label="Направления">
        <?php foreach (DIRECTIONS as $k => $d): ?><li><a href="<?= h(dir_url($k)) ?>"><?= h($d['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="crit__box">
      <h3>Что нужно для старта</h3>
      <ul class="checks">
        <li>От 2 лет коммерческого опыта по специальности</li>
        <li>Самостоятельно пишете код или ведёте анализ</li>
        <li>Опыт подтверждается документами: электронная трудовая, сканы трудовой или договоров</li>
        <li>Опыт в домене проекта — если он указан в карточке</li>
        <li>Статус самозанятого или ИП — можно оформить после согласования условий</li>
      </ul>
      <a class="btn btn--green btn--block crit__cta" href="#apply" data-cta="criteria">Подхожу — зарегистрироваться</a>
    </div>
  </div>
</section>

<!-- 5. Что получаете -->
<section class="section" id="benefits">
  <div class="wrap">
    <p class="eyebrow">Что вы получаете</p>
    <h2>Проекты на понятных условиях</h2>
    <?php view_facts('facts--light'); ?>
    <div class="bens">
      <div class="ben"><h3>Ставка видна сразу</h3><p>Ставка в ₽/час указана в каждом запросе. Бюджет известен до первой встречи с заказчиком.</p></div>
      <div class="ben"><h3>Проекты под ваш стек</h3><p>Видите запросы, где стек совпадает, и сколько требований закрываете: «совпадает 3 из 4».</p></div>
      <div class="ben"><h3>Опыт подтверждается один раз</h3><p>После проверки документов на профиле появляется отметка «подтверждённый опыт» — её видят все заказчики.</p></div>
      <div class="ben"><h3>Заказчики приходят сами</h3><p>Компании видят профиль и присылают приглашения на проекты во вкладку «Отклики и приглашения».</p></div>
      <div class="ben"><h3>Путь в крупную компанию</h3><p>Проекты долгосрочные, и бывает, что заказчик по итогам предлагает перейти к нему в штат. Решение — за вами и заказчиком.</p></div>
      <div class="ben"><h3>Выплаты раз в месяц</h3><p>Отчёты о часах и закрывающие документы — в кабинете платформы. Если у проекта другой график оплаты, он указан в запросе.</p></div>
    </div>
  </div>
</section>

<!-- 6. О платформе -->
<section class="section section--paper2" id="operator">
  <div class="wrap op">
    <div>
      <p class="eyebrow">Кто стоит за платформой</p>
      <h2>GO&nbsp;Flow — платформа компании GO&nbsp;Digital</h2>
      <p class="lead">GO&nbsp;Digital с 2019 года работает в IT-аутстаффинге с крупными компаниями. GO&nbsp;Flow — её платформа, где заказчики публикуют проектные запросы, а специалисты откликаются на них напрямую.</p>
      <ul class="badges-light">
        <?php if (!empty($trust['rating'])): ?><li><?= h($trust['rating']) ?></li><?php endif; ?>
        <?php if (!empty($trust['accredited'])): ?><li>Аккредитованная IT-компания</li><?php endif; ?>
        <?php if (!empty($trust['skolkovo'])): ?><li>Участник «Сколково»</li><?php endif; ?>
      </ul>
      <p class="op__src">Цифры — с сайта <a href="https://godigital.rocks/about" target="_blank" rel="noopener">godigital.rocks</a></p>
    </div>
    <dl class="stats">
      <div><dt>с 2019</dt><dd>в IT-аутстаффинге</dd></div>
      <div><dt>300+</dt><dd>специалистов на проектах</dd></div>
      <div><dt>100+</dt><dd>реализованных проектов</dd></div>
      <div><dt>2 года</dt><dd>в среднем длится сотрудничество с клиентом</dd></div>
    </dl>
  </div>
  <?php if (!empty($trust['clients'])): ?>
  <div class="wrap">
    <p class="clients__label">Компании, с которыми работает GO&nbsp;Digital</p>
    <ul class="clients"><?php foreach ($trust['clients'] as $c): ?><li><?= h($c) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
</section>

<!-- 7. FAQ -->
<section class="section" id="faq">
  <div class="wrap faq">
    <div><p class="eyebrow">Вопросы</p><h2>Частые вопросы</h2></div>
    <div class="faq__list">
      <details><summary>Я стану сотрудником GO&nbsp;Digital?</summary><p>Нет. GO&nbsp;Flow — платформа проектов: вы работаете над проектами заказчиков как самозанятый или ИП, без трудовых отношений с GO&nbsp;Digital.</p></details>
      <details><summary>А в штат заказчика перейти можно?</summary><p>Бывает, что по итогам проекта заказчик предлагает специалисту перейти к нему в штат. Такой переход оформляется трёхсторонним соглашением между вами, заказчиком и платформой.</p></details>
      <details><summary>Какая ставка на проектах?</summary><p>До <?= h($rate) ?>&nbsp;₽/час. Ставка указана в карточке каждого проекта. При полной загрузке — до <?= h(cfg('hero.month', '500 000')) ?>&nbsp;₽ в месяц. Налог самозанятого или ИП вы платите сами.</p></details>
      <details><summary>Какая комиссия у платформы?</summary><p>Зависит от проекта, обычно от 0 до 5%.</p></details>
      <details><summary>Как и когда платят?</summary><p>Раз в месяц: загружаете отчёт о часах, подписываете закрывающие документы и получаете оплату. В некоторых проектах оплата поквартальная — это всегда указано в запросе.</p></details>
      <details><summary>Как быстро можно начать?</summary><p>Обычно от регистрации до старта проекта проходит <?= h(cfg('hero.start', '1–3 недели')) ?>: регистрация, подтверждение опыта, ассесмент и согласование условий.</p></details>
      <details><summary>Какой опыт нужен и как он подтверждается?</summary>
        <p>Нужно минимум 2 года коммерческого опыта по специальности — в штате или по договору с любой компанией. После регистрации вы загружаете резюме и документы, менеджер верификации проверяет каждое место работы отдельно.</p>
        <p>Если у вас от 1 года опыта в крупной IT-компании, откликаться на проекты можно сразу. Если нет — сначала техническая встреча с экспертом GO&nbsp;Digital.</p>
        <p>Какие документы подходят:</p>
        <ul>
          <li>электронная трудовая книжка с Госуслуг, подписанная ЭЦП, — допуск автоматический;</li>
          <li>сканы бумажной трудовой книжки;</li>
          <li>договоры об оказании услуг по специальности, подписанные ЭЦП, — если работали как самозанятый или ИП.</li>
        </ul></details>
      <details><summary>Что если не всё в резюме получается подтвердить?</summary><p>Если подтверждённого опыта хватает для критериев выше, мы вас допускаем. Места работы, которые не удалось подтвердить документами, в профиле будут отмечены для заказчика как неподтверждённые.</p></details>
      <details><summary>Видит ли заказчик мои документы?</summary><p>Нет. Заказчик видит только результат верификации — отметку «подтверждённый опыт» и статус проверки. Документы, если нужно, вы передаёте заказчику сами.</p></details>
      <details><summary>Как обрабатываются мои документы и данные?</summary><p>В соответствии с законодательством РФ: законами о персональных данных (152-ФЗ), об информации и защите информации (149-ФЗ) и об электронной подписи (63-ФЗ). Документы используются только для проверки опыта, доступ к ним есть у менеджеров верификации, заказчикам и третьим лицам они не передаются.</p></details>
      <details><summary>У меня нет ИП или самозанятости</summary><p>Можно оформить уже после того, как согласуете условия с заказчиком. Самозанятость регистрируется онлайн в приложении «Мой налог», ИП — через Госуслуги, обычно за 1 день.</p></details>
      <details><summary>Какая занятость на проектах?</summary><p>В карточке указана занятость проекта: полная или частичная, часов в неделю. Свои ограничения вы задаёте в разделе «Ограничения».</p></details>
      <details><summary>Можно совмещать с основной работой или другими проектами?</summary><p>Да, если занятость проекта и ваши ограничения совпадают. Учитывайте условия вашего текущего договора.</p></details>
      <details><summary>Что если по моей специальности сейчас нет проектов?</summary><p>Зарегистрируйтесь и подтвердите опыт: новые запросы появляются в кабинете каждые несколько минут, а заказчики смогут пригласить вас сами.</p></details>
      <details><summary>У меня меньше 2 лет опыта</summary><p>Сейчас проекты на платформе рассчитаны на специалистов с коммерческим опытом от 2 лет по специальности. Возвращайтесь, когда наберёте опыт.</p></details>
    </div>
  </div>
</section>

<?php view_apply(null); ?>

</main>
<?php view_footer(); ?>

<div class="sticky" id="sticky" aria-hidden="true">
  <a class="btn btn--green btn--block" href="#projects" data-cta="sticky" tabindex="-1" id="stickyBtn">Смотреть проекты<?= $count ? ' · ' . $count : '' ?></a>
</div>
<?php
view_boot([
    'type' => 'landing',
    'variant' => $variant,
    'dir' => $dirKey,
    'projects' => array_map('project_public', $projects),
]);
