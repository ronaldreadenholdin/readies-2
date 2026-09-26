<?php

namespace App\Services\Psp\Security;

final class CardDataLeakDetector
{
    /**
     * @return list<array{type: string, sample: string}>
     */
    public function findings(mixed $value): array
    {
        $text = $this->flatten($value);
        $findings = [];

        foreach ($this->panCandidates($text) as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate);
            if ($digits !== null && $this->luhnValid($digits)) {
                $findings[] = ['type' => 'pan', 'sample' => $this->sample($candidate)];
            }
        }

        if (preg_match_all('/\b(cvv|cvc|cvv2|securityCode|cardSecurityCode|cvn)\b\s*[:=]\s*["\']?\d{3,4}\b/i', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $findings[] = ['type' => 'cvv', 'sample' => $this->sample($match)];
            }
        }

        if (preg_match_all('/(%B[^?\r\n]{1,128}\?|;[0-9][^?\r\n]{1,128}\?)/', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $findings[] = ['type' => 'track', 'sample' => $this->sample($match)];
            }
        }

        return $findings;
    }

    public function containsLeak(mixed $value): bool
    {
        return $this->findings($value) !== [];
    }

    /**
     * @return list<string>
     */
    private function panCandidates(string $text): array
    {
        if (preg_match_all('/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/', $text, $matches) !== 1) {
            return [];
        }

        return $matches[0];
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

    private function flatten(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $child) {
                $parts[] = (string) $key . '=' . $this->flatten($child);
            }

            return implode(' ', $parts);
        }
        if (is_object($value)) {
            return $this->flatten(get_object_vars($value));
        }

        return '';
    }

    private function sample(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return substr($value, 0, 16);
    }
}
