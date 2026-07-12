<?php

declare(strict_types=1);

namespace AfrPay\Services;

final class HttpClient
{
    public static function request(
        string $method,
        string $url,
        array $headers,
        ?array $payload,
        int $timeout
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to init curl.');
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k.': '.$v;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($payload !== null && strtoupper($method) !== 'GET') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
        } elseif ($payload !== null && strtoupper($method) === 'GET' && $payload !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($payload);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('AfrPay HTTP error: '.$error);
        }

        $json = json_decode((string) $body, true);
        if (! is_array($json)) {
            $json = [];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'json' => $json,
            'raw' => (string) $body,
            'url' => $url,
            'method' => strtoupper($method),
        ];
    }
}
