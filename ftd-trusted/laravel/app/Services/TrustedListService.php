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
        $merchant = $this->normalizeMerchant($input['merchant'] ?? $input['merchant_id'] ?? null);
        $keys = $this->keys($input);

        foreach (['email', 'phone', 'card_first6_last4', 'birthday', 'full_name'] as $field) {
            $value = $keys[$field] ?? null;
            if ($value === null) {
                continue;
            }

            $column = $field === 'card_first6_last4' ? 'card_first6_last4' : $field;
            $record = TrustedCustomer::query()
                ->where('merchant', $merchant)
                ->where($column, $value)
                ->first();
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

        $biz = $this->normalizeBiz($input['biz'] ?? $input['business'] ?? null);
        if ($biz !== null) {
            $record->biz = $biz;
        }

        $record->merchant = $this->normalizeMerchant($input['merchant'] ?? $input['merchant_id'] ?? null);
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

    public function replaceFromCsv(string $merchant, string $csv): array
    {
        $merchant = $this->normalizeMerchant($merchant);
        if ($merchant === 'default') {
            throw new \InvalidArgumentException('Merchant name is required. Admin uploads the list for a merchant.');
        }

        TrustedCustomer::query()->where('merchant', $merchant)->delete();

        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv) ?: []);
        $header = array_map(static fn ($item) => strtolower(trim((string) $item)), $rows[0] ?? []);
        $imported = 0;
        $skipped = 0;
        foreach (array_slice($rows, 1) as $data) {
            if ($data === [] || $data === [null]) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = (string) ($data[$i] ?? '');
            }
            $keys = $this->keys($row);
            if (! array_filter($keys)) {
                $skipped++;
                continue;
            }
            $record = new TrustedCustomer(['merchant' => $merchant]);
            foreach ($keys as $field => $value) {
                if ($value !== null) {
                    $record->{$field} = $value;
                }
            }
            $biz = $this->normalizeBiz($row['biz'] ?? $row['business'] ?? null);
            if ($biz !== null) {
                $record->biz = $biz;
            }
            $record->save();
            $imported++;
        }

        return [
            'merchant' => $merchant,
            'imported' => $imported,
            'skipped' => $skipped,
            'trusted_count' => $imported,
            'rule' => 'Admin upload is now the whole trusted list for this merchant. Merchants do not upload.',
        ];
    }

    /**
     * Admin helper: record a successful payment for a merchant's customer.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function markPaidSuccessfully(string $merchantId, array $input): array
    {
        $input['merchant'] = $merchantId;

        return $this->markPaid($input);
    }

    /**
     * Admin helper: replace a merchant's whole list from an uploaded CSV path.
     *
     * @return array<string, mixed>
     */
    public function replaceMerchantList(string $merchantId, string $path): array
    {
        $csv = is_file($path) ? (string) file_get_contents($path) : '';

        return $this->replaceFromCsv($merchantId, $csv);
    }

    public function normalizeMerchant(mixed $value): string
    {
        $merchant = strtolower(trim((string) $value));
        $merchant = preg_replace('/[^a-z0-9_-]+/', '-', $merchant) ?? '';
        $merchant = trim($merchant, '-');

        return $merchant !== '' ? $merchant : 'default';
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

    private function normalizeBiz(mixed $value): ?string
    {
        $raw = strtolower(trim((string) $value));
        $raw = str_replace(['-', ' '], '_', $raw);
        $raw = preg_replace('/_+/', '_', $raw) ?? '';
        $aliases = [
            'casino' => 'gambling',
            'igaming' => 'gambling',
            'supplements' => 'food_supplements',
            'food_supplement' => 'food_supplements',
            'nutra' => 'food_supplements',
            'pharmacy' => 'pharma',
            'fx' => 'forex',
            'digital' => 'digital_products',
            'digital_product' => 'digital_products',
        ];
        $biz = $aliases[$raw] ?? $raw;

        return $biz !== '' ? substr($biz, 0, 64) : null;
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
