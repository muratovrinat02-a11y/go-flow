<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/views.php';

$projects = projects_open();
$count = count($projects);
$range = rates_range($projects);

// Направление: ?dir=python и т.п.
$dirKey = strtolower((string)($_GET['dir'] ?? ''));
if (!isset(DIRECTIONS[$dirKey])) $dirKey = '';
$dir = $dirKey ? DIRECTIONS[$dirKey] : null;
$dirProjects = $dirKey ? array_values(array_filter($projects, fn($p) => $p['direction'] === $dirKey)) : $projects;
$dirRange = rates_range($dirProjects) ?: $range;

// Вариант оффера: ?v=a|b|c, иначе случайно A или B и запоминаем в cookie.
$variant = strtolower((string)($_GET['v'] ?? ($_COOKIE['gf_v'] ?? '')));
if (!in_array($variant, ['a', 'b', 'c'], true)) $variant = random_int(0, 1) ? 'a' : 'b';
setcookie('gf_v', $variant, ['expires' => time() + 86400 * 60, 'path' => base_path() ?: '/', 'samesite' => 'Lax']);
if ($variant === 'a' && !$dirRange) $variant = 'b'; // без реальных ставок оффер про ставку не показываем

$noun = $dir ? 'senior ' . $dir['noun'] : 'senior-разработчиков и аналитиков';
$maxRate = $dirRange ? fmt_rub($dirRange['max']) : '';
$rateMark = ($dirRange && $dirRange['hasExamples']) ? tbd('ставка из примера') : '';
$offers = [
    'a' => ['Проекты для ' . h($noun) . ' до&nbsp;<span class="hl">' . $maxRate . '&nbsp;₽/час</span>' . $rateMark,
            'Компании публикуют запросы, GO&nbsp;Flow подбирает их под ваш стек. Ставку, стек и задачи вы видите до отклика — без «обсудим на интервью».'],
    'b' => ['Только проекты <span class="hl">под ваш стек</span>. Без сотни нерелевантных откликов',
            'Один раз проходите проверку опыта. Дальше видите запросы, где стек совпадает, и откликаетесь только туда, где условия подходят.'],
    'c' => ['Заказчики <span class="hl">приглашают вас</span> на проекты сами',
            'Подтвердите опыт, укажите стек и ставку. Компании увидят профиль с отметкой «подтверждённый опыт» и пришлют приглашение.'],
];
[$h1, $sub] = $offers[$variant];
$heroProject = $dirProjects[0] ?? ($projects[0] ?? null);
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

view_head(
    'GO Flow — проекты для senior-специалистов' . ($dir ? ': ' . $dir['label'] : ''),
    'Проектные запросы компаний под ваш стек. Ставка в ₽/час, стек и задачи видны до отклика.'
);
view_header(true);
?>
<main>

<!-- 1. Первый экран -->
<section class="hero">
  <div class="wrap hero__in">
    <div class="hero__text">
      <p class="live"><span class="live__dot" aria-hidden="true"></span>Пилот GO&nbsp;Flow · <a href="#projects"><?= h($countText) ?></a></p>
      <h1><?= $h1 ?></h1>
      <p class="hero__sub"><?= $sub ?></p>

      <div class="picker" role="group" aria-label="Ваш стек">
        <span class="picker__label">Ваш стек:</span>
        <?php foreach (DIRECTIONS as $k => $d): ?>
          <a class="picker__btn<?= $k === $dirKey ? ' is-on' : '' ?>" href="#projects" data-pick-dir="<?= h($k) ?>"><?= h($d['label']) ?><?php if (!empty($dirCounts[$k])): ?><sup><?= (int)$dirCounts[$k] ?></sup><?php endif; ?></a>
        <?php endforeach; ?>
      </div>

      <div class="hero__cta">
        <a class="btn btn--green btn--lg" href="#projects" data-cta="hero_projects">Смотреть проекты<?= $count ? ' <span class="btn__count">' . $count . '</span>' : '' ?></a>
        <a class="btn btn--ghost btn--lg" href="#apply" data-cta="hero_register">Зарегистрироваться</a>
      </div>
      <p class="hero__note"><span class="dot" aria-hidden="true"></span>Это не вакансия в штат GO&nbsp;Digital. Вы работаете на проектах заказчиков, договор и расчёты идут через платформу.</p>
    </div>

    <div class="hero__visual">
      <div class="app" aria-label="Так выглядит подборка в кабинете">
        <div class="app__bar"><i></i><i></i><i></i><span>GO Flow · кабинет специалиста</span></div>
        <div class="app__body">
          <aside class="app__side" aria-hidden="true">
            <b class="is-active">Заявки</b><b>Отчеты</b><b>Резюме</b><b>Ограничения</b>
          </aside>
          <div class="app__main">
            <div class="app__tabs" aria-hidden="true"><span class="is-active">Подобрано для вас</span><span>Отклики и приглашения</span></div>
            <div id="heroCard"></div>
            <div class="app__more" id="heroMore"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="wrap">
    <ol class="path" aria-label="Путь к проекту">
      <li><b>1</b><span>Выбираете проект<br><small>ставка и стек видны сразу</small></span></li>
      <li><b>2</b><span>Анкета за 2 минуты<br><small>скоринг один раз</small></span></li>
      <li><b>3</b><span>Встреча с заказчиком<br><small>ответ в Telegram <?= tbd('срок') ?></small></span></li>
    </ol>
  </div>
