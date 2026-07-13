<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use AfrPay\Services\AfrPayAdaptor;
use AfrPay\Services\GoLiveGate;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rtrim($uri, '/') ?: '/';

$connections = require __DIR__.'/config.php';

function adaptor_for(string $code, array $connections): AfrPayAdaptor
{
    $code = strtoupper($code);
    if (! isset($connections[$code])) {
        json_response(['status' => 'failed', 'failure_message' => "Unknown connection {$code}"], 404);
    }

    return new AfrPayAdaptor($code, $connections[$code]);
}

function require_fields(array $body, array $fields): void
{
    foreach ($fields as $field) {
        if (! isset($body[$field]) || $body[$field] === '') {
            json_response([
                'status' => 'failed',
                'failure_code' => 'VALIDATION',
                'failure_message' => "Missing field: {$field}",
            ], 422);
        }
    }
}

try {
    // Home page
    if ($uri === '/' && $method === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__.'/status.html');
        exit;
    }

    if ($uri === '/api/afrpay/status' && $method === 'GET') {
        json_response([
            'provider' => 'AfrPay',
            'host' => 'hostinger-standalone',
            'go_live' => GoLiveGate::status(),
            'connections' => array_keys($connections),
            'endpoints' => [
                'POST /api/afrpay/OR001/payments',
                'GET /api/afrpay/OR001/payments/{ref}',
                'POST /api/afrpay/OR001/refunds',
                'POST /api/afrpay/OB003/payments',
                'GET /api/afrpay/OB003/payments/{ref}',
                'POST /api/afrpay/OB003/refunds',
                'POST /api/afrpay/go-live/approve',
                'POST /webhooks/afrpay/OR001',
                'POST /webhooks/afrpay/OB003',
            ],
        ]);
    }

    if ($uri === '/api/afrpay/go-live/approve' && $method === 'POST') {
        $body = read_json_body();
        $approvedBy = (string) ($body['approved_by'] ?? '');
        if ($approvedBy === '') {
            json_response(['status' => 'failed', 'failure_message' => 'approved_by is required'], 422);
        }
        $enableLive = ! empty($body['enable_live']);
        $or001 = array_key_exists('or001', $body) ? (bool) $body['or001'] : true;
        $ob003 = array_key_exists('ob003', $body) ? (bool) $body['ob003'] : true;

        $lines = [
            'AFRPAY_TEST_APPROVED=true',
            'AFRPAY_TEST_APPROVED_BY='.$approvedBy,
            'AFRPAY_TEST_APPROVED_AT='.gmdate('c'),
        ];
        if ($or001) {
            $lines[] = 'AFRPAY_OR001_TEST_APPROVED=true';
        }
        if ($ob003) {
            $lines[] = 'AFRPAY_OB003_TEST_APPROVED=true';
        }
        if ($enableLive) {
            $lines[] = 'AFRPAY_LIVE_ENABLED=true';
        }

        json_response([
            'status' => 'ok',
            'message' => 'Copy these lines into public_html/.env on Hostinger, save, then retry API calls.',
            'env' => $lines,
            'current' => GoLiveGate::status(),
        ]);
    }

    if (preg_match('#^/api/afrpay/(OR001|OB003|or001|ob003)/payments$#', $uri, $m) && $method === 'POST') {
        $code = strtoupper($m[1]);
        $body = read_json_body();
        require_fields($body, [
            'merchant_reference', 'customer_reference', 'amount_minor', 'currency',
            'success_url', 'failure_url', 'idempotency_key',
        ]);
        $result = adaptor_for($code, $connections)->createPayment($body);
        json_response(array_merge(['connection' => $code], $result), $result['failure_code'] ? 422 : 200);
    }

    if (preg_match('#^/api/afrpay/(OR001|OB003|or001|ob003)/payments/([^/]+)$#', $uri, $m) && $method === 'GET') {
        $code = strtoupper($m[1]);
        $ref = urldecode($m[2]);
        $result = adaptor_for($code, $connections)->paymentStatus($ref);
        json_response(array_merge(['connection' => $code], $result), $result['failure_code'] ? 422 : 200);
    }

    if (preg_match('#^/api/afrpay/(OR001|OB003|or001|ob003)/refunds$#', $uri, $m) && $method === 'POST') {
        $code = strtoupper($m[1]);
        $body = read_json_body();
        require_fields($body, ['provider_reference', 'amount_minor', 'currency', 'idempotency_key']);
        $result = adaptor_for($code, $connections)->refund($body);
        json_response(array_merge(['connection' => $code], $result), $result['failure_code'] ? 422 : 200);
    }

    if (preg_match('#^/webhooks/afrpay/(OR001|OB003|or001|ob003)$#', $uri, $m) && $method === 'POST') {
        $code = strtoupper($m[1]);
        $raw = file_get_contents('php://input') ?: '';
        $result = adaptor_for($code, $connections)->handleWebhook($raw, request_headers());

        $logDir = __DIR__.'/logs';
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir.'/webhooks.log',
            gmdate('c').' '.$code.' '.json_encode($result, JSON_UNESCAPED_SLASHES)."\n",
            FILE_APPEND
        );

        if (($result['mapped_status'] ?? '') === 'rejected' && ($result['event'] ?? '') === 'signature_failed') {
            json_response(['status' => 'rejected', 'reason' => 'invalid_signature'], 401);
        }

        json_response([
            'status' => 'accepted',
            'connection' => $code,
            'event' => $result['event'],
            'mapped_status' => $result['mapped_status'],
            'provider_reference' => $result['provider_reference'],
            'merchant_reference' => $result['merchant_reference'],
        ]);
    }

    json_response([
        'status' => 'failed',
        'failure_message' => 'Not found',
        'path' => $uri,
        'hint' => 'GET /api/afrpay/status',
    ], 404);
} catch (Throwable $e) {
    json_response([
        'status' => 'failed',
        'failure_code' => 'ADAPTOR_ERROR',
        'failure_message' => $e->getMessage(),
    ], 422);
}
