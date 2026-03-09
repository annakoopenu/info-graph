<?php

declare(strict_types=1);

/**
 * Load .env file into $_ENV / getenv().
 * Lightweight: no external dependency needed.
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

/**
 * Base path for the app (e.g. '/info-graph').
 * All generated URLs/redirects must use this prefix.
 */
function basePath(): string
{
    return env('APP_BASE_PATH', '/info-graph');
}

/** Generate a full URL path with the base prefix. */
function url(string $path = '/'): string
{
    $base = rtrim(basePath(), '/');
    if ($path === '' || $path === '/') {
        return $base ?: '/';
    }
    return $base . '/' . ltrim($path, '/');
}
