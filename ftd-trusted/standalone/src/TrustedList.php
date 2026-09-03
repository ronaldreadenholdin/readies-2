<?php

declare(strict_types=1);

final class TrustedList
{
    public const FTD = 'FTD';
    public const TRUSTED = 'trusted';

    public const MATCH_EMAIL = 'email';
    public const MATCH_PHONE = 'phone';
    public const MATCH_CARD = 'card_first6_last4';
    public const MATCH_BIRTHDAY = 'birthday';
    public const MATCH_FULL_NAME = 'full_name';

    public const BIZ_VALUES = [
        'gambling',
        'gaming',
        'mlm',
        'food_supplements',
        'pharma',
        'forex',
        'digital_products',
        'other',
    ];

    public function __construct(private readonly string $path)
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (! is_file($path)) {
            $this->write(['records' => []]);
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{status:string,matched_by:?string,record:?array}
     */
    public function classify(array $input): array
    {
        $merchant = $this->normalizeMerchant($input['merchant'] ?? $input['merchant_id'] ?? null);
        $keys = $this->keys($input);
        $records = $this->forMerchant($merchant);

        foreach ([
            self::MATCH_EMAIL,
            self::MATCH_PHONE,
            self::MATCH_CARD,
            self::MATCH_BIRTHDAY,
            self::MATCH_FULL_NAME,
        ] as $field) {
            $value = $keys[$field] ?? null;
            if ($value === null) {
                continue;
            }
            foreach ($records as $record) {
                if (($record[$field] ?? null) === $value) {
                    return [
                        'status' => self::TRUSTED,
                        'matched_by' => $field,
                        'record' => $record,
                    ];
                }
            }
        }

        return [
            'status' => self::FTD,
            'matched_by' => null,
            'record' => null,
        ];
    }

    /**
     * A successful payment puts the customer on the list.
     *
     * @param array<string,mixed> $input
     * @return array{status:string,matched_by:string,record:array}
     */
    public function markPaid(array $input): array
    {
        $merchant = $this->normalizeMerchant($input['merchant'] ?? $input['merchant_id'] ?? null);
        $keys = $this->keys($input);
        $existing = $this->classify($input);
        $record = $existing['record'] ?? [
            'id' => bin2hex(random_bytes(8)),
            'created_at' => gmdate('c'),
        ];
        $record['merchant'] = $merchant;

        foreach ($keys as $field => $value) {
            if ($value !== null) {
                $record[$field] = $value;
            }
        }

        $biz = $this->normalizeBiz($input['biz'] ?? $input['business'] ?? null);
        if ($biz !== null) {
            $record['biz'] = $biz;
        }

        $record['trusted'] = true;
        $record['successful_payments'] = (int) ($record['successful_payments'] ?? 0) + 1;
        $record['last_provider'] = $this->clean((string) ($input['provider'] ?? ''), 32);
        $record['last_paid_at'] = gmdate('c');
        $record['updated_at'] = gmdate('c');

        $this->upsert($record);

        return [
            'status' => self::TRUSTED,
            'matched_by' => 'successful_payment',
            'record' => $record,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,?string>
     */
    public function keys(array $input): array
    {
        $email = $this->normalizeEmail($input['email'] ?? null);
        $phone = $this->normalizePhone($input['phone'] ?? null);
        $card = $this->normalizeCard($input['card_first6'] ?? null, $input['card_last4'] ?? null);
        $birthday = $this->normalizeBirthday($input['birthday'] ?? null);
        $name = $this->normalizeName($input['full_name'] ?? $input['name'] ?? null);

        return [
            self::MATCH_EMAIL => $email,
            self::MATCH_PHONE => $phone,
            self::MATCH_CARD => $card,
            self::MATCH_BIRTHDAY => $birthday,
            self::MATCH_FULL_NAME => $name,
        ];
    }

    public function count(?string $merchant = null): int
    {
        if ($merchant === null) {
            return count($this->all());
        }

        return count($this->forMerchant($this->normalizeMerchant($merchant)));
    }

    /**
     * Admin upload replaces the whole list for that merchant.
     * Merchants do not upload; 0609 staff do this on the admin backend.
     *
     * @return array{merchant:string,imported:int,skipped:int,trusted_count:int}
     */
    public function replaceFromCsv(string $merchant, string $csv): array
    {
        $merchant = $this->normalizeMerchant($merchant);
        if ($merchant === 'default') {
            throw new InvalidArgumentException('Merchant name is required. Admin uploads the list for a merchant.');
        }

        $rows = $this->parseCsv($csv);
        $imported = [];
        $skipped = 0;
        foreach ($rows as $row) {
            $keys = $this->keys($row);
            if (! array_filter($keys)) {
                $skipped++;
                continue;
            }
            $record = [
                'id' => bin2hex(random_bytes(8)),
                'merchant' => $merchant,
                'trusted' => true,
                'source' => 'admin_upload',
                'successful_payments' => 0,
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ];
            foreach ($keys as $field => $value) {
                if ($value !== null) {
                    $record[$field] = $value;
                }
            }
            $biz = $this->normalizeBiz($row['biz'] ?? $row['business'] ?? null);
            if ($biz !== null) {
                $record['biz'] = $biz;
            }
            $imported[] = $record;
        }

        $kept = array_values(array_filter(
            $this->all(),
            fn (array $row): bool => ($row['merchant'] ?? 'default') !== $merchant
        ));
        $this->write(['records' => array_merge($kept, $imported)]);

        return [
            'merchant' => $merchant,
            'imported' => count($imported),
            'skipped' => $skipped,
            'trusted_count' => count($imported),
            'rule' => 'Admin upload is now the whole trusted list for this merchant. Merchants do not upload.',
        ];
    }

    public function normalizeMerchant(mixed $value): string
    {
        $merchant = strtolower($this->clean((string) $value, 64));
        $merchant = preg_replace('/[^a-z0-9_-]+/', '-', $merchant) ?? '';
        $merchant = trim($merchant, '-');

        return $merchant !== '' ? $merchant : 'default';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function forMerchant(string $merchant): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $row): bool => ($row['merchant'] ?? 'default') === $merchant
        ));
    }

