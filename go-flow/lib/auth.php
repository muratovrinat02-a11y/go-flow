<?php
/* Вход в админку: сессия, CSRF, ограничение попыток. */
declare(strict_types=1);

function admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('gf_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => (base_path() ?: '') . '/admin', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function admin_logged_in(): bool
{
    admin_session();
    if (empty($_SESSION['admin']) || ($_SESSION['admin_ttl'] ?? 0) < time()) return false;
    $_SESSION['admin_ttl'] = time() + 8 * 3600;
    return true;
}

function csrf_token(): string
{
    admin_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Сессия устарела. Обновите страницу и повторите.');
    }
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function login_blocked(): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?');
    $st->execute([client_ip(), time() - 900]);
    return (int)$st->fetchColumn() >= 5;
}

function admin_login(string $email, string $password): bool
{
    $okEmail = hash_equals(mb_strtolower((string)cfg('admin.email', '')), mb_strtolower(trim($email)));
    $hash = (string)cfg('admin.password_hash', '');
    $okPass = $hash !== '' && password_verify($password, $hash);
    if (!$okEmail || !$okPass) {
        db()->prepare('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)')->execute([client_ip(), time()]);
        db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 86400]);
        usleep(400000);
        return false;
    }
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_ttl'] = time() + 8 * 3600;
    return true;
}

function admin_logout(): void
{
    admin_session();
    $_SESSION = [];
    session_destroy();
}
