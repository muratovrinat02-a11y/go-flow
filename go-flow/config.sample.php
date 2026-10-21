<?php
/* Скопируйте в config.php и заполните. config.php не коммитится в git. */
return [
    // Макет: жёлтые пометки «подтвердить». Перед запуском — false.
    'demo' => true,

    // Пусто — адрес определяется сам. Можно задать явно: 'https://murx.io/go-flow'
    'base_url' => '',
    // Красивые адреса /p/slug и /r/code (нужен .htaccess / правило nginx). false — project.php?slug=…
    'pretty_urls' => true,

    // База SQLite. Безопаснее вынести за пределы публичной папки, например '/home/USER/goflow-data/goflow.sqlite'
    'db_path' => __DIR__ . '/data/goflow.sqlite',
    // Создать примеры карточек при пустой базе.
    'seed_examples' => true,

    // Вход в /admin. Хэш пароля: php -r "echo password_hash('ПАРОЛЬ', PASSWORD_DEFAULT), PHP_EOL;"
    'admin' => [
        'email' => 'admin@example.com',
        'password_hash' => '',
    ],

    // Аналитика
    'ym_id' => '',
    'vk_pixel_id' => '',
    'analytics_after_consent' => false,

    // Регистрация специалиста на платформе: сюда ведёт форма (с UTM и параметрами анкеты).
    'register_url' => 'https://goflow.godigital.rocks/start',

    // Первый экран: ставка и УТП.
    'hero' => [
        'rate' => '3 000',          // «до 3 000 ₽/час»
        'month' => '450 000',       // «до 450 000 ₽/мес»
        'start' => '1–3 недели',    // «старт проекта за 1–3 недели»
    ],

    // Доверие: факты о GO Digital для блока «Кто стоит за платформой».
    'trust' => [
        'rating' => 'Рейтинг Рунета «Аутстаффинг» 2025: 10-е место в Москве, 22-е в России',
        'rating_url' => 'https://ratingruneta.ru/outstaffing/moscow/2025/',
        'accredited' => true,
        'skolkovo' => true,          // на godigital.rocks бейдж «Sk Участник»; проверить в реестре фонда
        'clients' => ['Сбер', 'Т-Банк', 'Рокетбанк', 'Wildberries', 'МТС', 'Лаборатория Касперского', 'Газпром'],
    ],

    // Уведомления о заявках в Telegram: токен бота от @BotFather и id чата.
    'notify' => ['tg_bot_token' => '', 'tg_chat_id' => ''],

    // Синхронизация проектов с платформой GO Flow (см. README → «API платформы»).
    'platform' => ['enabled' => false, 'url' => '', 'token' => '', 'timeout' => 15],

    // Для подвала и пометки «Реклама» в постах.
    'operator' => ['name' => 'GO Digital', 'ad_name' => 'ООО «КлинЭнджин»', 'inn' => '1615013503'],

];
