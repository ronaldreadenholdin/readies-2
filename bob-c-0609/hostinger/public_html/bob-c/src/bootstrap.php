<?php

declare(strict_types=1);

function bob_c_root(): string
{
    return dirname(__DIR__);
}

function bob_c_load_env(string $path): void
{
    if (! is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function bob_c_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function bob_c_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (! is_string($raw) || trim($raw) === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function bob_c_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bob_c_require_token(): void
{
    $expected = bob_c_env('BOB_C_ACCESS_TOKEN');
    if ($expected === null) {
        return;
    }

    $provided = $_SERVER['HTTP_X_BOB_C_TOKEN']
        ?? $_GET['token']
        ?? $_POST['token']
        ?? (bob_c_json_input()['token'] ?? null);

    if (! is_string($provided) || ! hash_equals($expected, $provided)) {
        bob_c_json([
            'ok' => false,
            'error' => 'BOB C is protected. Add the access token.',
        ], 401);
    }
}

bob_c_load_env(bob_c_root() . '/.env');

require_once __DIR__ . '/BobGClient.php';
require_once __DIR__ . '/ConversationStore.php';
