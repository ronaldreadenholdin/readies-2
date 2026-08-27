<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Allow: GET, POST, OPTIONS');
    exit;
}

bob_c_require_token();

$client = BobGClient::fromEnv();
$store = new ConversationStore(__DIR__ . '/storage/conversation.json');
$action = $_GET['action'] ?? $_POST['action'] ?? (bob_c_json_input()['action'] ?? 'status');

try {
    if ($action === 'status' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        bob_c_json(['ok' => true] + $client->status() + [
            'history_count' => count($store->all()),
        ]);
    }

    if ($action === 'history' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        bob_c_json([
            'ok' => true,
            'messages' => $store->all(),
        ]);
    }

    if ($action === 'clear' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $store->clear();
        bob_c_json(['ok' => true, 'messages' => []]);
    }

    if ($action === 'ask' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $input = bob_c_json_input();
        $message = trim((string) ($input['message'] ?? ''));
        $history = $store->all();
        $result = $client->ask($message, $history);
        $store->add('user', $message);
        $store->add('assistant', $result['reply']);

        bob_c_json($result + [
            'messages' => $store->all(),
        ]);
    }

    bob_c_json(['ok' => false, 'error' => 'Unknown BOB C action.'], 404);
} catch (InvalidArgumentException $e) {
    bob_c_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    bob_c_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
