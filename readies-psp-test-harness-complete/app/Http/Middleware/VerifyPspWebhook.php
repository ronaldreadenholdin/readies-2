<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPspWebhook
{
    public function handle(Request $request, Closure $next, string $pspName): Response
    {
        $rawPayload = $request->getContent();
        $receivedSignature = $this->signatureFromRequest($request);
        $secret = config("psp.{$pspName}.webhook_secret");

        if (! $secret || ! $receivedSignature) {
            Log::warning('PSP webhook signature missing.', [
                'psp' => $pspName,
                'has_secret' => (bool) $secret,
                'has_signature' => (bool) $receivedSignature,
            ]);

            return response()->json(['error' => 'Signature required'], 401);
        }

        $computed = hash_hmac('sha256', $rawPayload, $secret);
        $computedWithPrefix = 'sha256='.$computed;

        if (! hash_equals($computed, $receivedSignature) && ! hash_equals($computedWithPrefix, $receivedSignature)) {
            Log::warning('Invalid PSP webhook signature.', [
                'psp' => $pspName,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $timestamp = $request->header('X-Timestamp') ?? $request->header('Timestamp');
        $tolerance = (int) config("psp.{$pspName}.replay_tolerance_seconds", 300);

        if ($timestamp && abs(time() - (int) $timestamp) > $tolerance) {
            Log::warning('PSP webhook timestamp outside replay tolerance.', [
                'psp' => $pspName,
                'timestamp' => $timestamp,
                'tolerance' => $tolerance,
            ]);

            return response()->json(['error' => 'Timestamp too old'], 401);
        }

        return $next($request);
    }

    private function signatureFromRequest(Request $request): string
    {
        return (string) (
            $request->header('X-Signature')
            ?? $request->header('Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-FBLS-Signature')
            ?? $request->header('X-XCore-Signature')
            ?? ''
        );
    }
}