</section>

<!-- 2. Проекты -->
<section class="section section--paper2" id="projects">
  <div class="wrap">
    <div class="sechead">
      <div>
        <p class="eyebrow">Открытые проекты</p>
        <h2>Посмотрите, что есть под ваш стек — прямо сейчас</h2>
        <p class="lead">Компании заказчиков не раскрываем до отклика. Ставка, стек, задачи и формат — как в кабинете.</p>
      </div>
    </div>

    <div class="filters" id="filters" role="tablist" aria-label="Направление"></div>
    <div class="stackbar" id="stackbar">
      <span class="stackbar__label">Отметьте свой стек — покажем совпадение:</span>
      <div class="stackbar__chips" id="stackChips"></div>
    </div>
    <div class="cards" id="cards"></div>
    <div class="cards__empty" id="cardsEmpty" hidden>
      <p>По этому направлению сейчас нет открытых запросов. Зарегистрируйтесь — пришлём, как только появятся, или следите в <a href="#" data-tg="cards_empty">Telegram</a>.</p>
    </div>
    <div class="cards__after">
      <p>Не нашли свой проект? Зарегистрируйтесь: новые запросы придут в кабинет и в Telegram.</p>
      <a class="btn btn--dark" href="#apply" data-cta="after_cards">Зарегистрироваться — 2 минуты</a>
    </div>
  </div>
</section>

<!-- 3. Как работает -->
<section class="section" id="how">
  <div class="wrap">
    <p class="eyebrow">Как это работает</p>
    <h2>Четыре шага от регистрации до первого счёта</h2>
    <ol class="steps">
      <li class="step"><span class="step__n">01</span><h3>Регистрация и скоринг</h3><p>Указываете стек, опыт и ставку. Мы один раз проверяем опыт по критериям пилота. <?= tbd('Срок ответа') ?></p></li>
      <li class="step"><span class="step__n">02</span><h3>Подбор под стек</h3><p>В разделе «Подобрано для вас» появляются запросы, где ваш стек совпадает с требованиями. На карточке видно совпадение, ставку, формат и занятость.</p></li>
      <li class="step"><span class="step__n">03</span><h3>Отклик или приглашение</h3><p>Откликаетесь на подходящий проект или принимаете приглашение заказчика. Дальше — встреча с заказчиком. <?= tbd('Число этапов') ?></p></li>
      <li class="step"><span class="step__n">04</span><h3>Работа и расчёты</h3><p>Ведёте отчёты по часам в кабинете, оплата — по графику. <?= tbd('Кто платит, график, договор') ?></p></li>
    </ol>
  </div>
</section>

<!-- 4. Для кого и критерии -->
<section class="section section--dark" id="criteria">
  <div class="wrap crit">
    <div>
      <p class="eyebrow eyebrow--dark">Для кого</p>
      <h2>Работаем только с сеньорами. Поэтому заказчики приходят за вами</h2>
      <p class="lead lead--dark">Заказчик видит на профиле «подтверждённый опыт» и сколько обязательных условий запроса вы закрываете. Поэтому приглашения приходят по делу, а не ради массового обзвона.</p>
      <ul class="dirs" aria-label="Направления">
        <?php foreach (DIRECTIONS as $d): ?><li><?= h($d['label']) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="crit__box">
      <h3>Критерии пилота</h3>
      <ul class="checks">
        <li>6+ лет на основном стеке</li>
        <li>Из них не меньше 18 месяцев практической разработки или аналитики за последние 2 года</li>
        <li>В среднем 18+ месяцев на одном месте работы или проекте</li>
        <li>Договор как самозанятый или ИП, расчёты в рублях <?= tbd('юрист') ?></li>
        <li>Пересечение с рабочим днём заказчика: 10:00–19:00 МСК</li>
      </ul>
      <a class="btn btn--green btn--block crit__cta" href="#apply" data-cta="criteria">Подхожу — пройти скоринг</a>
      <p class="crit__foot">Не проходите по одному из пунктов? <a href="#" data-tg="criteria">Подпишитесь на канал</a> — напишем, если критерии расширятся.</p>
    </div>
  </div>
</section>

