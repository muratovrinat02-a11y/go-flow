<?php
/* SQLite: подключение, схема, стартовые данные. */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = (string)cfg('db_path', APP_ROOT . '/data/goflow.sqlite');
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS projects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'draft',          -- draft | open | paused | closed
  source TEXT NOT NULL DEFAULT 'manual',         -- manual | platform
  external_id TEXT,                              -- id запроса на платформе GO Flow
  sync_lock INTEGER NOT NULL DEFAULT 0,          -- 1: синхронизация не перезаписывает ручные правки
  is_example INTEGER NOT NULL DEFAULT 0,
  direction TEXT NOT NULL DEFAULT 'python',
  role TEXT NOT NULL,
  grade TEXT NOT NULL DEFAULT 'Senior',
  domain TEXT NOT NULL DEFAULT '',
  summary TEXT NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  stack_required TEXT NOT NULL DEFAULT '[]',
  stack_nice TEXT NOT NULL DEFAULT '[]',
  tasks TEXT NOT NULL DEFAULT '',
  requirements TEXT NOT NULL DEFAULT '',
  conditions TEXT NOT NULL DEFAULT '',
  process TEXT NOT NULL DEFAULT '',
  format TEXT NOT NULL DEFAULT 'Удалённо',
  workload TEXT NOT NULL DEFAULT '',
  duration TEXT NOT NULL DEFAULT '',
  start_date TEXT NOT NULL DEFAULT '',
  team TEXT NOT NULL DEFAULT '',
  rate_min INTEGER,
  rate_max INTEGER,
  rate_note TEXT NOT NULL DEFAULT '',
  sort INTEGER NOT NULL DEFAULT 100,
  published_at TEXT,
  synced_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE UNIQUE INDEX IF NOT EXISTS projects_ext ON projects(source, external_id) WHERE external_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS applications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT NOT NULL UNIQUE,
  stage TEXT NOT NULL DEFAULT 'step1',          -- step1 | step2
  status TEXT NOT NULL DEFAULT 'new',           -- new | review | invited | rejected | archived
  score TEXT,                                   -- pass | border | fail
  score_reasons TEXT NOT NULL DEFAULT '[]',
  project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  direction TEXT, years TEXT, contact TEXT,
  consent_pd INTEGER NOT NULL DEFAULT 0, consent_ads INTEGER NOT NULL DEFAULT 0,
  stack TEXT NOT NULL DEFAULT '[]',
  handson TEXT, tenure TEXT, legal TEXT, overlap TEXT, rate INTEGER, workload TEXT, resume TEXT,
  utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_content TEXT, utm_term TEXT,
  yclid TEXT, link_code TEXT, referrer TEXT, landing_url TEXT, variant TEXT, page_dir TEXT,
  user_agent TEXT, notes TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS app_created ON applications(created_at);
CREATE INDEX IF NOT EXISTS app_source ON applications(utm_source, utm_campaign);

CREATE TABLE IF NOT EXISTS links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL DEFAULT '',
  utm_source TEXT NOT NULL,
  utm_medium TEXT NOT NULL DEFAULT 'outreach',
  utm_campaign TEXT NOT NULL DEFAULT '',
  utm_content TEXT NOT NULL DEFAULT '',
  project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  paid INTEGER NOT NULL DEFAULT 0,
  cost INTEGER NOT NULL DEFAULT 0,
  erid TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS clicks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  link_code TEXT NOT NULL,
  visitor TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS clicks_code ON clicks(link_code, created_at);

CREATE TABLE IF NOT EXISTS login_attempts (
  ip TEXT NOT NULL,
  created_at INTEGER NOT NULL
);
SQL);

    if ((int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 0 && cfg('seed_examples', true)) {
        seed_projects($pdo);
    }
}

