<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class BobGClient
{
    public const FALLBACK_MODE = 'bob-g-workdesk';
    public const GROK_MODE = 'bob-g-grok';

    public function connected(): bool
    {
        return filled(config('bob_c.xai_api_key'));
    }

    public function work(): array
    {
        $paths = [
            resource_path('bob-g-work/catalog.json'),
            base_path('bob-c-0609/bob-g-work/catalog.json'),
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) {
                    return $decoded + ['functions' => ['list_work', 'recommend', 'advise', 'respond', 'go_live_gate']];
                }
            }
        }

        return ['agent' => 'Bob G', 'extension' => 'BOB C', 'completed' => [], 'open' => []];
    }

    public function status(): array
    {
        return [
            'agent' => 'BOB C',
            'assistant' => 'Bob G',
            'extends' => 'Bob G',
            'mode' => $this->connected() ? self::GROK_MODE : self::FALLBACK_MODE,
            'model' => $this->connected() ? config('bob_c.xai_model') : 'bob-g-workdesk',
            'connected' => $this->connected(),
            'site' => config('app.url', 'https://0609.readies.biz'),
            'work' => $this->work(),
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
            'model' => 'bob-g-workdesk',
            'reply' => $this->localReply($message),
            'source' => 'bob-g-workdesk',
            'desk' => $this->work(),
            'notice' => 'Using Bob G work desk. Add XAI_API_KEY if you want live Grok on top of this catalog.',
        ];
    }

    public function systemPrompt(): string
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
        $work = $this->work();

        if (preg_match('/work|what did|catalog|inventory|already built/', $lower) === 1) {
            $titles = collect($work['completed'] ?? [])->pluck('title')->filter()->values()->all();
            $open = collect($work['open'] ?? [])->pluck('title')->filter()->values()->all();
            return "BOB C extends Bob G. Already built:\n- ".implode("\n- ", $titles)."\n\nStill open:\n- ".implode("\n- ", $open);
        }

        if (str_contains($lower, 'afrpay')) {
            return 'Recover AfrPay Europe / Kazakhstan / Tunisia materials. Do not copy CashForo OR001/OB003.';
        }

        if (preg_match('/cashforo|or001|ob003|adaptor/', $lower) === 1) {
            return 'Bob G already created `PspAdaptorInterface`, `CashForoOnrampAdaptor` (OR001), and `CashForoOpenBankingAdaptor` (OB003). Map real CashForo docs onto those stubs. Do not write new adaptors.';
        }

        if (preg_match('/recommend|flagged|request list/', $lower) === 1) {
            return app(BobRecommendationService::class)->generate([
                ['name' => 'Webhook sample', 'details' => 'Need a signed webhook payload before go-live.'],
                ['name' => 'Signature header', 'details' => 'Confirm header name, secret, and canonical string.'],
            ]);
        }

        if (preg_match('/fbls|p003|xcore|p004|pre-flight|harness/', $lower) === 1) {
            return 'Bob G already built `PspTestHarnessService` and `/psp-sandbox` for FBLS P003 and Xcore P004. BOB C continues flagged checks and the go-live gate.';
        }

        return 'I am Bob G, used through BOB C. Continue existing work. Do not rebuild boards or adaptors that already exist.';
    }
}
