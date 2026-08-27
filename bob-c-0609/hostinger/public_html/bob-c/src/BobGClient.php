<?php

declare(strict_types=1);

final class BobGClient
{
    public const FALLBACK_MODE = 'local-helper';
    public const GROK_MODE = 'bob-g-grok';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            bob_c_env('XAI_API_KEY'),
            bob_c_env('XAI_MODEL', 'grok-3') ?? 'grok-3',
            rtrim(bob_c_env('XAI_BASE_URL', 'https://api.x.ai/v1') ?? 'https://api.x.ai/v1', '/'),
        );
    }

    public function connected(): bool
    {
        return is_string($this->apiKey) && $this->apiKey !== '';
    }

    public function desk(): BobGWorkDesk
    {
        $path = bob_c_root() . '/work/catalog.json';
        if (! is_file($path)) {
            $path = dirname(__DIR__, 3) . '/bob-g-work/catalog.json';
        }

        return BobGWorkDesk::load($path);
    }

    public function status(): array
    {
        $desk = $this->desk()->summary();

        return [
            'agent' => 'BOB C',
            'assistant' => 'Bob G',
            'extends' => 'Bob G',
            'mode' => $this->connected() ? self::GROK_MODE : 'bob-g-workdesk',
            'model' => $this->connected() ? $this->model : 'bob-g-workdesk',
            'connected' => $this->connected(),
            'site' => bob_c_env('APP_URL', 'https://0609.readies.biz'),
            'work' => $desk,
        ];
    }

    public function ask(string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Ask Bob G a question first.');
        }

        $desk = $this->desk();
        $deskReply = $desk->respond($message);

        $messages = array_merge(
            [['role' => 'system', 'content' => self::systemPrompt() . "\n\n" . $desk->summary()['rule']]],
            $this->normalizeHistory($history),
            [['role' => 'user', 'content' => $message]],
        );

        if ($this->connected()) {
            $reply = $this->callGrok($messages);
            return [
                'ok' => true,
                'mode' => self::GROK_MODE,
                'model' => $this->model,
                'reply' => $reply,
                'source' => 'bob-g',
                'desk' => $desk->summary(),
            ];
        }

        return [
            'ok' => true,
            'mode' => 'bob-g-workdesk',
            'model' => 'bob-g-workdesk',
            'reply' => $deskReply,
            'source' => 'bob-g-workdesk',
            'desk' => $desk->summary(),
            'notice' => 'Using Bob G work desk. Add XAI_API_KEY if you want live Grok on top of this catalog.',
        ];
    }

    public static function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Bob G. BOB C is your 0609 sidebar extension.
Do not rebuild work you already shipped. Continue open catalog items only.
You are used from the BOB C sidebar tab on https://0609.readies.biz.

Help authorized backend users:
1. Create Laravel functions, controllers, routes, services, jobs, migrations, and Blade views.
2. Integrate payment service providers (PSPs) into the Readies gateway.
3. Draft webhook handlers, signature checks, sandbox vs live gates, and go-live checklists.
4. Explain errors and propose reviewable code.

Known provider codes:
- P003 = FBLS
- P004 = Xcore
- P005 = next PSP starter
- OR001 = CashForo onramp (card → USDT/USDC → Readies)
- OB003 = CashForo open banking
- AfrPay = three geos with different costs: Europe, Kazakhstan, Tunisia. Real AfrPay API docs may be missing. Do not invent them. Do not treat AfrPay as CashForo or Flamingo.

Hard rules:
- Never print live secrets, passwords, or webhook secrets.
- Never enable live PSP traffic from chat.
- All generated code is a draft. A human must review before Hostinger deploy.
- Prefer Laravel + Hostinger public/ document-root patterns.
- Keep answers practical and specific. Include file paths when you generate code.
PROMPT;
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     */
    private function callGrok(array $messages): string
    {
        $payload = json_encode([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($this->baseUrl . '/chat/completions');
        if ($ch === false) {
            throw new RuntimeException('Could not start Bob G request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw new RuntimeException('Bob G network error: ' . $error);
        }

        $decoded = json_decode((string) $body, true);
        if ($status >= 400) {
            $apiError = is_array($decoded) ? ($decoded['error']['message'] ?? $body) : $body;
            throw new RuntimeException('Bob G API error (' . $status . '): ' . $apiError);
        }

        $reply = $decoded['choices'][0]['message']['content'] ?? null;
        if (! is_string($reply) || trim($reply) === '') {
            throw new RuntimeException('Bob G returned an empty reply.');
        }

        return $reply;
    }

    /**
     * @param list<array{role?:string,content?:string}> $history
     * @return list<array{role:string,content:string}>
     */
    private function normalizeHistory(array $history): array
    {
        $clean = [];
        foreach (array_slice($history, -16) as $row) {
            $role = $row['role'] ?? '';
            $content = trim((string) ($row['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }

        return $clean;
    }

    public static function localReply(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'afrpay')) {
            return "AfrPay is a separate provider with three geos: Europe, Kazakhstan, and Tunisia. Do not reuse CashForo OR001/OB003 or Flamingo boards as AfrPay. If you do not have the original AfrPay onboarding/API, ask the owner for those files before generating live adaptors.\n\nI can still draft a Laravel stub that keeps the three geos separate, with sandbox-only flags, once you share the real endpoints.";
        }

        if (preg_match('/cashforo|or001|ob003/', $lower) === 1) {
            return "CashForo uses two products:\n- OR001 onramp (card → USDT/USDC → Readies)\n- OB003 open banking\n\nFor a Laravel drop-in, create `CashForoOnrampAdaptor` and `CashForoOpenBankingAdaptor` behind `PspAdaptorInterface`, plus `/webhooks/cashforo/OR001` and `/webhooks/cashforo/OB003`. Keep `LIVE_ENABLED=false` until pre-flight is green.\n\nConnect Bob G with `XAI_API_KEY` if you want me to generate the full files from Grok.";
        }

        if (preg_match('/fbls|p003|xcore|p004|psp/', $lower) === 1) {
            return "Known Readies PSP codes:\n- P003 FBLS\n- P004 Xcore (Europe: 3DS, name rules, signatures)\n- P005 next starter\n\nOn 0609 the existing harness lives at `/psp-sandbox` and should stay sandbox-only until checks are green. Tell me which provider and I will draft the Laravel controller, webhook middleware, and `.env` keys.";
        }

        if (preg_match('/function|controller|route|laravel|migrate/', $lower) === 1) {
            return "I can draft Laravel pieces for 0609:\n1. Route in `routes/web.php` or a dedicated routes file\n2. Controller under `app/Http/Controllers`\n3. Service under `app/Services`\n4. Blade view under `resources/views` extending `layouts.adminpanel`\n5. Migration if you need storage\n\nDescribe the function you want (name, input, output, who can use it). Connect `XAI_API_KEY` to have Bob G / Grok write the full files.";
        }

        return "I am Bob G, used from the BOB C tab.\n\nI can help you:\n- create Laravel functions for the 0609 backend\n- integrate PSPs (FBLS, Xcore, CashForo, AfrPay geos, Fena)\n- draft webhooks, signatures, and go-live gates\n\nGrok is not connected yet. Add `XAI_API_KEY` to `public_html/bob-c/.env` to switch this tab from the local helper to live Bob G.\n\nAsk a specific task, for example: “Draft a Laravel webhook for FBLS P003”.";
    }
}