/** Примеры карточек, пока нет реальных проектов. Помечены is_example = 1. */
function seed_projects(PDO $pdo): void
{
    $common = [
        'conditions' => "Удалённо\nДоговор с самозанятым или ИП, расчёты в рублях\nОплата по отчётам о часах",
        'process' => "Регистрация и подтверждение опыта на платформе (один раз)\nЗнакомство с заказчиком\nСтарт проекта",
    ];
    $rows = [
        ['python', 'Backend-разработчик', 'Финтех', [2000, 3000], ['Python', 'FastAPI', 'PostgreSQL', 'Docker'], ['Kafka', 'Redis'],
            'Развитие платёжного бэкенда: новые API и интеграции.',
            "Проектирование и разработка REST API на FastAPI\nИнтеграции с внешними платёжными сервисами\nОптимизация запросов к PostgreSQL\nCode review и менторинг middle-разработчиков",
            "От 3 лет коммерческой разработки на Python\nAsyncio, FastAPI или аналог\nPostgreSQL: индексы, планы запросов\nDocker, CI/CD"],
        ['go', 'Backend-разработчик', 'E-commerce', [1500, 2200], ['Go', 'PostgreSQL', 'Kafka'], ['Kubernetes', 'gRPC'],
            'Высоконагруженный сервис каталога и заказов.',
            "Разработка микросервисов на Go\nРабота с событиями в Kafka\nПрофилирование и снижение latency",
            "От 3 лет в бэкенде, из них заметная часть на Go\nОпыт highload\nKafka в продакшене"],
        ['dotnet', '.NET-разработчик', 'Банк', [1500, 2200], ['C#', '.NET', 'PostgreSQL'], ['ASP.NET Core', 'RabbitMQ'],
            'Модернизация внутренних сервисов банка.',
            "Разработка сервисов на .NET 8\nПеренос легаси-логики в новые сервисы\nНаписание тестов",
            "От 3 лет на C# / .NET\nASP.NET Core, EF Core\nОпыт работы с банковскими системами — плюс"],
        ['react', 'Frontend-разработчик', 'Телеком', [1500, 2200], ['React', 'TypeScript', 'Next.js'], ['Redux', 'Storybook'],
            'Личный кабинет клиента: новые разделы и дизайн-система.',
            "Разработка интерфейсов на React и TypeScript\nРазвитие дизайн-системы\nПроизводительность и доступность",
            "От 3 лет во фронтенде\nReact, TypeScript\nSSR на Next.js"],
        ['angular', 'Frontend-разработчик', 'Госсектор', [1500, 2200], ['Angular', 'TypeScript', 'RxJS'], ['NgRx'],
            'Портал с формами и личным кабинетом.',
            "Разработка модулей на Angular\nРабота со сложными формами\nИнтеграция с REST API",
            "От 3 лет во фронтенде\nAngular 15+\nRxJS"],
        ['sa', 'Системный аналитик', 'Ритейл', [1500, 2200], ['BPMN', 'UML', 'REST', 'SQL'], ['Kafka'],
            'Интеграции между учётными системами и маркетплейсами.',
            "Сбор и формализация требований\nОписание API и интеграций\nПостановка задач разработке",
            "От 3 лет в системном анализе\nОписание REST API, SQL\nBPMN, UML"],
        ['ba', 'Бизнес-аналитик', 'Логистика', [1500, 2200], ['BPMN', 'User Stories', 'SQL'], ['BI'],
            'Оптимизация процессов складской логистики.',
            "Анализ и моделирование процессов\nРабота с заказчиком и бэклогом\nПодготовка требований к доработкам",
            "От 3 лет в бизнес-анализе\nBPMN, User Stories\nSQL на уровне выгрузок"],
    ];
    $st = $pdo->prepare('INSERT INTO projects (slug,status,is_example,direction,role,grade,domain,summary,description,stack_required,stack_nice,tasks,requirements,conditions,process,format,workload,duration,rate_min,rate_max,sort,published_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime(\'now\',\'localtime\'))');
    foreach ($rows as $i => $r) {
        [$dir, $role, $domain, $rate, $req, $nice, $summary, $tasks, $reqs] = $r;
        $st->execute([
            $dir . '-' . ($i + 1), 'open', 1, $dir, $role, 'Senior', $domain, $summary,
            'Пример карточки. Реальное описание проекта подставит команда GO Digital.',
            json_encode($req, JSON_UNESCAPED_UNICODE), json_encode($nice, JSON_UNESCAPED_UNICODE),
            $tasks, $reqs, $common['conditions'], $common['process'], 'Удалённо', '40 ч/нед', 'от 6 месяцев', $rate[0], $rate[1], ($i + 1) * 10,
        ]);
    }
}
