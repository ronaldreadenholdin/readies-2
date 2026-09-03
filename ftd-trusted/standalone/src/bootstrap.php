<?php

declare(strict_types=1);

function ftd_root(): string
{
    return dirname(__DIR__);
}

function ftd_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (! is_string($raw) || trim($raw) === '') {
        return $_POST;
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function ftd_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/TrustedList.php';
