/* GO Flow — фронтенд лендинга и страниц проектов. Без зависимостей. */
(function () {
  'use strict';

  var GF = window.GF || {};
  var CONFIG = GF.config || {};
  var DIRS = GF.dirs || {};
  var PAGE = GF.page || { type: 'landing', projects: [] };
  var PROJECTS = PAGE.projects || [];

  /* ================= УТИЛИТЫ ================= */
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function get(kind, k) { try { return window[kind].getItem(k); } catch (e) { return null; } }
  function set(kind, k, v) { try { window[kind].setItem(k, v); } catch (e) {} }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function tbd(t) { return CONFIG.demo ? '<span class="tbd">' + esc(t) + '</span>' : ''; }
  var params = new URLSearchParams(location.search);

  /* ================= UTM ================= */
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'lc'];
  var utm = {};
  (function () {
    var saved = {};
    try { saved = JSON.parse(get('sessionStorage', 'gf_utm') || '{}') || {}; } catch (e) {}
    var fromUrl = {};
    UTM_KEYS.forEach(function (k) { if (params.get(k)) fromUrl[k] = params.get(k); });
    utm = Object.keys(fromUrl).length ? fromUrl : saved;
    utm.referrer = saved.referrer || document.referrer || '';
    utm.landing_url = saved.landing_url || location.href;
    set('sessionStorage', 'gf_utm', JSON.stringify(utm));
  })();

  /* ================= АНАЛИТИКА ================= */
  // Счётчики подключаем после загрузки страницы: события до этого копятся в очереди ym/_tmr.
  var analyticsLoaded = false;
  function addScript(src, id) {
    var s = document.createElement('script');
    s.async = true; s.src = src; if (id) s.id = id;
    document.head.appendChild(s);
  }
  function afterLoad(fn) {
    var run = function () { (window.requestIdleCallback || function (f) { setTimeout(f, 1); })(fn, { timeout: 3000 }); };
    if (document.readyState === 'complete') run(); else window.addEventListener('load', run);
  }
  function loadAnalytics() {
    if (analyticsLoaded) return;
    analyticsLoaded = true;
    if (CONFIG.ymId) {
      window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
      window.ym.l = 1 * new Date();
      window.ym(CONFIG.ymId, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true,
        params: { variant: PAGE.variant || '', dir: PAGE.dir || '', page: PAGE.type } });
    }
    if (CONFIG.vkPixelId) {
      var _tmr = window._tmr || (window._tmr = []);
      _tmr.push({ id: CONFIG.vkPixelId, type: 'pageView', start: (new Date()).getTime() });
    }
    afterLoad(function () {
      if (CONFIG.ymId) addScript('https://mc.yandex.ru/metrika/tag.js');
      if (CONFIG.vkPixelId) addScript('https://top-fwz1.mail.ru/js/code.js', 'tmr-code');
    });
  }
  function goal(name, data) {
    data = data || {};
    data.variant = PAGE.variant || ''; data.page = PAGE.type;
    if (window.ym && CONFIG.ymId) window.ym(CONFIG.ymId, 'reachGoal', name, data);
    if (window._tmr && CONFIG.vkPixelId) window._tmr.push({ type: 'reachGoal', id: CONFIG.vkPixelId, goal: name });
    if (CONFIG.demo && window.console) console.info('[goal]', name, data);
  }

  /* ================= КАРТОЧКИ ================= */
  var myStack = [];
  try { myStack = JSON.parse(get('localStorage', 'gf_stack') || '[]') || []; } catch (e) {}
  function saveStack() { set('localStorage', 'gf_stack', JSON.stringify(myStack)); }
  function has(s) { return myStack.indexOf(s) > -1; }
  function matchCount(p) { return p.stack.filter(has).length; }

  function cardHtml(p, opts) {
    opts = opts || {};
    var m = matchCount(p);
    var showMatch = myStack.length > 0;
    var chips = p.stack.map(function (s) {
      return '<span class="chip' + (showMatch && has(s) ? ' is-match' : '') + '">' + esc(s) + '</span>';
    }).join('') + (p.stackNice || []).slice(0, 2).map(function (s) {
      return '<span class="chip chip--nice' + (showMatch && has(s) ? ' is-match' : '') + '" title="Будет плюсом">+ ' + esc(s) + '</span>';
    }).join('');
    var meta = [p.format, p.workload, p.duration].filter(Boolean).map(esc);
    if (!p.workload) meta.push(tbd('занятость'));
    var rate = p.rate ? esc(p.rate) + (p.isExample ? tbd('пример') : '') : 'Ставка по запросу' + tbd('уточнить');
    if (opts.app) {
      return '<a class="pcard pcard--app" href="' + esc(p.url) + '" data-card-open="' + p.id + '">' +
        '<div class="pcard__head"><span class="pcard__role">' + esc(p.role) + '</span><span class="grade">' + esc(p.grade) + '</span></div>' +
        '<div class="stack">' + p.stack.map(function (s, i) { return '<span class="chip' + (i < Math.max(1, p.stack.length - 1) ? ' is-match' : '') + '">' + esc(s) + '</span>'; }).join('') + '</div>' +
        '<span class="matchline">совпадает ' + Math.max(1, p.stack.length - 1) + ' из ' + p.stack.length + '</span>' +
        '<div class="pcard__foot"><div class="rate">' + rate + '</div><span class="btn btn--green">Откликнуться</span></div></a>';
    }
    return '<article class="pcard' + (showMatch && m === p.stack.length && m > 0 ? ' is-top' : '') + '">' +
      '<div class="pcard__top">' +
        '<div class="pcard__tags">' + (p.isExample && CONFIG.demo ? '<span class="tag tag--warn">Пример</span>' : '') +
          (p.domain ? '<span class="tag">' + esc(p.domain) + '</span>' : '') + '</div>' +
        '<span class="grade">' + esc(p.grade) + '</span>' +
      '</div>' +
      '<h3 class="pcard__title"><a href="' + esc(p.url) + '" data-card-open="' + p.id + '">' + esc(p.role) + '</a></h3>' +
      '<div class="pcard__rate' + (p.rate ? '' : ' is-empty') + '">' + rate + '</div>' +
      (p.summary ? '<p class="pcard__sum">' + esc(p.summary) + '</p>' : '') +
      '<div class="stack">' + chips + '</div>' +
      (showMatch ? '<span class="matchline' + (m ? '' : ' is-zero') + '">совпадает ' + m + ' из ' + p.stack.length + '</span>' : '') +
      (p.tasks && p.tasks.length ? '<ul class="pcard__tasks">' + p.tasks.slice(0, 2).map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>' : '') +
      '<div class="meta">' + meta.map(function (x) { return '<span>' + x + '</span>'; }).join('') + '</div>' +
      '<div class="pcard__foot">' +
        '<a class="pcard__more" href="' + esc(p.url) + '" data-card-open="' + p.id + '">Подробнее</a>' +
        '<button class="btn btn--green" type="button" data-apply="' + p.id + '">Откликнуться</button>' +
      '</div>' +
    '</article>';
  }

  var filter = PAGE.dir || 'all';
  function visibleProjects() {
    var list = filter === 'all' ? PROJECTS.slice() : PROJECTS.filter(function (p) { return p.direction === filter; });
    if (myStack.length) list.sort(function (a, b) { return (matchCount(b) / b.stack.length) - (matchCount(a) / a.stack.length); });
    return list;
  }

  function renderFilters() {
    var box = $('#filters');
    if (!box) return;
    var counts = {};
    PROJECTS.forEach(function (p) { counts[p.direction] = (counts[p.direction] || 0) + 1; });
    var keys = ['all'].concat(Object.keys(DIRS));
    box.innerHTML = keys.map(function (k) {
      var label = k === 'all' ? 'Все' : DIRS[k].label;
      var n = k === 'all' ? PROJECTS.length : (counts[k] || 0);
      return '<button type="button" role="tab" data-f="' + k + '" aria-selected="' + (k === filter) + '">' + esc(label) + ' <span>' + n + '</span></button>';
    }).join('');
  }
  function setFilter(k, scroll) {
    filter = k;
    $$('#filters button').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-f') === k)); });
    $$('[data-pick-dir]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-pick-dir') === k); });
    renderStackChips();
    renderCards();
    if (scroll) $('#projects').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function renderStackChips() {
    var box = $('#stackChips');
    if (!box) return;
    var pool = [];
    var src = filter === 'all' ? PROJECTS : PROJECTS.filter(function (p) { return p.direction === filter; });
    src.forEach(function (p) { p.stack.concat(p.stackNice || []).forEach(function (s) { if (pool.indexOf(s) < 0) pool.push(s); }); });
    if (filter !== 'all' && DIRS[filter]) DIRS[filter].stack.forEach(function (s) { if (pool.indexOf(s) < 0) pool.push(s); });
    box.innerHTML = pool.slice(0, 18).map(function (s) {
      return '<button type="button" aria-pressed="' + has(s) + '">' + esc(s) + '</button>';
    }).join('') + (myStack.length ? '<button type="button" class="stackbar__reset" data-reset>Сбросить</button>' : '');
  }
  function renderCards() {
    var box = $('#cards');
    if (!box) return;
    var list = PAGE.type === 'landing' ? visibleProjects() : PROJECTS;
    box.innerHTML = list.map(function (p) { return cardHtml(p); }).join('');
    var empty = $('#cardsEmpty');
    if (empty) empty.hidden = list.length > 0;
  }
  function renderHero() {
    var box = $('#heroCard');
    if (!box) return;
    var src = PAGE.dir ? PROJECTS.filter(function (p) { return p.direction === PAGE.dir; }) : PROJECTS;
    var p = src[0] || PROJECTS[0];
    if (!p) { box.innerHTML = '<p class="app__empty">Скоро здесь появятся проекты</p>'; return; }
    box.innerHTML = cardHtml(p, { app: true });
    var rest = PROJECTS.length - 1;
    var more = $('#heroMore');
    if (more && rest > 0) more.innerHTML = '<a href="#projects">+ ещё ' + rest + ' в подборке →</a>';
  }

  document.addEventListener('click', function (e) {
    var f = e.target.closest('#filters button[data-f]');
    if (f) { setFilter(f.getAttribute('data-f')); goal('spec_filter', { dir: f.getAttribute('data-f') }); return; }
    var pick = e.target.closest('[data-pick-dir]');
    if (pick && PAGE.type === 'landing') {
      e.preventDefault();
      var d = pick.getAttribute('data-pick-dir');
      setFilter(filter === d ? 'all' : d, true);
      goal('spec_hero_pick', { dir: d });
      return;
    }
    var chip = e.target.closest('#stackChips button');
    if (chip) {
      if (chip.hasAttribute('data-reset')) myStack = [];
      else {
        var s = chip.textContent, i = myStack.indexOf(s);
        if (i > -1) myStack.splice(i, 1); else myStack.push(s);
      }
      saveStack(); renderStackChips(); renderCards();
      goal('spec_stack_pick');
      return;
    }
    var ap = e.target.closest('[data-apply]');
    if (ap) {
      var p = PROJECTS.filter(function (x) { return String(x.id) === ap.getAttribute('data-apply'); })[0];
      if (p) { setFormProject(p); goal('spec_project_apply_click', { project: p.slug }); }
      $('#apply').scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    var op = e.target.closest('[data-card-open]');
    if (op) goal('spec_project_open', { id: op.getAttribute('data-card-open') });
  });

  /* ============ Проверка стека на странице проекта ============ */
  (function () {
    var box = $('#matchPick');
    if (!box || !PAGE.project) return;
    var p = PAGE.project;
    function draw() {
      box.innerHTML = p.stackAll.map(function (s) { return '<button type="button" aria-pressed="' + has(s) + '">' + esc(s) + '</button>'; }).join('');
      var m = matchCount(p);
      var res = $('#matchRes');
      if (!myStack.length) { res.textContent = ''; return; }
      res.innerHTML = '<b>Совпадает ' + m + ' из ' + p.stack.length + '</b> обязательных. ' +
        (m === p.stack.length ? 'Отличный матч — откликайтесь.' : m >= p.stack.length - 1 ? 'Хороший матч, остальное можно обсудить с заказчиком.' : 'Совпадение слабое — посмотрите и другие проекты.');
    }
    box.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (!b) return;
      var s = b.textContent, i = myStack.indexOf(s);
      if (i > -1) myStack.splice(i, 1); else myStack.push(s);
      saveStack(); draw(); renderCards();
    });
    draw();
  })();

  /* ================= ФОРМА ================= */
  var form = $('#form');
  var token = '';
  var picked = myStack.slice();
  if (form) initForm();

  function setFormProject(p) {
    var ctx = $('#formCtx');
    $('#fProject').value = p ? p.id : '';
    if (!ctx) return;
    ctx.hidden = !p;
    if (p) {
      $('#formCtxTitle').textContent = p.title;
      $('#formCtxRate').textContent = p.rate || '';
      $('#formCtxClear').hidden = PAGE.type === 'project';
      var sel = $('#fDirection');
      if (sel && !sel.value) sel.value = p.direction;
      p.stack.forEach(function (s) { if (picked.indexOf(s) < 0 && has(s)) picked.push(s); });
    }
  }

  function initForm() {
    var step1 = $('#step1'), step2 = $('#step2');

    function renderStackPick(dir) {
      var list = (DIRS[dir] && DIRS[dir].stack) ? DIRS[dir].stack.slice() : [];
      picked.forEach(function (s) { if (list.indexOf(s) < 0) list.push(s); });
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
    var clear = $('#formCtxClear');
    if (clear) clear.addEventListener('click', function () { setFormProject(null); });

    function setProgress(n) {
      $('#progressLabel').textContent = 'Шаг ' + n + ' из 2';
      $('#progressBar').style.width = (n * 50) + '%';
    }
    function markBad(el, bad) {
      var t = el.closest('.seg') || el.closest('.check') || el;
      t.classList.toggle('is-bad', bad);
    }
    function validate(fs, errEl) {
      var ok = true, firstBad = null, seen = {};
      $$('[required]', fs).forEach(function (el) {
        var bad;
        if (el.type === 'radio') {
          if (seen[el.name]) return;
          seen[el.name] = true;
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
      if (!ok) { errEl.textContent = 'Заполните отмеченные поля.'; if (firstBad.focus) firstBad.focus(); }
      return ok;
    }
    form.addEventListener('change', function (e) { markBad(e.target, false); });
    form.addEventListener('input', function (e) { markBad(e.target, false); });

    function payload(stage) {
      var fd = new FormData(form), out = {};
      fd.forEach(function (v, k) { out[k] = v; });
      out.stage = stage;
      out.token = token;
      out.consent_pd = form.consent_pd.checked;
      out.consent_ads = form.consent_ads.checked;
      out.stack = picked.concat(String(out.stack_extra || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean));
      delete out.stack_extra;
      Object.keys(utm).forEach(function (k) { out[k] = utm[k]; });
      out.variant = PAGE.variant || '';
      out.page_dir = PAGE.dir || (PAGE.project ? PAGE.project.direction : '');
      return out;
    }
    function send(data) {
      if (!CONFIG.apiApply) return Promise.resolve({ ok: true, demo: true });
      return fetch(CONFIG.apiApply, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data), credentials: 'same-origin'
      }).then(function (r) { return r.json(); });
    }
    function busy(btn, on, text) {
      btn.disabled = on;
      if (on) { btn.dataset.text = btn.innerHTML; btn.textContent = text || 'Отправляем…'; }
      else if (btn.dataset.text) btn.innerHTML = btn.dataset.text;
    }
    function fail(errEl) {
      errEl.hidden = false;
      errEl.innerHTML = 'Не получилось отправить. Проверьте интернет и попробуйте ещё раз.';
    }
    function showResult(res) {
      form.hidden = true;
      var ctx = $('#formCtx'); if (ctx) ctx.hidden = true;
      $('#resPass').hidden = res !== 'pass';
      $('#resBorder').hidden = res !== 'border';
      $('#resFail').hidden = res !== 'fail';
      goal('spec_score_' + res);
      $('#formbox').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('#next1').addEventListener('click', function () {
      if (!validate(step1, $('#err1'))) return;
      var btn = this;
      busy(btn, true, 'Сохраняем…');
      send(payload('step1')).then(function (r) {
        busy(btn, false);
        if (!r || !r.ok) return fail($('#err1'));
        token = r.token || '';
        goal('spec_form_step1');
        if (r.score === 'fail') {
          $('#failText').textContent = 'Сейчас пилот работает только со специалистами, у которых 6+ лет на основном стеке. Если критерии расширятся, напишем первыми. А пока запросы с другими требованиями публикуем в Telegram.';
          showResult('fail');
          return;
        }
        renderStackPick(form.direction.value);
        step1.hidden = true; step2.hidden = false;
        setProgress(2);
        $('#formbox').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function () { busy(btn, false); fail($('#err1')); });
    });
    $('#back2').addEventListener('click', function () { step2.hidden = true; step1.hidden = false; setProgress(1); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validate(step2, $('#err2'))) return;
      var btn = $('#submit2');
      busy(btn, true);
      send(payload('step2')).then(function (r) {
        busy(btn, false);
        if (!r || !r.ok) return fail($('#err2'));
        goal('spec_form_submit');
        if (r.cabinetUrl) $('#cabinetLink').href = r.cabinetUrl;
        var pp = $('#passProject'), ctxT = $('#formCtxTitle');
        if (pp && ctxT && $('#fProject').value) pp.textContent = ', в том числе на «' + ctxT.textContent + '»';
        showResult(r.score || 'border');
      }).catch(function () { busy(btn, false); fail($('#err2')); });
    });
    $('#cabinetLink').href = CONFIG.cabinetUrl || '#';
  }

  /* ================= ССЫЛКИ, СТИКИ, COOKIE ================= */
  function tgHref(place) {
    var u = CONFIG.tgUrl || 'https://t.me/';
    return u + (u.indexOf('?') > -1 ? '&' : '?') + 'start=' + encodeURIComponent((utm.utm_campaign || 'landing') + '_' + place);
  }
  $$('[data-tg]').forEach(function (a) {
    var place = a.getAttribute('data-tg');
    a.href = tgHref(place); a.target = '_blank'; a.rel = 'noopener';
    a.addEventListener('click', function () { goal('spec_tg_click', { place: place }); });
  });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-cta]');
    if (c) goal('spec_cta_click', { place: c.getAttribute('data-cta') });
  });
  $$('.faq details').forEach(function (d, i) {
    d.addEventListener('toggle', function () { if (d.open) goal('spec_faq_open', { q: i + 1 }); });
  });

  var sticky = $('#sticky');
  var heroEnd = $('.hero__cta') || $('.pbox .btn');
  if (sticky && heroEnd && 'IntersectionObserver' in window) {
    var heroOut = false, applyIn = false;
    var upd = function () {
      var on = heroOut && !applyIn;
      sticky.classList.toggle('is-on', on);
      sticky.setAttribute('aria-hidden', String(!on));
      $('a', sticky).tabIndex = on ? 0 : -1;
    };
    new IntersectionObserver(function (es) { heroOut = !es[0].isIntersecting && es[0].boundingClientRect.top < 0; upd(); }).observe(heroEnd);
    var ap = $('#apply');
    if (ap) new IntersectionObserver(function (es) { applyIn = es[0].isIntersecting; upd(); }, { threshold: 0.05 }).observe(ap);
    // На лендинге: до блока проектов стики ведёт к проектам, внутри и после — к анкете.
    var pr = $('#projects'), sb = $('#stickyBtn');
    if (pr && sb) {
      var label = 'Смотреть проекты' + (PROJECTS.length ? ' · ' + PROJECTS.length : '');
      var onScroll = function () {
        var past = pr.getBoundingClientRect().top < window.innerHeight * 0.3;
        sb.href = past ? '#apply' : '#projects';
        sb.textContent = past ? 'Зарегистрироваться — 2 минуты' : label;
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }
  }

  var cookie = $('#cookie');
  if (cookie) {
    if (get('localStorage', 'gf_cookie') !== '1') cookie.hidden = false;
    $('#cookieOk').addEventListener('click', function () {
      set('localStorage', 'gf_cookie', '1'); cookie.hidden = true; loadAnalytics();
    });
  }
  if (!CONFIG.analyticsAfterConsent || get('localStorage', 'gf_cookie') === '1') loadAnalytics();

  /* ================= СТАРТ ================= */
  renderFilters();
  renderStackChips();
  renderCards();
  renderHero();
  if (PAGE.type === 'project' && PAGE.project) goal('spec_project_view', { project: PAGE.project.slug });
})();
