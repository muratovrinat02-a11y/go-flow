<?php
/* Метки кампаний: какие принимаем, как чистим. Правила совпадают со scripts/analytics/validate_utm.py. */
declare(strict_types=1);

/** Метки, которые лендинг принимает из ссылки и передаёт в регистрацию. Порядок = порядок в ссылке. */
const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_adgroup', 'utm_creative', 'yclid'];

/** Словарь utm_medium (docs/analytics/marketing/data/UTM_DICTIONARY.csv). */
const UTM_MEDIUMS = [
    'paid_social' => 'Платный пост / VK Ads (paid_social)',
    'social' => 'Свой неоплаченный пост (social)',
    'outreach' => 'Личный аутрич: TG DM, hh (outreach)',
    'job_board' => 'Вакансия / биржа (job_board)',
    'content' => 'Статья: Habr, vc (content)',
    'email' => 'Рассылка по своей базе (email)',
    'referral_program' => 'Реферальная программа (referral_program)',
    'cpc' => 'Поисковая реклама (cpc)',
    'display' => 'РСЯ, гео-таргет (display)',
    'affiliate' => 'Оплачиваемый партнёр (affiliate)',
    'test' => 'Тест, не в KPI (test)',
];

const UTM_CODE_RE = '/^[a-z0-9_]{1,100}$/';
const UTM_CAMPAIGN_RE = '/^(test_)?\d{4}q[1-4]_(specialists|customers)_[a-z0-9_]+$/';
const UTM_YCLID_RE = '/^[A-Za-z0-9_-]{1,100}$/';

/** Похоже на контакт или ссылку: e-mail, телефон, URL. Те же шаблоны, что в валидаторе. */
function utm_looks_like_pii(string $v): bool
{
    return (bool)(preg_match('/@/', $v) || preg_match('/\+?\d[\d\s\-()]{9,}/', $v) || preg_match('#https?://|www\.#i', $v));
}

/**
 * Чистит значение метки из ссылки.
 * Возвращает [значение|null, причина отказа|null]. Пустое значение — [null, null] (метки не было).
 */
function utm_clean(string $key, $raw): array
{
    $v = trim((string)$raw);
    if ($v === '') return [null, null];
    if ($key === 'yclid') {
        // Идентификатор клика Директа: цифры, не проверяем на «телефон».
        return preg_match(UTM_YCLID_RE, $v) ? [$v, null] : [null, 'format'];
    }
    if (utm_looks_like_pii($v)) return [null, 'pii'];
    $v = strtolower($v);
    if (!preg_match(UTM_CODE_RE, $v)) return [null, 'format'];
    return [$v, null];
}

/** Чистит все метки разом. Возвращает [метки, отклонённые ключи => причина]. */
function utm_clean_all(array $in): array
{
    $out = []; $rejected = [];
    foreach (UTM_FIELDS as $k) {
        [$v, $why] = utm_clean($k, $in[$k] ?? '');
        if ($v !== null) $out[$k] = $v;
        if ($why !== null) $rejected[$k] = $why;
    }
    return [$out, $rejected];
}

/** Проверка ссылки из админки: те же правила, что у валидатора матрицы кампаний. Возвращает список ошибок. */
function utm_validate_link(array $l): array
{
    $err = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $k) {
        if (trim((string)($l[$k] ?? '')) === '') $err[] = "$k: обязательное поле";
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_adgroup', 'utm_creative'] as $k) {
        $v = (string)($l[$k] ?? '');
        if ($v === '') continue;
        if (utm_looks_like_pii($v)) $err[] = "$k: похоже на контакт или URL";
        elseif (!preg_match(UTM_CODE_RE, $v)) $err[] = "$k: только a-z, 0-9, _ и до 100 символов";
    }
    if (($l['utm_medium'] ?? '') !== '' && !isset(UTM_MEDIUMS[$l['utm_medium']])) $err[] = 'utm_medium: нет в словаре';
    if (($l['utm_campaign'] ?? '') !== '' && !preg_match(UTM_CAMPAIGN_RE, $l['utm_campaign'])) $err[] = 'utm_campaign: формат 2026q4_specialists_название';
    return $err;
}
