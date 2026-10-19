<?php
/* Синхронизация проектов с платформой GO Flow. Запуск по cron: php /path/to/go-flow/sync.php */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/lib/bootstrap.php';
try {
    $s = platform_sync();
    echo date('c') . " sync ok: +{$s['created']} ~{$s['updated']} closed {$s['closed']} skipped {$s['skipped']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' sync error: ' . $e->getMessage() . "\n");
    exit(1);
}
