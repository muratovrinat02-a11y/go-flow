<?php
/* Чистка меток лендинга совпадает с правилами scripts/analytics/validate_utm.py (DT-15). Запуск: php tests/landing/test_utm.php */
declare(strict_types=1);
require __DIR__ . '/../../go-flow/lib/utm.php';

$fail = 0; $n = 0;
function ok(bool $c, string $msg): void { global $fail, $n; $n++; if (!$c) { $fail++; echo "FAIL: $msg\n"; } }

// 1. Все значения матрицы кампаний принимаются лендингом без изменений (макросы Директа — отдельно).
$matrix = __DIR__ . '/../../docs/analytics/marketing/data/CAMPAIGN_MATRIX.csv';
$h = fopen($matrix, 'r');
$head = fgetcsv($h); $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
$rows = 0;
while (($r = fgetcsv($h)) !== false) {
    $row = array_combine($head, $r); $rows++;
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_adgroup', 'utm_creative'] as $k) {
        $v = $row[$k] ?? '';
        if ($v === '') continue;
        if (preg_match('/^\{\{?[a-z_]+\}?\}$/', $v)) {
            // Макрос подставляет рекламная система; если он дошёл до лендинга сырым — это ошибка подстановки, лендинг его отбрасывает.
            ok(utm_clean($k, $v)[0] === null, "{$row['id']} $k=$v: сырой макрос должен отбрасываться");
            continue;
        }
        ok(utm_clean($k, $v)[0] === $v, "{$row['id']} $k=$v должен приниматься как есть");
    }
    ok(utm_validate_link($row + ['utm_term' => '']) === [] || preg_match('/\{/', $row['utm_adgroup'] . $row['utm_creative']), "{$row['id']}: проверка ссылки из админки должна проходить");
    ok(isset(UTM_MEDIUMS[$row['utm_medium']]), "{$row['id']}: utm_medium={$row['utm_medium']} есть в словаре лендинга");
}
fclose($h);
ok($rows >= 20, "в матрице $rows строк");

// 2. Словарь medium лендинга = словарь валидатора.
preg_match('/MEDIUMS = \{([^}]+)\}/s', file_get_contents(__DIR__ . '/../../scripts/analytics/validate_utm.py'), $m);
preg_match_all('/"([a-z_]+)"/', $m[1], $mm);
$py = $mm[1]; sort($py);
$php = array_keys(UTM_MEDIUMS); sort($php);
ok($py === $php, 'словарь utm_medium совпадает с validate_utm.py: py=' . implode(',', $py) . ' php=' . implode(',', $php));

// 3. Отказы: контакты, URL, недопустимые символы, длина.
$bad = [
    ['utm_content', 'ivan@mail.ru', 'pii'], ['utm_content', '+7 999 123-45-67', 'pii'], ['utm_term', 'https://t.me/x', 'pii'],
    ['utm_source', 'www.site.ru', 'pii'], ['utm_campaign', 'java launch', 'format'], ['utm_source', 'tg-python', 'format'],
    ['utm_content', str_repeat('a', 101), 'format'], ['utm_medium', 'Пост', 'format'], ['yclid', 'abc def', 'format'],
];
foreach ($bad as [$k, $v, $why]) ok(utm_clean($k, $v) === [null, $why], "$k=" . substr($v, 0, 20) . " → $why");

// 4. Допустимое: регистр приводится к нижнему, yclid из цифр не считается телефоном, пустое = метки нет.
ok(utm_clean('utm_source', 'Telegram') === ['telegram', null], 'Telegram → telegram');
ok(utm_clean('yclid', '1234567890123456789') === ['1234567890123456789', null], 'yclid из цифр принимается');
ok(utm_clean('utm_source', '') === [null, null], 'пустое значение = метки нет');
ok(utm_clean('utm_content', str_repeat('a', 100))[0] !== null, '100 символов — можно');

// 5. Проверка ссылки из админки.
ok(utm_validate_link(['utm_source' => 'telegram', 'utm_medium' => 'paid_social', 'utm_campaign' => '2026q4_specialists_java_launch']) === [], 'корректная ссылка');
ok(count(utm_validate_link(['utm_source' => 'telegram', 'utm_medium' => 'seeding', 'utm_campaign' => 'python'])) === 2, 'medium вне словаря и кампания не по формату');
ok(utm_validate_link(['utm_source' => 'internal_test', 'utm_medium' => 'test', 'utm_campaign' => 'test_2026q4_specialists_smoke']) === [], 'тестовая ссылка');

echo $fail ? "test_utm: $fail из $n проверок упали\n" : "test_utm: OK, $n проверок\n";
exit($fail ? 1 : 0);