    /**
     * @return list<array<string,string>>
     */
    private function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Could not read CSV.');
        }
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle);
        if (! is_array($header) || $header === [null] || $header === false) {
            fclose($handle);
            throw new InvalidArgumentException('CSV needs a header row.');
        }
        $header = array_map(static function ($item): string {
            return strtolower(trim((string) $item));
        }, $header);
        $aliases = [
            'e-mail' => 'email',
            'mail' => 'email',
            'mobile' => 'phone',
            'tel' => 'phone',
            'bin' => 'card_first6',
            'first6' => 'card_first6',
            'last4' => 'card_last4',
            'dob' => 'birthday',
            'birth_date' => 'birthday',
            'name' => 'full_name',
            'customer_name' => 'full_name',
            'business' => 'biz',
            'vertical' => 'biz',
        ];
        $header = array_map(static fn (string $item): string => $aliases[$item] ?? $item, $header);

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === []) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = (string) ($data[$i] ?? '');
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function all(): array
    {
        $raw = file_get_contents($this->path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $records = is_array($decoded) ? ($decoded['records'] ?? []) : [];

        return is_array($records) ? array_values($records) : [];
    }

    /**
     * @param array<string,mixed> $record
     */
    private function upsert(array $record): void
    {
        $records = $this->all();
        $found = false;
        foreach ($records as $i => $row) {
            if (($row['id'] ?? null) === ($record['id'] ?? null)) {
                $records[$i] = $record;
                $found = true;
                break;
            }
        }
        if (! $found) {
            $records[] = $record;
        }
        $this->write(['records' => $records]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function write(array $payload): void
    {
        file_put_contents(
            $this->path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = strtolower($this->clean((string) $value, 190));
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        return $email;
    }

    private function normalizePhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }

        return $digits;
    }

    private function normalizeCard(mixed $first6, mixed $last4): ?string
    {
        $bin = preg_replace('/\D+/', '', (string) $first6) ?? '';
        $tail = preg_replace('/\D+/', '', (string) $last4) ?? '';
        if (strlen($bin) !== 6 || strlen($tail) !== 4) {
            return null;
        }

        return $bin . $tail;
    }

    private function normalizeBirthday(mixed $value): ?string
    {
        $raw = $this->clean((string) $value, 32);
        if ($raw === '') {
            return null;
        }
        $time = strtotime($raw);
        if ($time === false) {
            return null;
        }

        return gmdate('Y-m-d', $time);
    }

    public function normalizeBiz(mixed $value): ?string
    {
        $raw = strtolower($this->clean((string) $value, 64));
        $raw = str_replace(['-', ' '], '_', $raw);
        $raw = preg_replace('/_+/', '_', $raw) ?? '';
        $aliases = [
            'casino' => 'gambling',
            'igaming' => 'gambling',
            'i_gaming' => 'gambling',
            'game' => 'gaming',
            'games' => 'gaming',
            'multi_level_marketing' => 'mlm',
            'supplements' => 'food_supplements',
            'food_supplement' => 'food_supplements',
            'nutra' => 'food_supplements',
            'nutraceutical' => 'food_supplements',
            'pharmacy' => 'pharma',
            'pharmaceutical' => 'pharma',
            'fx' => 'forex',
            'digital' => 'digital_products',
            'digital_product' => 'digital_products',
            'info_product' => 'digital_products',
        ];
        $biz = $aliases[$raw] ?? $raw;
        if ($biz === '') {
            return null;
        }

        return in_array($biz, self::BIZ_VALUES, true) ? $biz : $this->clean($biz, 64);
    }

    private function normalizeName(mixed $value): ?string
    {
        $name = strtolower($this->clean((string) $value, 190));
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        if ($name === '' || ! str_contains($name, ' ')) {
            return null;
        }

        return $name;
    }

    private function clean(string $value, int $max): string
    {
        $value = trim($value);

        return substr($value, 0, $max);
    }
}
