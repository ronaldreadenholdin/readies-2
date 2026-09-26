<?php

declare(strict_types=1);

use App\Services\Psp\Security\CardDataLeakDetector;
use App\Services\Psp\Security\CardDataMaskerInterface;

require_once __DIR__ . '/php_psp_bootstrap.php';

final class ReferenceCardDataMasker implements CardDataMaskerInterface
{
    public function mask(mixed $value): mixed
    {
        if (is_array($value)) {
            $masked = [];
            foreach ($value as $key => $child) {
                if ($this->isCvvKey((string) $key)) {
                    $masked[$key] = '[REDACTED_CVV]';
                    continue;
                }
                if ($this->isExpiryKey((string) $key)) {
                    $masked[$key] = '[REDACTED_EXPIRY]';
                    continue;
                }
                $masked[$key] = $this->mask($child);
            }

            return $masked;
        }

        if (is_object($value)) {
            $copy = clone $value;
            foreach (get_object_vars($copy) as $key => $child) {
                $copy->{$key} = $this->mask([$key => $child])[$key];
            }

            return $copy;
        }

        if (! is_scalar($value)) {
            return $value;
        }

        $text = (string) $value;
        $text = preg_replace('/%B[^?\r\n]{1,128}\?/', '[REDACTED_TRACK]', $text) ?? $text;
        $text = preg_replace('/;[0-9][^?\r\n]{1,128}\?/', '[REDACTED_TRACK]', $text) ?? $text;
        $text = preg_replace_callback(
            '/\b(cvv|cvc|cvv2|securityCode|cardSecurityCode|cvn)\b\s*([:=])\s*["\']?\d{3,4}\b/i',
            static fn (array $match): string => $match[1] . $match[2] . '[REDACTED_CVV]',
            $text,
        ) ?? $text;
        $text = preg_replace_callback('/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/', function (array $match): string {
            $digits = preg_replace('/\D+/', '', $match[0]) ?? '';
            if (! $this->luhnValid($digits)) {
                return $match[0];
            }

            return substr($digits, 0, 6) . '******' . substr($digits, -4);
        }, $text) ?? $text;

        return $text;
    }

    private function isCvvKey(string $key): bool
    {
        return preg_match('/^(cvv|cvc|cvv2|securityCode|cardSecurityCode|cvn)$/i', $key) === 1;
    }

    private function isExpiryKey(string $key): bool
    {
        return preg_match('/^(expiry|card_expiry|expiry_month|expiry_year|exp_month|exp_year)$/i', $key) === 1;
    }

    private function luhnValid(string $digits): bool
    {
        $length = strlen($digits);
        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        $alternate = false;
        for ($index = $length - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}

$masker = new ReferenceCardDataMasker();
$detector = new CardDataLeakDetector();
$raw = [
    'card' => [
        'pan' => '4111 1111 1111 1111',
        'cvv' => '123',
        'expiry' => '12/30',
    ],
    'track' => ';4111111111111111=30122010000000000000?',
    'note' => 'order number 123456789012 is not a PAN',
];
$masked = $masker->mask($raw);
$maskedAgain = $masker->mask($masked);

echo json_encode([
    'implements_interface' => $masker instanceof CardDataMaskerInterface,
    'pan_masked' => $masked['card']['pan'] === '411111******1111',
    'cvv_masked' => $masked['card']['cvv'] === '[REDACTED_CVV]',
    'expiry_masked' => $masked['card']['expiry'] === '[REDACTED_EXPIRY]',
    'track_masked' => $masked['track'] === '[REDACTED_TRACK]',
    'idempotent' => $maskedAgain === $masked,
    'masked_is_clean' => ! $detector->containsLeak($masked),
    'canary_is_detected' => $detector->containsLeak('leak pan=4111 1111 1111 1111 cvv=123'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
