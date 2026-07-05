<?php

namespace App\Services;

use App\Models\PspProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class PspTestHarnessService
{
    /**
     * Return every configured PSP provider, if the host app has the model/table.
     */
    public function providers()
    {
        if (! class_exists(PspProvider::class)) {
            return collect();
        }

        try {
            return PspProvider::query()
                ->when($this->providerHasColumn('name'), function ($query) {
                    $query->orderBy('name');
                })
                ->get();
        } catch (Throwable $exception) {
            return collect();
        }
    }

    /**
     * Run the safe pre-flight checks against every provider.
     */
    public function runAll($performHttpChecks = false)
    {
        return $this->providers()
            ->map(function ($provider) use ($performHttpChecks) {
                return $this->runProvider($provider, $performHttpChecks);
            })
            ->values();
    }

    /**
     * Run checks for one provider model instance.
     */
    public function runProvider($provider, $performHttpChecks = false)
    {
        $checks = collect([
            $this->testConfiguration($provider),
            $this->testCredentials($provider),
            $this->testEndpointConfiguration($provider, $performHttpChecks),
            $this->testWebhookConfiguration($provider),
            $this->testTransactionPayload($provider),
        ]);

        return [
            'provider' => $this->providerSummary($provider),
            'status' => $this->worstStatus($checks),
            'checks' => $checks,
        ];
    }

    /**
     * Create top-level totals for dashboard cards.
     */
    public function summary(Collection $results)
    {
        $totalChecks = $results->sum(function ($result) {
            return $result['checks']->count();
        });

        $passedChecks = $results->sum(function ($result) {
            return $result['checks']->where('status', 'pass')->count();
        });

        $warningChecks = $results->sum(function ($result) {
            return $result['checks']->where('status', 'warn')->count();
        });

        $failedChecks = $results->sum(function ($result) {
            return $result['checks']->where('status', 'fail')->count();
        });

        return [
            'providers' => $results->count(),
            'checks' => $totalChecks,
            'passed' => $passedChecks,
            'warnings' => $warningChecks,
            'failed' => $failedChecks,
            'ready' => $results->where('status', 'pass')->count(),
        ];
    }

    protected function testConfiguration($provider)
    {
        $data = $this->providerData($provider);
        $enabled = $this->firstPresent($data, ['enabled', 'is_enabled', 'active', 'is_active']);
        $status = $this->firstPresent($data, ['status', 'state']);
        $label = $this->providerLabel($provider);

        if (! $this->present($label)) {
            return $this->check('Configuration', 'fail', 'Provider is missing a display name or code.');
        }

        if ($enabled === false || $enabled === 0 || $enabled === '0') {
            return $this->check('Configuration', 'warn', 'Provider is configured but currently disabled.');
        }

        if (is_string($status) && in_array(strtolower($status), ['disabled', 'inactive', 'draft'], true)) {
            return $this->check('Configuration', 'warn', 'Provider status is '.$status.'.');
        }

        return $this->check('Configuration', 'pass', 'Provider record is present and identifiable.');
    }

    protected function testCredentials($provider)
    {
        $data = $this->providerData($provider);
        $credentials = $this->matchingValues($data, [
            'api_key',
            'apikey',
            'secret',
            'token',
            'merchant',
            'client_id',
            'client_secret',
            'username',
            'password',
            'public_key',
            'private_key',
        ]);

        if (count($credentials) === 0) {
            return $this->check('Credentials', 'fail', 'No credential-like values were found on this provider.');
        }

        $empty = array_filter($credentials, function ($value) {
            return ! $this->present($value);
        });

        if (count($empty) > 0) {
            return $this->check('Credentials', 'fail', count($empty).' credential field(s) are empty.');
        }

        return $this->check('Credentials', 'pass', count($credentials).' credential field(s) are populated.');
    }

    protected function testEndpointConfiguration($provider, $performHttpChecks)
    {
        $data = $this->providerData($provider);
        $endpoints = $this->matchingValues($data, ['url', 'endpoint', 'base_uri', 'base_url', 'api_host', 'host']);
        $urls = array_values(array_filter($endpoints, function ($value) {
            return is_string($value) && filter_var($value, FILTER_VALIDATE_URL);
        }));

        if (count($urls) === 0) {
            return $this->check('API Endpoint', 'fail', 'No valid PSP API URL was found.');
        }

        $insecure = array_filter($urls, function ($url) {
            return strpos($url, 'https://') !== 0;
        });

        if (count($insecure) > 0) {
            return $this->check('API Endpoint', 'warn', 'Endpoint is valid, but at least one URL is not HTTPS.');
        }

        if (! $performHttpChecks) {
            return $this->check('API Endpoint', 'pass', 'Valid HTTPS endpoint configured. Live ping is disabled.');
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get($urls[0]);

            if ($response->serverError()) {
                return $this->check('API Endpoint', 'fail', 'Endpoint responded with HTTP '.$response->status().'.');
            }

            return $this->check('API Endpoint', 'pass', 'Endpoint responded with HTTP '.$response->status().'.');
        } catch (Throwable $exception) {
            return $this->check('API Endpoint', 'fail', 'Endpoint ping failed: '.$exception->getMessage());
        }
    }

    protected function testWebhookConfiguration($provider)
    {
        $data = $this->providerData($provider);
        $webhooks = $this->matchingValues($data, ['webhook', 'callback', 'return_url', 'notify_url']);
        $urls = array_values(array_filter($webhooks, function ($value) {
            return is_string($value) && filter_var($value, FILTER_VALIDATE_URL);
        }));

        if (count($urls) === 0) {
            return $this->check('Webhook', 'warn', 'No valid webhook or callback URL was found.');
        }

        $https = array_filter($urls, function ($url) {
            return strpos($url, 'https://') === 0;
        });

        if (count($https) !== count($urls)) {
            return $this->check('Webhook', 'warn', 'Webhook/callback URL exists but should use HTTPS.');
        }

        return $this->check('Webhook', 'pass', 'Webhook/callback URL is configured over HTTPS.');
    }

    protected function testTransactionPayload($provider)
    {
        $data = $this->providerData($provider);
        $currency = $this->firstPresent($data, ['currency', 'default_currency']);
        $minimum = $this->firstPresent($data, ['minimum_amount', 'min_amount', 'min_deposit', 'min_withdrawal']);

        if (! $this->present($currency)) {
            return $this->check('Transaction Payload', 'warn', 'Default currency was not found; test payload will need one.');
        }

        if ($this->present($minimum) && ! is_numeric($minimum)) {
            return $this->check('Transaction Payload', 'warn', 'Minimum amount exists but is not numeric.');
        }

        return $this->check('Transaction Payload', 'pass', 'A safe dry-run transaction payload can be assembled.');
    }

    protected function providerSummary($provider)
    {
        $data = $this->providerData($provider);

        return [
            'id' => $this->firstPresent($data, ['id', 'provider_id']),
            'name' => $this->providerLabel($provider),
            'code' => $this->firstPresent($data, ['code', 'slug', 'key']),
        ];
    }

    protected function providerLabel($provider)
    {
        $data = $this->providerData($provider);

        return $this->firstPresent($data, ['name', 'display_name', 'title', 'code', 'slug', 'key', 'id']);
    }

    protected function providerData($provider)
    {
        if (is_array($provider)) {
            return $this->normaliseArray($provider);
        }

        if (is_object($provider) && method_exists($provider, 'getAttributes')) {
            return $this->normaliseArray($provider->getAttributes());
        }

        if (is_object($provider) && method_exists($provider, 'toArray')) {
            return $this->normaliseArray($provider->toArray());
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
        $this->walkData($data, function ($key, $value) use (&$matches, $needles) {
            foreach ($needles as $needle) {
                if (strpos(strtolower((string) $key), $needle) !== false) {
                    $matches[(string) $key] = $value;
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

    protected function walkData(array $data, callable $callback)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->walkData($value, $callback);
                continue;
            }

            $callback($key, $value);
        }
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

    protected function check($name, $status, $message)
    {
        return [
            'name' => $name,
            'status' => $status,
            'message' => $message,
        ];
    }

    protected function worstStatus(Collection $checks)
    {
        if ($checks->contains('status', 'fail')) {
            return 'fail';
        }

        if ($checks->contains('status', 'warn')) {
            return 'warn';
        }

        return 'pass';
    }

    protected function providerHasColumn($column)
    {
        try {
            $model = new PspProvider();

            return in_array($column, $model->getFillable(), true)
                || array_key_exists($column, $model->getAttributes());
        } catch (Throwable $exception) {
            return false;
        }
    }
}
