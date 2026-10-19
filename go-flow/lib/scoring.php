<?php
/* Скоринг анкеты. Правила — в config.php → scoring, после брифа их заполнит команда GO Digital. */
declare(strict_types=1);

/** Правила по умолчанию — критерии пилота из ТЗ. */
function scoring_rules(): array
{
    $default = [
        // Ответы, при которых анкета сразу не проходит.
        'knockout' => [
            'years' => ['lt3', '3-5'],
        ],
        // Штрафные баллы за ответ. Сумма 0 — проходит, до border_max — ручная проверка, выше — не проходит.
        'penalties' => [
            'handson' => ['18-24' => 0, '12-17' => 1, 'lt12' => 2],
            'tenure'  => ['36+' => 0, '18-35' => 0, '12-17' => 1, 'lt12' => 2],
            'legal'   => ['npd_ru' => 0, 'ip_ru' => 0, 'other' => 1, 'none' => 1],
            'overlap' => ['full' => 0, '6+' => 0, '4-5' => 1, 'lt4' => 2],
        ],
        'border_max' => 1,
    ];
    $custom = cfg('scoring');
    return is_array($custom) ? array_replace_recursive($default, $custom) : $default;
}

const SCORING_LABELS = [
    'years' => 'Лет на стеке', 'handson' => 'Практика за 24 мес', 'tenure' => 'Средний срок на месте',
    'legal' => 'Оформление', 'overlap' => 'Пересечение с МСК',
];

/** @return array{0:string,1:array} [результат, причины] */
function score_application(array $a): array
{
    $r = scoring_rules();
    $reasons = [];
    foreach ($r['knockout'] as $field => $bad) {
        if (in_array($a[$field] ?? null, $bad, true)) {
            return ['fail', [(SCORING_LABELS[$field] ?? $field) . ': ' . ($a[$field] ?? '—') . ' (стоп-фактор)']];
        }
    }
    if (($a['stage'] ?? 'step2') === 'step1') return ['', []];
    $sum = 0;
    foreach ($r['penalties'] as $field => $map) {
        $v = $a[$field] ?? null;
        $p = (int)($map[$v] ?? 0);
        if ($p > 0) $reasons[] = (SCORING_LABELS[$field] ?? $field) . ': ' . $v . ' (+' . $p . ')';
        $sum += $p;
    }
    $res = $sum === 0 ? 'pass' : ($sum <= (int)$r['border_max'] ? 'border' : 'fail');
    return [$res, $reasons];
}

const ANSWER_LABELS = [
    'years' => ['lt3' => 'меньше 3', '3-5' => '3–5', '6-8' => '6–8', '9+' => '9+'],
    'handson' => ['18-24' => '18–24 мес', '12-17' => '12–17 мес', 'lt12' => 'меньше 12'],
    'tenure' => ['lt12' => '<12 мес', '12-17' => '12–17 мес', '18-35' => '18–35 мес', '36+' => '36+ мес'],
    'legal' => ['npd_ru' => 'Самозанятый РФ', 'ip_ru' => 'ИП РФ', 'other' => 'Другое', 'none' => 'Не оформлен'],
    'overlap' => ['full' => 'Весь день', '6+' => '6+ ч', '4-5' => '4–5 ч', 'lt4' => 'меньше 4 ч'],
    'workload' => ['full' => 'Полная', '20-30' => '20–30 ч/нед', 'lt20' => '<20 ч/нед'],
];

function answer_label(string $field, ?string $v): string
{
    if ($v === null || $v === '') return '—';
    return ANSWER_LABELS[$field][$v] ?? $v;
}
