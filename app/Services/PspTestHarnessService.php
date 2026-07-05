<?php

namespace App\Services;

use App\Models\PspProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class PspTestHarnessService
{
    const STATUS_PASS = 'pass';
    const STATUS_WARN = 'warn';
    const STATUS_FAIL = 'fail';
    const STATUS_SKIP = 'skip';

    /**
     * Load every configured PSP provider from the host application.
     */
    public function providers($providerId = null)
    {
        if (! class_exists(PspProvider::class)) {
            return collect();
        }

        try {
            $query = PspProvider::query();

            if ($providerId) {
                $query->whereKey($providerId);
            }

            $providers = $query->get();

            return $providers->sortBy(function ($provider) {
                return strtolower((string) $this->providerLabel($provider));
            })->values();
        } catch (Throwable $exception) {
            return collect();
        }
    }

    /**
     * Run safe pre-flight checks against every provider.
     */
    public function runAll($performHttpChecks = false, $providerId = null)
    {
        return $this->providers($providerId)
            ->map(function ($provider) use ($performHttpChecks) {
                return $this->runProvider($provider, $performHttpChecks);
            })
            ->values();
    }

    /**
     * Run the full test suite for one provider model instance.
     */
    public function runProvider($provider, $performHttpChecks = false)
    {
        $data = $this->providerData($provider);
        $checks = collect([
            $this->testProviderRecord($provider, $data),
            $this->testOperationalStatus($data),
            $this->testCredentials($data),
            $this->testSecretHygiene($data),
            $this->testEndpointConfiguration($data, $performHttpChecks),
            $this->testWebhookConfiguration($data),
            $this->testSupportedCurrencies($data),
            $this->testAmountLimits($data),
            $this->testRoutingReadiness($data),
            $this->testIdempotencySupport($data),
            $this->testTransactionPayload($provider, $data),
        ]);

        $summary = $this->providerCheckSummary($checks);

        return [
            'provider' => $this->providerSummary($provider, $data),
            'status' => $this->worstStatus($checks),
            'score' => $summary['score'],
            'summary' => $summary,
            'checks' => $checks->values(),
            'generated_at' => now(),
        ];
    }

    /**
     * Create top-level dashboard totals.
     */
    public function summary(Collection $results)
    {
        $checks = $results->flatMap(function ($result) {
            return $result['checks'];
        });

        $totalChecks = $checks->count();
        $passedChecks = $checks->where('status', self::STATUS_PASS)->count();
        $warningChecks = $checks->where('status', self::STATUS_WARN)->count();
        $failedChecks = $checks->where('status', self::STATUS_FAIL)->count();
        $skippedChecks = $checks->where('status', self::STATUS_SKIP)->count();
        $readyProviders = $results->where('status', self::STATUS_PASS)->count();
        $averageScore = $results->count() > 0 ? round($results->avg('score')) : 0;

        return [
            'providers' => $results->count(),
            'checks' => $totalChecks,
            'passed' => $passedChecks,
            'warnings' => $warningChecks,
            'failed' => $failedChecks,
            'skipped' => $skippedChecks,
            'ready' => $readyProviders,
            'average_score' => $averageScore,
            'generated_at' => now(),
        ];
    }

    protected function testProviderRecord($provider, array $data)
    {
        $label = $this->providerLabel($provider, $data);
        $identifier = $this->firstPresent($data, ['id', 'provider_id', 'uuid', 'code', 'slug', 'key']);

        if (! $this->present($label)) {
            return $this->check(
                'Provider Record',
                'configuration',
                self::STATUS_FAIL,
                'Provider is missing a display name, code, or key.',
                'Add a recognizable name/code so support and routing logs can identify this PSP.'
            );
        }

        if (! $this->present($identifier)) {
            return $this->check(
                'Provider Record',
                'configuration',
                self::STATUS_WARN,
                'Provider is identifiable by name but has no obvious ID/code field.',
                'Confirm the record has a stable identifier used by payment routing.'
            );
        }

        return $this->check(
            'Provider Record',
            'configuration',
            self::STATUS_PASS,
            'Provider record is present and identifiable.',
            'No action needed.'
        );
    }

    protected function testOperationalStatus(array $data)
    {
        $enabled = $this->firstPresent($data, ['enabled', 'is_enabled', 'active', 'is_active', 'live']);
        $status = $this->firstPresent($data, ['status', 'state', 'mode', 'environment']);

        if ($enabled === false || $enabled === 0 || $enabled === '0') {
            return $this->check(
                'Operational Status',
                'configuration',
                self::STATUS_WARN,
                'Provider is configured but currently disabled.',
                'Enable it when the remaining checks are green and the PSP is ready for routing.'
            );
        }

        if (is_string($status) && in_array(strtolower($status), ['disabled', 'inactive', 'draft', 'paused'], true)) {
            return $this->check(
                'Operational Status',
                'configuration',
                self::STATUS_WARN,
                'Provider status is '.$status.'.',
                'Move the provider to an active/sandbox-ready status before sending traffic.'
            );
        }

        if (is_string($status) && in_array(strtolower($status), ['production', 'prod', 'live'], true)) {
            return $this->check(
                'Operational Status',
                'configuration',
                self::STATUS_WARN,
                'Provider appears to be in '.$status.' mode.',
                'Use sandbox/test mode for harness validation unless this is an intentional production smoke test.'
            );
        }

        return $this->check(
            'Operational Status',
            'configuration',
            self::STATUS_PASS,
            'Provider is not marked disabled or inactive.',
            'No action needed.'
        );
    }

    protected function testCredentials(array $data)
    {
        $credentials = $this->matchingValues($data, [
            'api_key',
            'apikey',
            'secret',
            'token',
            'merchant',
            'merchant_id',
            'client_id',
            'client_secret',
            'username',
            'password',
            'public_key',
            'private_key',
            'certificate',
        ]);

        if (count($credentials) === 0) {
            return $this->check(
                'Credentials',
                'credentials',
                self::STATUS_FAIL,
                'No credential-like fields were found on this provider.',
                'Add the PSP sandbox credentials before running a payment test.'
            );
        }

        $empty = array_filter($credentials, function ($value) {
            return ! $this->present($value);
        });

        if (count($empty) > 0) {
            return $this->check(
                'Credentials',
                'credentials',
                self::STATUS_FAIL,
                count($empty).' credential field(s) are empty.',
                'Fill each required credential field or remove unused placeholders.'
            );
        }

        return $this->check(
            'Credentials',
            'credentials',
            self::STATUS_PASS,
            count($credentials).' credential field(s) are populated.',
            'No action needed.',
            ['fields_found' => array_keys($credentials)]
        );
    }

    protected function testSecretHygiene(array $data)
    {
        $credentials = $this->matchingValues($data, [
            'secret',
            'token',
            'password',
            'private_key',
            'certificate',
        ]);

        if (count($credentials) === 0) {
            return $this->check(
                'Secret Hygiene',
                'credentials',
                self::STATUS_SKIP,
                'No secret-like values were found to inspect.',
                'This may be normal if secrets are stored outside the provider record.'
            );
        }

        $suspicious = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $lower = strtolower(trim($value));
            if (in_array($lower, ['test', 'secret', 'password', 'changeme', 'todo', 'dummy'], true)) {
                $suspicious[] = $key;
            }
        }

        if (count($suspicious) > 0) {
            return $this->check(
                'Secret Hygiene',
                'credentials',
                self::STATUS_WARN,
                'Some secret-like values look like placeholders.',
                'Replace placeholder secrets before using this provider.',
                ['fields' => $suspicious]
            );
        }

        return $this->check(
            'Secret Hygiene',
            'credentials',
            self::STATUS_PASS,
            'Secret-like values do not look like obvious placeholders.',
            'No action needed.'
        );
    }

    protected function testEndpointConfiguration(array $data, $performHttpChecks)
    {
        $endpoints = $this->matchingValues($data, ['url', 'endpoint', 'base_uri', 'base_url', 'api_host', 'host']);
        $urls = $this->validUrls($endpoints);

        if (count($urls) === 0) {
            return $this->check(
                'API Endpoint',
                'connectivity',
                self::STATUS_FAIL,
                'No valid PSP API URL was found.',
                'Add the sandbox base URL or endpoint URL supplied by the PSP.'
            );
        }

        $insecure = array_filter($urls, function ($url) {
            return strpos($url, 'https://') !== 0;
        });

        if (count($insecure) > 0) {
            return $this->check(
                'API Endpoint',
                'connectivity',
                self::STATUS_WARN,
                'At least one PSP endpoint is not HTTPS.',
                'Use HTTPS endpoints for PSP communication.',
                ['urls' => $this->maskUrls($urls)]
            );
        }

        if (! $performHttpChecks) {
            return $this->check(
                'API Endpoint',
                'connectivity',
                self::STATUS_PASS,
                'Valid HTTPS endpoint configured. Live ping is disabled.',
                'Enable live endpoint ping when you want to test reachability.',
                ['urls' => $this->maskUrls($urls)]
            );
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get($urls[0]);

            if ($response->serverError()) {
                return $this->check(
                    'API Endpoint',
                    'connectivity',
                    self::STATUS_FAIL,
                    'Endpoint responded with HTTP '.$response->status().'.',
                    'Check PSP status, firewall rules, and endpoint configuration.',
                    ['url' => $this->maskUrl($urls[0]), 'http_status' => $response->status()]
                );
            }

            return $this->check(
                'API Endpoint',
                'connectivity',
                self::STATUS_PASS,
                'Endpoint responded with HTTP '.$response->status().'.',
                'No action needed.',
                ['url' => $this->maskUrl($urls[0]), 'http_status' => $response->status()]
            );
        } catch (Throwable $exception) {
            return $this->check(
                'API Endpoint',
                'connectivity',
                self::STATUS_FAIL,
                'Endpoint ping failed: '.$exception->getMessage(),
                'Check DNS, firewall, SSL, and the configured PSP base URL.',
                ['url' => $this->maskUrl($urls[0])]
            );
        }
    }

    protected function testWebhookConfiguration(array $data)
    {
        $webhooks = $this->matchingValues($data, ['webhook', 'callback', 'return_url', 'notify_url', 'notification_url']);
        $urls = $this->validUrls($webhooks);

        if (count($urls) === 0) {
            return $this->check(
                'Webhook / Callback',
                'webhooks',
                self::STATUS_WARN,
                'No valid webhook or callback URL was found.',
                'Add the return/notification URL that the PSP will call after payment events.'
            );
        }

        $insecure = array_filter($urls, function ($url) {
            return strpos($url, 'https://') !== 0;
        });

        if (count($insecure) > 0) {
            return $this->check(
                'Webhook / Callback',
                'webhooks',
                self::STATUS_WARN,
                'Webhook/callback URL exists but should use HTTPS.',
                'Switch callback URLs to HTTPS before external PSP testing.',
                ['urls' => $this->maskUrls($urls)]
            );
        }

        return $this->check(
            'Webhook / Callback',
            'webhooks',
            self::STATUS_PASS,
            'Webhook/callback URL is configured over HTTPS.',
            'No action needed.',
            ['urls' => $this->maskUrls($urls)]
        );
    }

    protected function testSupportedCurrencies(array $data)
    {
        $currency = $this->firstPresent($data, ['currency', 'default_currency', 'currencies', 'supported_currencies']);

        if (! $this->present($currency)) {
            return $this->check(
                'Supported Currencies',
                'transactions',
                self::STATUS_WARN,
                'No default or supported currency setting was found.',
                'Configure at least one sandbox currency before assembling test payments.'
            );
        }

        $currencies = is_array($currency) ? $currency : preg_split('/[\s,|]+/', (string) $currency);
        $valid = array_values(array_filter($currencies, function ($item) {
            return is_string($item) && preg_match('/^[A-Z]{3}$/', strtoupper(trim($item)));
        }));

        if (count($valid) === 0) {
            return $this->check(
                'Supported Currencies',
                'transactions',
                self::STATUS_WARN,
                'Currency value exists but does not look like an ISO currency code.',
                'Use ISO-4217 currency codes such as USD, EUR, or GBP.'
            );
        }

        return $this->check(
            'Supported Currencies',
            'transactions',
            self::STATUS_PASS,
            count($valid).' currency value(s) are configured.',
            'No action needed.',
            ['currencies' => array_map('strtoupper', $valid)]
        );
    }

    protected function testAmountLimits(array $data)
    {
        $minimum = $this->firstPresent($data, ['minimum_amount', 'min_amount', 'min_deposit', 'min_withdrawal']);
        $maximum = $this->firstPresent($data, ['maximum_amount', 'max_amount', 'max_deposit', 'max_withdrawal']);

        if (! $this->present($minimum) && ! $this->present($maximum)) {
            return $this->check(
                'Amount Limits',
                'transactions',
                self::STATUS_WARN,
                'No transaction minimum or maximum amount was found.',
                'Configure limits so test payloads stay inside the PSP sandbox rules.'
            );
        }

        if (($this->present($minimum) && ! is_numeric($minimum)) || ($this->present($maximum) && ! is_numeric($maximum))) {
            return $this->check(
                'Amount Limits',
                'transactions',
                self::STATUS_WARN,
                'One or more amount limits exist but are not numeric.',
                'Store transaction limits as numeric values.'
            );
        }

        if (is_numeric($minimum) && is_numeric($maximum) && (float) $minimum > (float) $maximum) {
            return $this->check(
                'Amount Limits',
                'transactions',
                self::STATUS_FAIL,
                'Minimum amount is greater than maximum amount.',
                'Correct the amount limit configuration before running payment tests.'
            );
        }

        return $this->check(
            'Amount Limits',
            'transactions',
            self::STATUS_PASS,
            'Transaction amount limits are present and valid.',
            'No action needed.',
            ['minimum' => $minimum, 'maximum' => $maximum]
        );
    }

    protected function testRoutingReadiness(array $data)
    {
        $priority = $this->firstPresent($data, ['priority', 'weight', 'rank', 'routing_weight']);
        $methods = $this->firstPresent($data, ['payment_methods', 'methods', 'channels', 'payment_type', 'type']);

        if (! $this->present($priority) && ! $this->present($methods)) {
            return $this->check(
                'Routing Readiness',
                'routing',
                self::STATUS_WARN,
                'No routing priority/weight or payment method mapping was found.',
                'Confirm this PSP can be selected by the payment router.'
            );
        }

        return $this->check(
            'Routing Readiness',
            'routing',
            self::STATUS_PASS,
            'Routing metadata is present.',
            'No action needed.'
        );
    }

    protected function testIdempotencySupport(array $data)
    {
        $idempotency = $this->firstPresent($data, ['idempotency', 'idempotency_key', 'reference_prefix', 'transaction_prefix']);

        if (! $this->present($idempotency)) {
            return $this->check(
                'Idempotency / References',
                'transactions',
                self::STATUS_WARN,
                'No idempotency or transaction reference setting was found.',
                'Ensure outbound PSP requests include a unique reference/idempotency key.'
            );
        }

        return $this->check(
            'Idempotency / References',
            'transactions',
            self::STATUS_PASS,
            'Reference/idempotency metadata is present.',
            'No action needed.'
        );
    }

    protected function testTransactionPayload($provider, array $data)
    {
        $currency = $this->normaliseCurrency($this->firstPresent($data, ['currency', 'default_currency', 'currencies', 'supported_currencies']));
        $amount = $this->safeTestAmount($data);
        $reference = 'psp-harness-'.date('YmdHis').'-'.$this->slug($this->providerLabel($provider, $data));

        if (! $currency) {
            return $this->check(
                'Dry-Run Payload',
                'transactions',
                self::STATUS_WARN,
                'A test payload can be created after currency configuration is added.',
                'Configure a default currency for this provider.'
            );
        }

        return $this->check(
            'Dry-Run Payload',
            'transactions',
            self::STATUS_PASS,
            'A safe dry-run transaction payload can be assembled.',
            'Use this payload only against PSP sandbox endpoints.',
            [
                'payload' => [
                    'amount' => $amount,
                    'currency' => $currency,
                    'reference' => $reference,
                    'description' => 'PSP harness validation payment',
                ],
            ]
        );
    }

    protected function providerCheckSummary(Collection $checks)
    {
        $scoredChecks = $checks->reject(function ($check) {
            return $check['status'] === self::STATUS_SKIP;
        });

        $score = 0;

        if ($scoredChecks->count() > 0) {
            $points = $scoredChecks->sum(function ($check) {
                if ($check['status'] === self::STATUS_PASS) {
                    return 100;
                }

                if ($check['status'] === self::STATUS_WARN) {
                    return 50;
                }

                return 0;
            });

            $score = round($points / $scoredChecks->count());
        }

        return [
            'score' => $score,
            'pass' => $checks->where('status', self::STATUS_PASS)->count(),
            'warn' => $checks->where('status', self::STATUS_WARN)->count(),
            'fail' => $checks->where('status', self::STATUS_FAIL)->count(),
            'skip' => $checks->where('status', self::STATUS_SKIP)->count(),
        ];
    }

    protected function providerSummary($provider, array $data)
    {
        return [
            'id' => $this->firstPresent($data, ['id', 'provider_id', 'uuid']),
            'name' => $this->providerLabel($provider, $data),
            'code' => $this->firstPresent($data, ['code', 'slug', 'key']),
            'mode' => $this->firstPresent($data, ['mode', 'environment', 'status']),
        ];
    }

    protected function providerLabel($provider, array $data = null)
    {
        $data = $data ?: $this->providerData($provider);

        return $this->firstPresent($data, ['name', 'display_name', 'title', 'code', 'slug', 'key', 'id']);
    }

    protected function providerData($provider)
    {
        if (is_array($provider)) {
            return $this->normaliseArray($provider);
        }

        if (is_object($provider) && method_exists($provider, 'toArray')) {
            return $this->normaliseArray($provider->toArray());
        }

        if (is_object($provider) && method_exists($provider, 'getAttributes')) {
            return $this->normaliseArray($provider->getAttributes());
        }

        return [];
    }

    protected function normaliseArray(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && $this->looksLikeJson($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = $decoded;
                }
            }
        }

        return $data;
    }

    protected function matchingValues(array $data, array $needles)
    {
        $matches = [];

        $this->walkData($data, function ($key, $value, $path) use (&$matches, $needles) {
            foreach ($needles as $needle) {
                if (strpos(strtolower((string) $key), strtolower($needle)) !== false) {
                    $matches[$path] = $value;
                    break;
                }
            }
        });

        return $matches;
    }

    protected function firstPresent(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $value = $this->findValue($data, $key);

            if ($this->present($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function findValue(array $data, $targetKey)
    {
        $found = null;

        $this->walkData($data, function ($key, $value) use ($targetKey, &$found) {
            if ($found !== null) {
                return;
            }

            if (strtolower((string) $key) === strtolower((string) $targetKey)) {
                $found = $value;
            }
        });

        return $found;
    }

    protected function walkData(array $data, callable $callback, $prefix = '')
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $this->walkData($value, $callback, $path);
                continue;
            }

            $callback($key, $value, $path);
        }
    }

    protected function validUrls(array $values)
    {
        return array_values(array_unique(array_filter($values, function ($value) {
            return is_string($value) && filter_var($value, FILTER_VALIDATE_URL);
        })));
    }

    protected function maskUrls(array $urls)
    {
        return array_map(function ($url) {
            return $this->maskUrl($url);
        }, $urls);
    }

    protected function maskUrl($url)
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $path = isset($parts['path']) ? $parts['path'] : '';

        return $scheme.$parts['host'].$path;
    }

    protected function normaliseCurrency($currency)
    {
        if (is_array($currency)) {
            $currency = reset($currency);
        }

        if (! is_string($currency) || trim($currency) === '') {
            return null;
        }

        $parts = preg_split('/[\s,|]+/', trim($currency));
        $currency = strtoupper(trim($parts[0]));

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }

    protected function safeTestAmount(array $data)
    {
        $minimum = $this->firstPresent($data, ['minimum_amount', 'min_amount', 'min_deposit', 'min_withdrawal']);
        $maximum = $this->firstPresent($data, ['maximum_amount', 'max_amount', 'max_deposit', 'max_withdrawal']);

        if (is_numeric($minimum)) {
            return round(max(1, (float) $minimum), 2);
        }

        if (is_numeric($maximum)) {
            return round(min(10, (float) $maximum), 2);
        }

        return 1.00;
    }

    protected function present($value)
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return true;
    }

    protected function looksLikeJson($value)
    {
        $value = trim($value);

        return $value !== '' && in_array(substr($value, 0, 1), ['{', '['], true);
    }

    protected function slug($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');

        return $value ?: 'provider';
    }

    protected function check($name, $category, $status, $message, $recommendation, array $meta = [])
    {
        return [
            'name' => $name,
            'category' => $category,
            'status' => $status,
            'message' => $message,
            'recommendation' => $recommendation,
            'meta' => $meta,
        ];
    }

    protected function worstStatus(Collection $checks)
    {
        if ($checks->contains('status', self::STATUS_FAIL)) {
            return self::STATUS_FAIL;
        }

        if ($checks->contains('status', self::STATUS_WARN)) {
            return self::STATUS_WARN;
        }

        if ($checks->contains('status', self::STATUS_SKIP)) {
            return self::STATUS_SKIP;
        }

        return self::STATUS_PASS;
    }
}