<!-- 5. Что получаете -->
<section class="section" id="benefits">
  <div class="wrap">
    <p class="eyebrow">Что вы получаете</p>
    <h2>Что меняется по сравнению с поиском на job-сайтах</h2>
    <div class="bens">
      <div class="ben"><h3>Ставка до отклика</h3><p>Ставка в ₽/час указана в каждом запросе. Не нужно проходить два этапа, чтобы узнать бюджет.</p></div>
      <div class="ben"><h3>Подбор под стек</h3><p>Вы видите запросы, где стек совпадает, и сколько требований закрываете: «совпадает 3 из 4».</p></div>
      <div class="ben"><h3>Свои условия</h3><p>В разделе «Ограничения» указываете занятость, минимальную ставку и формат. <?= tbd('Фильтрует ли выдачу') ?></p></div>
      <div class="ben"><h3>Проверка один раз</h3><p>Опыт подтверждается на старте. Заказчик видит отметку «подтверждённый опыт» и не начинает скрининг с нуля.</p></div>
      <div class="ben"><h3>Заказчик приходит сам</h3><p>Компании видят профиль и присылают приглашение через платформу — во вкладку «Отклики и приглашения».</p></div>
      <div class="ben"><h3>Расчёты через платформу</h3><p>Часы фиксируете в «Отчётах», оплата по графику. <?= tbd('График выплат') ?></p></div>
    </div>
  </div>
</section>

<!-- 6. Оператор -->
<section class="section section--paper2" id="operator">
  <div class="wrap op">
    <div>
      <p class="eyebrow">Кто стоит за платформой</p>
      <h2>Оператор платформы — GO&nbsp;Digital</h2>
      <p class="lead">GO&nbsp;Digital с 2019 года занимается аутстаффингом IT-специалистов уровня middle и senior. GO&nbsp;Flow — платформа компании, где заказчики и специалисты находят друг друга напрямую.</p>
      <p class="op__src">Цифры с сайта <a href="https://godigital.rocks/about" target="_blank" rel="noopener">godigital.rocks</a> <?= tbd('подтвердить') ?></p>
    </div>
    <dl class="stats">
      <div><dt>с 2019</dt><dd>на рынке аутстаффинга</dd></div>
      <div><dt>300+</dt><dd>специалистов на проектах</dd></div>
      <div><dt>100+</dt><dd>реализованных проектов</dd></div>
      <div><dt>2 года</dt><dd>в среднем сотрудничество с клиентом</dd></div>
    </dl>
  </div>
</section>

<!-- 7. FAQ -->
<section class="section" id="faq">
  <div class="wrap faq">
    <div><p class="eyebrow">FAQ</p><h2>Частые вопросы</h2></div>
    <div class="faq__list">
      <details><summary>Это работа в штате GO&nbsp;Digital?</summary><p>Нет. Вы работаете на проектах компаний-заказчиков, GO&nbsp;Flow отвечает за подбор, договор и расчёты. <?= tbd('Кто сторона договора') ?></p></details>
      <details><summary>Как оформляется сотрудничество?</summary><p>По договору с самозанятым или ИП. <?= tbd('Тип договора, исполнители из РБ, КЗ, Армении') ?></p></details>
      <details><summary>Как и когда платят?</summary><p><?= tbd('Периодичность, срок после закрытия отчёта, платит ли платформа до оплаты заказчиком') ?></p></details>
      <details><summary>Ставка на карточке — до или после налогов и комиссии?</summary><p><?= tbd('Gross или на руки, есть ли комиссия платформы') ?></p></details>
      <details><summary>Какая занятость на проектах?</summary><p>В карточке указана занятость: полная, частичная, часов в неделю. Свои ограничения вы задаёте в разделе «Ограничения».</p></details>
      <details><summary>Можно совмещать с основной работой?</summary><p>Да, если занятость проекта и ваши ограничения совпадают. Учитывайте условия вашего текущего трудового договора. <?= tbd('Подтвердить политику') ?></p></details>
      <details><summary>Как проверяется опыт?</summary><p>По анкете и резюме. Проверка проходится один раз. <?= tbd('Звонок, тех-интервью, референсы; срок') ?></p></details>
      <details><summary>Сколько собеседований будет с заказчиком?</summary><p><?= tbd('Фактическое число этапов в пилоте') ?> Этапы отбора указаны в карточке каждого проекта.</p></details>
      <details><summary>Что если по моему стеку нет проектов?</summary><p>Профиль сохраняется, и новый запрос с вашим стеком придёт уведомлением. Новые запросы публикуем в <a href="#" data-tg="faq">Telegram-канале</a>.</p></details>
      <details><summary>Не прохожу по одному из критериев. Есть смысл регистрироваться?</summary><p>Критерии пилота жёсткие, потому что заказчики приходят за подтверждённым senior-уровнем. Подпишитесь на канал: при расширении критериев напишем первыми.</p></details>
    </div>
  </div>
</section>

<?php view_apply(null, $count ? 'Откликнитесь на проект или зарегистрируйтесь — 2&nbsp;минуты' : ''); ?>

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
