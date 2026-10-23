<?php
/* Анкета на лендинге: специальность и опыт. Порог — 2 года по специальности, дальше человек идёт на регистрацию на платформе. */
declare(strict_types=1);

/** Специальности в мини-анкете: уровнем выше стеков, чтобы никто не отвалился. */
const ROLES = [
    'dev'      => ['label' => 'Разработчик',            'specs' => ['Backend', 'Frontend', 'Fullstack', 'Mobile', 'Python', 'Go', 'Java', '.NET', 'JS / TS', 'PHP', '1С']],
    'analyst'  => ['label' => 'Аналитик',               'specs' => ['Системный', 'Бизнес', 'Data / BI', 'Продуктовый']],
    'qa'       => ['label' => 'Тестировщик (QA)',       'specs' => ['Ручное', 'Автоматизация', 'Нагрузочное']],
    'devops'   => ['label' => 'DevOps / SRE',           'specs' => ['Kubernetes', 'CI/CD', 'Облака', 'Мониторинг']],
    'ml'       => ['label' => 'ML / Data Science',      'specs' => ['ML-инженер', 'Data Scientist', 'Data-инженер', 'LLM / NLP', 'CV']],
    'designer' => ['label' => 'Дизайнер',               'specs' => ['UX/UI', 'Продуктовый', 'Графический']],
    'pm'       => ['label' => 'Проджект / продакт',     'specs' => ['Project', 'Product', 'Delivery', 'Scrum-мастер']],
    'other'    => ['label' => 'Другое',                 'specs' => ['Архитектор', 'Тимлид', 'Техписатель', 'Безопасность']],
];

/** Подпись специальности по ключу (в том числе старые ключи направлений из первых версий анкеты). */
function role_label(?string $key): string
{
    if ($key === null || $key === '') return '—';
    return ROLES[$key]['label'] ?? (DIRECTIONS[$key]['label'] ?? $key);
}

const YEARS_OPTIONS = ['lt2' => 'Меньше 2 лет', '2-5' => '2–5 лет', '6+' => '6 лет и больше'];

/** pass — уходит на регистрацию, fail — меньше 2 лет на стеке. */
function score_application(array $a): array
{
    if (($a['years'] ?? '') === 'lt2') return ['fail', ['Меньше 2 лет по специальности']];
    return ['pass', []];
}

function answer_label(string $field, ?string $v): string
{
    if ($v === null || $v === '') return '—';
    if ($field === 'years') return YEARS_OPTIONS[$v] ?? $v;
    return $v;
}
