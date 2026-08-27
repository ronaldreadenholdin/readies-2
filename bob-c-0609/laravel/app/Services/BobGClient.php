<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class BobGClient
{
    public const FALLBACK_MODE = 'local-helper';
    public const GROK_MODE = 'bob-g-grok';

    public function connected(): bool
    {
        return filled(config('bob_c.xai_api_key'));
    }

    public function status(): array
    {
        return [
            'agent' => 'BOB C',
            'assistant' => 'Bob G',
            'mode' => $this->connected() ? self::GROK_MODE : self::FALLBACK_MODE,
            'model' => $this->connected() ? config('bob_c.xai_model') : 'readies-local-helper',
            'connected' => $this->connected(),
            'site' => config('app.url', 'https://0609.readies.biz'),
        ];
    }

    public function ask(string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Ask Bob G a question first.');
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $this->normalizeHistory($history),
            [['role' => 'user', 'content' => $message]],
        );

        if ($this->connected()) {
            return [
                'ok' => true,
                'mode' => self::GROK_MODE,
                'model' => config('bob_c.xai_model'),
                'reply' => $this->callGrok($messages),
                'source' => 'bob-g',
            ];
        }

        return [
            'ok' => true,
            'mode' => self::FALLBACK_MODE,
            'model' => 'readies-local-helper',
            'reply' => $this->localReply($message),
            'source' => 'local-helper',
            'notice' => 'Bob G Grok is not connected yet. Add XAI_API_KEY to the 0609 .env.',
        ];
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Bob G, a Grok-powered Readies / Okepay backend assistant.
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

    private function callGrok(array $messages): string
    {
        $response = Http::timeout(60)
            ->withToken((string) config('bob_c.xai_api_key'))
            ->acceptJson()
            ->post(rtrim((string) config('bob_c.xai_base_url'), '/') . '/chat/completions', [
                'model' => config('bob_c.xai_model'),
                'messages' => $messages,
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            $error = data_get($response->json(), 'error.message', $response->body());
            throw new RuntimeException('Bob G API error (' . $response->status() . '): ' . $error);
        }

        $reply = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($reply) || trim($reply) === '') {
            throw new RuntimeException('Bob G returned an empty reply.');
        }

        return $reply;
    }

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

    public function localReply(string $message): string
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

        return "I am Bob G, used from the BOB C tab.\n\nI can help you:\n- create Laravel functions for the 0609 backend\n- integrate PSPs (FBLS, Xcore, CashForo, AfrPay geos, Fena)\n- draft webhooks, signatures, and go-live gates\n\nGrok is not connected yet. Add `XAI_API_KEY` to the 0609 `.env` to switch this tab from the local helper to live Bob G.\n\nAsk a specific task, for example: “Draft a Laravel webhook for FBLS P003”.";
    }
}
