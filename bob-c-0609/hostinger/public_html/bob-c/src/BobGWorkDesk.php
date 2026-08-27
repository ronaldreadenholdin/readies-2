<?php

declare(strict_types=1);

final class BobGWorkDesk
{
    public function __construct(
        private readonly array $catalog,
        private readonly BobRecommendationService $recommendations,
    ) {
    }

    public static function load(string $catalogPath): self
    {
        $raw = is_file($catalogPath) ? file_get_contents($catalogPath) : false;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return new self(is_array($decoded) ? $decoded : [], new BobRecommendationService());
    }

    public function summary(): array
    {
        return [
            'agent' => $this->catalog['agent'] ?? 'Bob G',
            'extension' => $this->catalog['extension'] ?? 'BOB C',
            'rule' => $this->catalog['rule'] ?? 'BOB C extends Bob G.',
            'completed' => $this->catalog['completed'] ?? [],
            'open' => $this->catalog['open'] ?? [],
            'providers' => $this->catalog['providers'] ?? [],
            'functions' => [
                'list_work',
                'recommend',
                'advise',
                'respond',
                'go_live_gate',
            ],
        ];
    }

    public function recommend(array $flaggedItems, string $pspName = 'PSP'): string
    {
        return $this->recommendations->generate($flaggedItems, $pspName);
    }

    public function advise(string $status, bool $adjustable, string $name, string $pspName): string
    {
        if ($status === 'green') {
            return "This point is green for {$pspName}. Keep it locked into the live mirror contract.";
        }
        if ($adjustable) {
            return "Flagged: {$name}. Bob G can adjust this locally in the test interface before we ask the PSP for help.";
        }

        return "Last resort: {$name}. This needs {$pspName} confirmation before live traffic can be approved.";
    }

    public function goLiveGate(bool $allGreen): array
    {
        if ($allGreen) {
            return [
                'unlocked' => true,
                'message' => 'All Bob G pre-flight checks are green. Hostinger go-live can be reviewed by a human.',
            ];
        }

        return [
            'unlocked' => false,
            'message' => 'Go-live stays locked. Bob G will not enable live PSP traffic until every check is green.',
        ];
    }

    public function respond(string $message): string
    {
        $lower = strtolower(trim($message));
        $providers = $this->catalog['providers'] ?? [];
        $open = $this->catalog['open'] ?? [];
        $completed = $this->catalog['completed'] ?? [];

        if ($lower === '' ) {
            return 'Ask Bob G to continue an existing function. BOB C does not start new work from scratch.';
        }

        if (preg_match('/work|what did|catalog|inventory|already built/', $lower) === 1) {
            $titles = array_map(static fn ($row) => $row['title'] ?? $row['id'], $completed);
            $openTitles = array_map(static fn ($row) => $row['title'] ?? $row['id'], $open);
            return "BOB C extends Bob G. Already built:\n- " . implode("\n- ", $titles) . "\n\nStill open:\n- " . implode("\n- ", $openTitles);
        }

        if (str_contains($lower, 'afrpay')) {
            return $this->findOpen('afrpay-real-materials')
                ?? 'AfrPay is Europe / Kazakhstan / Tunisia only. Do not reuse CashForo or Flamingo.';
        }

        if (preg_match('/cashforo|or001|ob003|adaptor/', $lower) === 1) {
            return "Bob G already created `PspAdaptorInterface`, `CashForoOnrampAdaptor` (OR001), and `CashForoOpenBankingAdaptor` (OB003). Those files are stubs with `API_DOCS_REQUIRED`. BOB C should map the real CashForo docs onto those adaptors, not write new ones.";
        }

        if (preg_match('/recommend|flagged|request list/', $lower) === 1) {
            return $this->recommend([
                ['name' => 'Webhook sample', 'details' => 'Need a signed webhook payload before go-live.'],
                ['name' => 'Signature header', 'details' => 'Confirm header name, secret, and canonical string.'],
            ], 'PSP');
        }

        if (preg_match('/fbls|p003|xcore|p004|pre-flight|harness/', $lower) === 1) {
            return "Bob G already built `PspTestHarnessService` and `/psp-sandbox` for FBLS P003 and Xcore P004, plus `createBobResponse` / `buildBobAdvice` in `pre-flight-test.html`. Use those. BOB C only continues flagged checks, local adjustments, and the go-live gate.";
        }

        if (preg_match('/fena|ob-fena/', $lower) === 1) {
            return "Bob G already built the OB Fena board with `runFenaTest` and `showBobGuidance`. Continue webhook evidence, refund path, and settlement controls. Do not mix Fena with CashForo OB003.";
        }

        if (preg_match('/go live|golive|live traffic/', $lower) === 1) {
            return $this->goLiveGate(false)['message'];
        }

        if (preg_match('/function|controller|laravel|route/', $lower) === 1) {
            return "Reuse Bob G's Laravel pieces first: `PspSandboxController`, `BobRecommendationService`, `VerifyPspWebhook`, and `layouts.adminpanel` views. BOB C adds `/bob-c` as the sidebar extension. Only draft a new function if it is not already in the Bob G catalog.";
        }

        $providerHint = $this->matchProvider($lower, $providers);
        if ($providerHint !== null) {
            return $providerHint;
        }

        return "I am Bob G, used through BOB C.\n\nAsk me to continue existing work: recommendations, FBLS P003, Xcore P004, CashForo OR001/OB003 mapping, Fena, or the 0609 sidebar. I will not rebuild boards or adaptors that already exist.";
    }

    private function findOpen(string $id): ?string
    {
        foreach ($this->catalog['open'] ?? [] as $row) {
            if (($row['id'] ?? '') === $id) {
                return ($row['title'] ?? $id) . '. Blocked on: ' . ($row['blocked_on'] ?? 'owner input') . '. AfrPay stays Europe / Kazakhstan / Tunisia. Do not copy CashForo OR001/OB003.';
            }
        }

        return null;
    }

    private function matchProvider(string $lower, array $providers): ?string
    {
        foreach ($providers as $provider) {
            $code = strtolower((string) ($provider['code'] ?? ''));
            $name = strtolower((string) ($provider['name'] ?? ''));
            if ($code !== '' && (str_contains($lower, $code) || ($name !== '' && str_contains($lower, $name)))) {
                $notes = $provider['notes'] ?? 'Continue from Bob G catalog, do not start a new adaptor.';
                return ($provider['name'] ?? $code) . ' (' . ($provider['code'] ?? '') . '): ' . $notes;
            }
        }

        return null;
    }
}
