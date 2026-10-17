/* GO Flow — лендинг для специалистов. Без зависимостей. */
(function () {
  'use strict';

  /* ================= НАСТРОЙКИ ================= */
  var CONFIG = {
    // Макет: подсвечивает значения, которые нужно подтвердить. Перед запуском — false.
    demo: true,
    // Куда отправлять анкету (POST JSON). Пусто — анкета никуда не уходит, только показывается результат.
    formEndpoint: '',
    // Номер счётчика Яндекс Метрики. Пусто — Метрика не подключается.
    ymId: '',
    // ID пикселя VK Ads (top.mail.ru). Пусто — пиксель не подключается.
    vkPixelId: '',
    // Канал или бот с проектами.
    tgUrl: 'https://t.me/',
    // Кабинет GO Flow, куда ведёт экран «Проходит».
    cabinetUrl: '#',
    // Загружать аналитику только после нажатия «Понятно» в cookie-баннере.
    analyticsAfterConsent: false
  };

  /* ================= ДАННЫЕ ================= */
  // Направления: подмена заголовка, ставки и порядка карточек через ?dir=
  var DIRS = {
    all:     { label: 'Все',               noun: 'senior-разработчиков и аналитиков', rate: '6 000', rateTbd: true },
    python:  { label: 'Python',            noun: 'senior Python-разработчиков',  rate: '6 000', rateTbd: true, stack: ['Python', 'FastAPI', 'Django', 'PostgreSQL', 'Docker', 'Kafka', 'Redis'] },
    go:      { label: 'Go',                noun: 'senior Go-разработчиков',      rate: '', stack: ['Go', 'PostgreSQL', 'Kafka', 'gRPC', 'Kubernetes', 'Redis'] },
    dotnet:  { label: '.NET',              noun: 'senior .NET-разработчиков',    rate: '', stack: ['C#', '.NET', 'ASP.NET Core', 'EF Core', 'MS SQL', 'PostgreSQL'] },
    react:   { label: 'React',             noun: 'senior React-разработчиков',   rate: '', stack: ['React', 'TypeScript', 'Next.js', 'Redux', 'Webpack', 'Jest'] },
    angular: { label: 'Angular',           noun: 'senior Angular-разработчиков', rate: '', stack: ['Angular', 'TypeScript', 'RxJS', 'NgRx', 'Jest'] },
    sa:      { label: 'Системный аналитик', noun: 'senior системных аналитиков',  rate: '', stack: ['BPMN', 'UML', 'REST', 'SQL', 'Kafka', 'Confluence'] },
    ba:      { label: 'Бизнес-аналитик',    noun: 'senior бизнес-аналитиков',     rate: '', stack: ['BPMN', 'User Stories', 'SQL', 'BI', 'Jira'] }
  };

  // Карточки проектов. ПРИМЕРЫ: реальные запросы подставит заказчик.
  // match — для карточки в кабинете на первом экране.
  var PROJECTS = [
    { dir: 'python',  role: 'Backend-разработчик',     stack: ['Python', 'FastAPI', 'PostgreSQL', 'Docker'], match: [0, 1, 2], load: '', rate: '6 000', rateTbd: true },
    { dir: 'go',      role: 'Backend-разработчик',     stack: ['Go', 'PostgreSQL', 'Kafka'], match: [0, 1], load: '', rate: '' },
    { dir: 'dotnet',  role: '.NET-разработчик',        stack: ['C#', '.NET', 'PostgreSQL'], match: [0, 1], load: '', rate: '' },
    { dir: 'react',   role: 'Frontend-разработчик',    stack: ['React', 'TypeScript', 'Next.js'], match: [0, 1], load: '', rate: '' },
    { dir: 'angular', role: 'Frontend-разработчик',    stack: ['Angular', 'TypeScript', 'RxJS'], match: [0, 1], load: '', rate: '' },
    { dir: 'sa',      role: 'Системный аналитик',      stack: ['BPMN', 'UML', 'REST', 'SQL'], match: [0, 2, 3], load: '', rate: '' },
    { dir: 'ba',      role: 'Бизнес-аналитик',         stack: ['BPMN', 'User Stories', 'SQL'], match: [0, 1], load: '', rate: '' }
  ];

  // Офферы для A/B: ?v=a|b|c
  var OFFERS = {
    a: {
      title: function (d) { return 'Проекты для ' + d.noun + '. Ставка до ' + rateHtml(d.rate, d.rateTbd, 'ставка') + '&nbsp;₽/час'; },
      sub: 'Компании публикуют запросы, GO&nbsp;Flow подбирает их под ваш стек. Ставку, стек и формат вы видите до отклика.'
    },
    b: {
      title: function () { return 'Только проекты под ваш стек. Без сотни нерелевантных откликов'; },
      sub: 'Один раз проходите проверку опыта. Дальше видите запросы, где стек совпадает, и откликаетесь только туда, где условия подходят.'
    },
    c: {
      title: function () { return 'Заказчики приглашают вас на проекты сами'; },
      sub: 'Подтвердите опыт, укажите стек и ставку. Компании увидят профиль с отметкой «подтверждённый опыт» и пришлют приглашение.'
    }
  };

  /* ================= УТИЛИТЫ ================= */
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function store(kind) { try { return window[kind]; } catch (e) { return null; } }
  function get(kind, k) { try { var s = store(kind); return s ? s.getItem(k) : null; } catch (e) { return null; } }
  function set(kind, k, v) { try { var s = store(kind); if (s) s.setItem(k, v); } catch (e) {} }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function tbd(text) { return '<span class="tbd">' + esc(text) + '</span>'; }
  function rateHtml(rate, isTbd, label) {
    if (!rate) return tbd(label || 'ставка');
    return rate.replace(' ', '&nbsp;') + (isTbd ? ' ' + tbd('подтвердить') : '');
  }

  var params = new URLSearchParams(location.search);

  /* ================= UTM ================= */
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'dir', 'v'];
  var utm = {};
  (function () {
    var saved = {};
    try { saved = JSON.parse(get('sessionStorage', 'gf_utm') || '{}') || {}; } catch (e) {}
    var fromUrl = {};
    UTM_KEYS.forEach(function (k) { if (params.get(k)) fromUrl[k] = params.get(k); });
    utm = Object.keys(fromUrl).length ? fromUrl : saved;
    if (!utm.referrer) utm.referrer = saved.referrer || document.referrer || '';
    if (!utm.landing_url) utm.landing_url = saved.landing_url || location.href;
    set('sessionStorage', 'gf_utm', JSON.stringify(utm));
  })();

  /* ================= ВАРИАНТ И НАПРАВЛЕНИЕ ================= */
  var variant = (params.get('v') || '').toLowerCase();
  if (!OFFERS[variant]) {
    variant = get('localStorage', 'gf_v');
    if (!OFFERS[variant]) variant = Math.random() < 0.5 ? 'a' : 'b'; // C запускаем позже, см. ТЗ 2.1
    set('localStorage', 'gf_v', variant);
  }
  var dirKey = (params.get('dir') || '').toLowerCase();
  if (!DIRS[dirKey]) dirKey = 'all';

  /* ================= АНАЛИТИКА ================= */
  var analyticsLoaded = false;
  function loadAnalytics() {
    if (analyticsLoaded) return;
    analyticsLoaded = true;
    if (CONFIG.ymId) {
      (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
        m[i].l = 1 * new Date();
        k = e.createElement(t); a = e.getElementsByTagName(t)[0];
        k.async = 1; k.src = r; a.parentNode.insertBefore(k, a);
      })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
      window.ym(CONFIG.ymId, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true,
        params: { variant: variant, dir: dirKey } });
    }
    if (CONFIG.vkPixelId) {
      var _tmr = window._tmr || (window._tmr = []);
      _tmr.push({ id: CONFIG.vkPixelId, type: 'pageView', start: (new Date()).getTime() });
      var s = document.createElement('script');
      s.async = true; s.id = 'tmr-code'; s.src = 'https://top-fwz1.mail.ru/js/code.js';
      document.head.appendChild(s);
    }
  }
  function goal(name, data) {
    data = data || {};
    data.variant = variant; data.dir = dirKey;
    if (window.ym && CONFIG.ymId) window.ym(CONFIG.ymId, 'reachGoal', name, data);
    if (window._tmr && CONFIG.vkPixelId) window._tmr.push({ type: 'reachGoal', id: CONFIG.vkPixelId, goal: name });
    if (CONFIG.demo && window.console) console.info('[goal]', name, data);
  }

  /* ================= РЕНДЕР ================= */
  function cardHtml(p, opts) {
    opts = opts || {};
    var chips = p.stack.map(function (s, i) {
      var m = opts.app && p.match && p.match.indexOf(i) > -1;
      return '<span class="chip' + (m ? ' is-match' : '') + '">' + esc(s) + '</span>';
    }).join('');
    var matchLine = opts.app && p.match
      ? '<span class="match">совпадает ' + p.match.length + ' из ' + p.stack.length + '</span>' : '';
    var load = p.load ? esc(p.load) : tbd('занятость');
    var head = '<div class="pcard__head"><div>' +
      (!opts.app && CONFIG.demo ? '<div class="pcard__tag">Пример карточки</div>' : '') +
      '<div class="pcard__role">' + esc(p.role) + '</div></div><span class="grade">Senior</span></div>';
    return head +
      '<div class="stack">' + chips + '</div>' + matchLine +
      '<div class="meta"><span>Удалённо</span><span>' + load + '</span></div>' +
      '<div class="pcard__foot"><div class="rate">' + (p.rate ? 'до ' + p.rate.replace(' ', '&nbsp;') + ' <small>₽/час</small>' + (p.rateTbd ? tbd('подтвердить') : '') : 'до … <small>₽/час</small>' + tbd('ставка')) + '</div>' +
      (opts.app ? '<span class="btn btn--green">Откликнуться</span>'
                : '<a class="btn btn--green" href="#apply" data-card="' + p.dir + '">Откликнуться</a>') +
      '</div>';
  }

  function renderHero() {
    var d = DIRS[dirKey];
    var o = OFFERS[variant];
    $('#heroTitle').innerHTML = o.title(d);
    $('#heroSub').innerHTML = o.sub;
    if (dirKey !== 'all') $('#heroStackBadge').textContent = d.stack.slice(0, 4).join(' · ');
    var p = PROJECTS.filter(function (x) { return x.dir === dirKey; })[0] || PROJECTS[0];
    $('#heroCard').innerHTML = cardHtml(p, { app: true });
  }

  var currentFilter = dirKey;
  function renderFilters() {
    var box = $('#filters');
    box.innerHTML = Object.keys(DIRS).map(function (k) {
      return '<button type="button" role="tab" data-f="' + k + '" aria-selected="' + (k === currentFilter) + '">' + esc(DIRS[k].label) + '</button>';
    }).join('');
    box.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-f]');
      if (!b) return;
      currentFilter = b.getAttribute('data-f');
      $$('button', box).forEach(function (x) { x.setAttribute('aria-selected', String(x === b)); });
      renderCards();
    });
  }
  function renderCards() {
    var list = currentFilter === 'all' ? PROJECTS.slice() : PROJECTS.filter(function (p) { return p.dir === currentFilter; });
    $('#cards').innerHTML = list.map(function (p) { return '<article class="pcard">' + cardHtml(p) + '</article>'; }).join('');
    $('#cardsEmpty').hidden = list.length > 0;
  }

  /* ================= ФОРМА ================= */
  var form = $('#form');
  var step1 = $('#step1'), step2 = $('#step2');
  var picked = [];

  function renderStackPick(dir) {
    var d = DIRS[dir] || DIRS.python;
    var list = d.stack || [];
    picked = picked.filter(function (s) { return list.indexOf(s) > -1; });
    $('#stackPick').innerHTML = list.map(function (s) {
      return '<button type="button" aria-pressed="' + (picked.indexOf(s) > -1) + '">' + esc(s) + '</button>';
    }).join('');
  }
  $('#stackPick').addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b) return;
    var s = b.textContent, i = picked.indexOf(s);
    if (i > -1) picked.splice(i, 1); else picked.push(s);
    b.setAttribute('aria-pressed', String(i < 0));
  });

  function setProgress(n) {
    $('#progressLabel').textContent = 'Шаг ' + n + ' из 2';
    $('#progressBar').style.width = (n * 50) + '%';
  }
  function markBad(el, bad) {
    var t = el.closest('.seg') || el.closest('.check') || el;
    t.classList.toggle('is-bad', bad);
  }
  function validate(fs, errEl) {
    var ok = true, firstBad = null;
    var radiosSeen = {};
    $$('[required]', fs).forEach(function (el) {
      var bad;
      if (el.type === 'radio') {
        if (radiosSeen[el.name]) return;
        radiosSeen[el.name] = true;
        bad = !form.querySelector('input[name="' + el.name + '"]:checked');
      } else if (el.type === 'checkbox') {
        bad = !el.checked;
      } else if (el.name === 'contact') {
        var v = el.value.trim();
        bad = !(/^@?[A-Za-z0-9_]{5,32}$/.test(v) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v));
      } else if (el.type === 'url') {
        bad = !/^(https?:\/\/)?[^\s.]+\.[^\s]{2,}/i.test(el.value.trim());
      } else {
        bad = !String(el.value).trim();
      }
      markBad(el, bad);
      if (bad) { ok = false; firstBad = firstBad || el; }
    });
    errEl.hidden = ok;
    if (!ok) {
      errEl.textContent = 'Заполните отмеченные поля.';
      if (firstBad.focus) firstBad.focus({ preventScroll: false });
    }
    return ok;
  }
  form.addEventListener('change', function (e) { markBad(e.target, false); });
  form.addEventListener('input', function (e) { markBad(e.target, false); });

  function data() {
    var fd = new FormData(form), out = {};
    fd.forEach(function (v, k) { out[k] = v; });
    out.consent_pd = !!form.consent_pd.checked;
    out.consent_ads = !!form.consent_ads.checked;
    out.stack = picked.concat(String(out.stack_extra || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean));
    delete out.stack_extra;
    out.ym_client_id = '';
    try { if (window.ym && CONFIG.ymId) window.ym(CONFIG.ymId, 'getClientID', function (id) { out.ym_client_id = id; }); } catch (e) {}
    Object.keys(utm).forEach(function (k) { out[k === 'v' ? 'utm_v' : k] = utm[k]; });
    out.variant = variant;
    out.page_dir = dirKey;
    out.submitted_at = new Date().toISOString();
    return out;
  }

  function send(stage, payload) {
    payload.stage = stage;
    if (!CONFIG.formEndpoint) {
      if (CONFIG.demo && window.console) console.info('[form:' + stage + ']', payload);
      return Promise.resolve();
    }
    return fetch(CONFIG.formEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      keepalive: true
    }).catch(function () {});
  }

  // Скоринг (дублировать на бэкенде): 0 отклонений — проходит, 1 — ручная проверка, 2+ — нет.
  var DEV = {
    handson: { '18-24': 0, '12-17': 1, 'lt12': 2 },
    tenure:  { '36+': 0, '18-35': 0, '12-17': 1, 'lt12': 2 },
    legal:   { 'npd_ru': 0, 'ip_ru': 0, 'other': 1, 'none': 1 },
    overlap: { 'full': 0, '6+': 0, '4-5': 1, 'lt4': 2 }
  };
  function score(d) {
    if (d.years === 'lt3' || d.years === '3-5') return 'fail';
    var n = 0;
    Object.keys(DEV).forEach(function (k) { n += DEV[k][d[k]] || 0; });
    return n === 0 ? 'pass' : n === 1 ? 'border' : 'fail';
  }

  function showResult(res) {
    form.hidden = true;
    $('#resPass').hidden = res !== 'pass';
    $('#resBorder').hidden = res !== 'border';
    $('#resFail').hidden = res !== 'fail';
    goal('spec_score_' + res);
    $('#formbox').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  $('#next1').addEventListener('click', function () {
    if (!validate(step1, $('#err1'))) return;
    var d = data();
    goal('spec_form_step1');
    send('step1', d);
    if (d.years === 'lt3' || d.years === '3-5') {
      $('#failText').textContent = 'Сейчас пилот работает только со специалистами, у которых 6+ лет на основном стеке. Если критерии расширятся, напишем первыми. А пока запросы с другими требованиями публикуем в Telegram.';
      showResult('fail');
      return;
    }
    renderStackPick(form.direction.value);
    step1.hidden = true; step2.hidden = false;
    setProgress(2);
    $('#formbox').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  $('#back2').addEventListener('click', function () {
    step2.hidden = true; step1.hidden = false; setProgress(1);
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validate(step2, $('#err2'))) return;
    var d = data();
    var res = score(d);
    d.score = res;
    var btn = $('#submit2');
    btn.disabled = true; btn.textContent = 'Отправляем…';
    goal('spec_form_submit');
    send('step2', d).then(function () { showResult(res); });
  });

  /* ================= ССЫЛКИ, CTA, СТИКИ ================= */
  function tgHref(place) {
    var u = CONFIG.tgUrl;
    return u + (u.indexOf('?') > -1 ? '&' : '?') + 'start=' + encodeURIComponent((utm.utm_campaign || 'landing') + '_' + place);
  }
  $$('[data-tg]').forEach(function (a) {
    var place = a.getAttribute('data-tg');
    a.href = tgHref(place);
    a.target = '_blank'; a.rel = 'noopener';
    a.addEventListener('click', function () { goal('spec_tg_click', { place: place }); });
  });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-cta]');
    if (c) goal('spec_cta_click', { place: c.getAttribute('data-cta') });
    var k = e.target.closest('[data-card]');
    if (k) {
      var d = k.getAttribute('data-card');
      goal('spec_project_card_click', { card_dir: d });
      if (!form.hidden && !step1.hidden) form.direction.value = d;
    }
  });
  $$('.faq details').forEach(function (d, i) {
    d.addEventListener('toggle', function () { if (d.open) goal('spec_faq_open', { q: i + 1 }); });
  });
  $('#cabinetLink').href = CONFIG.cabinetUrl;

  // Стики-кнопка на мобильных: после первого экрана и пока не видно анкету.
  var sticky = $('#sticky');
  if ('IntersectionObserver' in window) {
    var heroOut = false, applyIn = false;
    var upd = function () {
      var on = heroOut && !applyIn;
      sticky.classList.toggle('is-on', on);
      sticky.setAttribute('aria-hidden', String(!on));
      $('a', sticky).tabIndex = on ? 0 : -1;
    };
    new IntersectionObserver(function (es) { heroOut = !es[0].isIntersecting; upd(); }).observe($('.hero__cta'));
    new IntersectionObserver(function (es) { applyIn = es[0].isIntersecting; upd(); }, { threshold: 0.05 }).observe($('#apply'));
  }

  /* ================= COOKIE ================= */
  var cookie = $('#cookie');
  if (get('localStorage', 'gf_cookie') !== '1') cookie.hidden = false;
  if (!CONFIG.analyticsAfterConsent || get('localStorage', 'gf_cookie') === '1') loadAnalytics();
  $('#cookieOk').addEventListener('click', function () {
    set('localStorage', 'gf_cookie', '1');
    cookie.hidden = true;
    loadAnalytics();
  });

  /* ================= СТАРТ ================= */
  if (CONFIG.demo) { document.body.classList.add('is-demo'); $('#demoBar').hidden = false; }
  if (dirKey !== 'all') form.direction.value = DIRS[dirKey] ? dirKey : '';
  renderHero();
  renderFilters();
  renderCards();
  var hiddenBox = $('#hidden');
  hiddenBox.innerHTML = UTM_KEYS.concat(['referrer', 'landing_url']).map(function (k) {
    return '<input type="hidden" name="h_' + k + '" value="' + esc(utm[k] || '') + '">';
  }).join('');
})();
