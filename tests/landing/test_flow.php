<?php
/* Сквозной сценарий DT-03 на синтетике: ссылка → лендинг → анкета → ссылка на регистрацию. Нужен запущенный сервер (см. run.sh). */
declare(strict_types=1);
$B = getenv('GF_BASE') ?: 'http://127.0.0.1:8791/go-flow';
$fail = 0; $n = 0;
function ok(bool $c, string $msg): void { global $fail, $n; $n++; if (!$c) { $fail++; echo "FAIL: $msg\n"; } }
function post(string $url, array $data): array
{
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => json_encode($data), 'ignore_errors' => true]]);
    return json_decode((string)file_get_contents($url, false, $ctx), true) ?: [];
}
function q(string $url): array { parse_str((string)parse_url($url, PHP_URL_QUERY), $q); return $q; }

$m01 = ['utm_source' => 'telegram', 'utm_medium' => 'paid_social', 'utm_campaign' => '2026q4_specialists_java_launch',
        'utm_content' => 'post_a', 'utm_adgroup' => 'tgch_java_a', 'utm_creative' => 'post_a', 'yclid' => '1234567890123'];

// 1. M01: все метки и yclid доходят до ссылки регистрации без изменений.
$r = post("$B/api/apply.php", ['direction' => 'dev', 'years' => '2-5', 'landing_url' => 'https://spec.godigital.rocks/?utm_source=telegram'] + $m01);
ok(($r['score'] ?? '') === 'pass', 'M01: анкета проходит');
$rq = q($r['redirect'] ?? '');
ok(str_starts_with($r['redirect'] ?? '', 'https://goflow.godigital.rocks/specialist/register?'), 'M01: ведёт на регистрацию специалиста');
foreach ($m01 as $k => $v) ok(($rq[$k] ?? null) === $v, "M01: $k=$v передан в регистрацию");
ok(($rq['landing'] ?? '') === 'spec', 'M01: landing=spec');
ok(!isset($rq['ref']), 'M01: параметр ref свободен под реферальную программу (DT-09)');

// 2. Прямой заход без меток: источник не придумывается.
$r = post("$B/api/apply.php", ['direction' => 'analyst', 'years' => '6+']);
$rq = q($r['redirect'] ?? '');
ok(!isset($rq['utm_source']) && !isset($rq['utm_medium']) && !isset($rq['utm_campaign']), 'прямой заход: utm_* не подставляются');

// 3. Метки с персональными данными и мусором не передаются, остальные передаются.
$r = post("$B/api/apply.php", ['direction' => 'qa', 'years' => '2-5', 'utm_source' => 'hh', 'utm_medium' => 'outreach',
    'utm_campaign' => '2026q4_specialists_java_launch', 'utm_content' => 'ivan@mail.ru', 'utm_term' => '+7 999 123 45 67']);
$rq = q($r['redirect'] ?? '');
ok(!isset($rq['utm_content']) && !isset($rq['utm_term']), 'контакты в метках отброшены');
ok(($rq['utm_source'] ?? '') === 'hh' && ($rq['utm_medium'] ?? '') === 'outreach', 'остальные метки на месте');

// 4. Меньше 2 лет: на регистрацию не ведём.
$r = post("$B/api/apply.php", ['direction' => 'dev', 'years' => 'lt2'] + $m01);
ok(($r['score'] ?? '') === 'fail' && empty($r['redirect']), 'меньше 2 лет: без перехода на регистрацию');

// 5. Короткая ссылка /r/<code> из админки ведёт на лендинг со всеми метками.
$code = getenv('GF_LINK_CODE');
if ($code) {
    $ctx = stream_context_create(['http' => ['follow_location' => 0, 'ignore_errors' => true, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
    @file_get_contents("$B/r/$code", false, $ctx);
    $loc = '';
    foreach ($http_response_header ?? [] as $hdr) if (stripos($hdr, 'Location:') === 0) $loc = trim(substr($hdr, 9));
    $lq = q($loc);
    foreach (['utm_source' => 'telegram', 'utm_medium' => 'paid_social', 'utm_campaign' => '2026q4_specialists_java_launch', 'utm_adgroup' => 'tgch_java_a', 'utm_creative' => 'post_a'] as $k => $v)
        ok(($lq[$k] ?? null) === $v, "короткая ссылка: $k=$v");
    ok(($lq['lc'] ?? '') === $code, 'короткая ссылка: lc передан');
}

echo $fail ? "test_flow: $fail из $n проверок упали\n" : "test_flow: OK, $n проверок\n";
exit($fail ? 1 : 0);
