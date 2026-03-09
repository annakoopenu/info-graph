<?php

declare(strict_types=1);

/**
 * Error handling bootstrap.
 * Dev mode: display errors. Prod mode: log only, show generic page.
 */
function initErrorHandling(): void
{
    $isDev = env('APP_ENV', 'dev') === 'dev';

    if ($isDev) {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        error_reporting(E_ALL);
        ini_set('log_errors', '1');
    }

    set_exception_handler(function (\Throwable $e) use ($isDev) {
        http_response_code(500);
        if ($isDev) {
            echo '<h1>Error</h1>';
            echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n";
            echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        } else {
            error_log($e->getMessage() . "\n" . $e->getTraceAsString());
            echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
        }
    });
}
