<?php

namespace App\Services;

use App\Models\TrustedCustomer;
use Illuminate\Support\Carbon;

class TrustedListService
{
    public const FTD = 'FTD';
    public const TRUSTED = 'trusted';

    public function classify(array $input): array
    {
        $keys = $this->keys($input);

        foreach (['email', 'phone', 'card_first6_last4', 'birthday', 'full_name'] as $field) {
            $value = $keys[$field] ?? null;
            if ($value === null) {
                continue;
            }

            $column = $field === 'card_first6_last4' ? 'card_first6_last4' : $field;
            $record = TrustedCustomer::query()->where($column, $value)->first();
            if ($record) {
                return [
                    'status' => self::TRUSTED,
                    'matched_by' => $field,
                    'record' => $record->toArray(),
                ];
            }
        }

        return [
            'status' => self::FTD,
            'matched_by' => null,
            'record' => null,
        ];
    }

    public function markPaid(array $input): array
    {
        $found = $this->classify($input);
        $record = $found['record']
            ? TrustedCustomer::query()->find($found['record']['id'])
            : new TrustedCustomer();

        $keys = $this->keys($input);
        foreach ($keys as $field => $value) {
            if ($value !== null) {
                $record->{$field} = $value;
            }
        }

        $record->successful_payments = (int) $record->successful_payments + 1;
        $record->last_provider = substr((string) ($input['provider'] ?? ''), 0, 32);
        $record->last_paid_at = Carbon::now();
        $record->save();

        return [
            'status' => self::TRUSTED,
            'matched_by' => 'successful_payment',
            'record' => $record->toArray(),
        ];
    }

    public function keys(array $input): array
    {
        return [
            'email' => $this->normalizeEmail($input['email'] ?? null),
            'phone' => $this->normalizePhone($input['phone'] ?? null),
            'card_first6_last4' => $this->normalizeCard($input['card_first6'] ?? null, $input['card_last4'] ?? null),
            'birthday' => $this->normalizeBirthday($input['birthday'] ?? null),
            'full_name' => $this->normalizeName($input['full_name'] ?? $input['name'] ?? null),
        ];
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));
        return ($email !== '' && str_contains($email, '@')) ? $email : null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        return strlen($digits) >= 8 ? $digits : null;
    }

    private function normalizeCard(mixed $first6, mixed $last4): ?string
    {
        $bin = preg_replace('/\D+/', '', (string) $first6) ?? '';
        $tail = preg_replace('/\D+/', '', (string) $last4) ?? '';
        return (strlen($bin) === 6 && strlen($tail) === 4) ? $bin.$tail : null;
    }

    private function normalizeBirthday(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeName(mixed $value): ?string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
        return ($name !== '' && str_contains($name, ' ')) ? $name : null;
    }
}
