<?php
/* Уведомление о новой заявке в Telegram (опционально: notify.tg_bot_token и notify.tg_chat_id). */
declare(strict_types=1);

function notify_application(int $id): void
{
    $token = (string)cfg('notify.tg_bot_token', '');
    $chat = (string)cfg('notify.tg_chat_id', '');
    if ($token === '' || $chat === '') return;
    $st = db()->prepare('SELECT a.*, p.role, p.grade FROM applications a LEFT JOIN projects p ON p.id = a.project_id WHERE a.id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) return;
    $icon = $a['score'] === 'pass' ? '🟢' : '⚪️';
    $text = $icon . ' Анкета #' . $id . ' · ' . role_label($a['direction'])
        . ' · ' . answer_label('years', $a['years'])
        . ($a['score'] === 'pass' ? ' → ушёл на регистрацию' : ' (меньше 2 лет)')
        . ($a['role'] ? "\nПроект: " . trim($a['grade'] . ' ' . $a['role']) : '')
        . "\nИсточник: " . ($a['utm_source'] ?: 'без UTM') . ($a['utm_campaign'] ? ' / ' . $a['utm_campaign'] : '')
        . "\n" . base_url() . '/admin/?page=application&id=' . $id;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 4, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode(['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => true], JSON_UNESCAPED_UNICODE),
    ]]);
    @file_get_contents('https://api.telegram.org/bot' . $token . '/sendMessage', false, $ctx);
}
