<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional route middleware. Prefer adaptor verifyWebhook() in the controller,
 * but this can be registered as verify.psp:afrpay_or001 etc.
 */
class VerifyAfrPayWebhook
{
    public function handle(Request $request, Closure $next, string $connection = 'OR001'): Response
    {
        $connection = strtoupper($connection);
        $config = config("psp_adaptors.connections.{$connection}", []);
        $secret = (string) ($config['webhook_secret'] ?? '');
        $headerName = (string) ($config['signature_header'] ?? 'X-AfrPay-Signature');
        $signature = (string) $request->header($headerName, '');

        if ($secret === '' || $signature === '') {
            Log::warning('AfrPay webhook signature missing', ['connection' => $connection]);

            return response()->json(['error' => 'Signature required'], 401);
        }

        $raw = $request->getContent();
        $computed = hash_hmac('sha256', $raw, $secret);

        if (! hash_equals($computed, $signature) && ! hash_equals('sha256='.$computed, $signature)) {
            Log::warning('AfrPay webhook invalid signature', ['connection' => $connection]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $timestamp = $request->header('X-Timestamp') ?? $request->header('Timestamp');
        $tolerance = (int) ($config['replay_tolerance_seconds'] ?? 300);
        if ($timestamp && abs(time() - (int) $timestamp) > $tolerance) {
            return response()->json(['error' => 'Timestamp too old'], 401);
        }

        return $next($request);
    }
}
