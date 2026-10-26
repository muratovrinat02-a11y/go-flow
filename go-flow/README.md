# GO Flow — лендинг для специалистов (spec.godigital.rocks)

Нужен PHP 8.0+ с `pdo_sqlite`. Собирать ничего не нужно, отдельная база тоже не нужна: SQLite-файл создаётся сам.

## Что внутри

| Адрес | Что это |
|---|---|
| `/` | Лендинг: открытые проекты, фильтр по стеку, короткая анкета |
| `/python/`, `/go/`, `/dotnet/`, `/react/`, `/angular/`, `/sa/`, `/ba/` | Страницы направлений для SEO и рекламы |
| `/p/<slug>` | Страница проекта: полная карточка, отклик, превью для Telegram |
| `/r/<code>` | Короткая ссылка для аутрича: считает клик и ведёт на проект с UTM |
| `/admin/` | Админка: проекты, анкеты, ссылки для каналов, источники и CPA |
| `/sitemap.xml`, `/robots.txt` | Для поисковиков |

## Метки и тесты

- Лендинг принимает `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `utm_adgroup`, `utm_creative`, `yclid` и передаёт их в регистрацию без изменений. Значения чистятся по правилам `scripts/analytics/validate_utm.py`: только a-z, 0-9, `_`, до 100 символов, без e-mail, телефонов и ссылок. Отброшенные метки видны в анкете (`utm_rejected`).
- Без меток источник не подставляется: платформа сама решает, unknown это или organic.
- Типы ссылок в админке = словарь `docs/analytics/marketing/data/UTM_DICTIONARY.csv`.
- Тесты: `bash tests/landing/run.sh` (временная база, локальный сервер, наружу ничего не уходит).

## Как работает анкета

Два вопроса: направление (по желанию — стек) и опыт на стеке.

- **«2–5 лет» или «6 лет и больше».** Анкета сохраняется, человек уходит на регистрацию на платформе: `https://goflow.godigital.rocks/specialist/register?utm_source=…&utm_campaign=…&landing=spec&lead=<id>&direction=…&years=…&project=…`. Платформа может прочитать эти параметры, чтобы видеть источник и проект.
- **«Меньше 2 лет».** Человек видит вежливое сообщение, на регистрацию не уходит.

Личных данных на лендинге не собираем: e-mail или телефон человек вводит уже на платформе. Поэтому на лендинге нет согласия на обработку ПДн — только cookie-баннер для Метрики.

## Установка на spec.godigital.rocks

1. Загрузите содержимое архива в корень сайта поддомена.
2. Папка `data/` должна быть доступна PHP на запись: там база и кэш стилей.
3. Откройте `https://spec.godigital.rocks/` — база и примеры проектов создадутся при первом заходе.
4. Войдите в `https://spec.godigital.rocks/admin/`, замените примеры на реальные проекты.
5. Проверьте, что `https://spec.godigital.rocks/config.php` и `https://spec.godigital.rocks/data/goflow.sqlite` отдают 403 или 404.

**Apache:** правила адресов, сжатия и кэша уже в `.htaccess`.

**Чистый nginx:**

```nginx
location ~ ^/(lib|data)/ { deny all; }
location ~ ^/(config|config\.sample|sync)\.php$ { deny all; }
location ~ \.(sqlite|md|min)$ { deny all; }
rewrite ^/p/([a-z0-9-]+)/?$ /project.php?slug=$1 last;
rewrite ^/r/([a-z0-9]+)/?$ /r.php?c=$1 last;
rewrite ^/(python|go|dotnet|react|angular|sa|ba)/?$ /index.php?dir=$1 last;
rewrite ^/sitemap\.xml$ /sitemap.php last;
gzip on; gzip_types text/css application/javascript application/json image/svg+xml;
```

## config.php

| Ключ | Что делает |
|---|---|
| `demo` | `true` — жёлтые пометки «подтвердить». Перед запуском поставить `false` |
| `base_url` | Можно задать явно: `https://spec.godigital.rocks` |
| `admin.email`, `admin.password_hash` | Вход в админку. Новый хэш: `php -r "echo password_hash('НОВЫЙ_ПАРОЛЬ', PASSWORD_DEFAULT), PHP_EOL;"` |
| `register_url` | Куда ведёт форма: `https://goflow.godigital.rocks/specialist/register` |
| `hero` | Ставка и УТП первого экрана: «до 3 000 ₽/час», «до 500 000 ₽/мес», «старт за 1–3 недели» |
| `trust` | Блок доверия: Рейтинг Рунета, аккредитация, Сколково, компании-клиенты |
| `ym_id`, `vk_pixel_id` | Яндекс Метрика и пиксель VK |
| `notify.*` | Уведомления о новых анкетах в Telegram-бот (внутренний) |
| `platform.*` | Синхронизация проектов с API платформы |

## Цели аналитики

`spec_cta_click`, `spec_hero_pick`, `spec_stack_pick`, `spec_project_open`, `spec_project_apply_click`, `spec_form_dir`, `spec_form_submit` (ушёл на регистрацию), `spec_form_low` (меньше 2 лет), `spec_faq_open`.

## Источники и CPA

Короткая ссылка `/r/abc123` из раздела «Ссылки для каналов» считает клики и передаёт UTM в анкету и дальше на платформу. Отчёт «Источники и CPA» показывает по каждому каналу:

- клики;
- анкеты;
- переходы на регистрацию;
- расход;
- цену анкеты и цену перехода.

Завершённые регистрации видит платформа, она получает те же UTM. Чтобы считать CPA по регистрациям прямо в админке, платформе нужно возвращать событие «зарегистрирован» с параметром `lead`.

## Синхронизация проектов с платформой

```php
'platform' => ['enabled' => true, 'url' => 'https://…/api/v1/requests?status=open', 'token' => '…', 'timeout' => 15],
```

Запуск — кнопкой в админке или по cron: `*/30 * * * * php /path/to/site/sync.php`. Формат ответа описан в `lib/projects.php` над `platform_fetch()`.

## Тексты

На лендинге нет слов «вакансия», «кандидат», «найм», «подбор», «рекрутер», «зарплата», «работодатель»: это платформа проектов, а не трудоустройство. Проверяйте это при правке текстов в админке.

## Перед запуском

- [ ] `demo => false`, примеры заменены реальными проектами
- [ ] Пароль админки сменён
- [ ] Проверено участие в «Сколково» (сейчас взято из бейджа на godigital.rocks)
- [ ] Согласовано использование названий компаний-клиентов
- [ ] `privacy.html` заменён текстом юриста
- [ ] Указаны `ym_id`; `robots.txt` и карта сайта добавлены в Яндекс Вебмастер

## Локальный запуск

```
php -S localhost:8770 -t .. ../tools/dev-router.php
# → http://localhost:8770/go-flow/
```
