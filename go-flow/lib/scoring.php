<?php
/* Анкета на лендинге: стек и опыт. Порог — 2 года на стеке, дальше человек идёт на регистрацию на платформе. */
declare(strict_types=1);

const YEARS_OPTIONS = ['lt2' => 'Меньше 2 лет', '2-5' => '2–5 лет', '6+' => '6 лет и больше'];

/** pass — уходит на регистрацию, fail — меньше 2 лет на стеке. */
function score_application(array $a): array
{
    if (($a['years'] ?? '') === 'lt2') return ['fail', ['Меньше 2 лет на стеке']];
    return ['pass', []];
}

function answer_label(string $field, ?string $v): string
{
    if ($v === null || $v === '') return '—';
    if ($field === 'years') return YEARS_OPTIONS[$v] ?? $v;
    return $v;
}
