<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

header('X-Content-Type-Options: nosniff');

$list = new TrustedList(ftd_root() . '/storage/trusted-list.json');
$action = $_GET['action'] ?? $_POST['action'] ?? (ftd_json_input()['action'] ?? 'status');

try {
    if ($action === 'status') {
        ftd_json([
            'ok' => true,
            'list' => 'FTD vs trusted',
            'scope' => 'every provider',
            'trusted_count' => $list->count(),
            'match_order' => [
                'email',
                'phone',
                'card_first6_last4',
                'birthday',
                'full_name',
            ],
            'biz_column' => 'biz',
            'biz_values' => TrustedList::BIZ_VALUES,
            'rule' => 'not on the list → FTD; on the list or paid once successfully → trusted',
        ]);
    }

    if ($action === 'classify' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = $list->classify(ftd_json_input());
        ftd_json(['ok' => true] + $result);
    }

    if ($action === 'paid' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = $list->markPaid(ftd_json_input());
        ftd_json(['ok' => true] + $result);
    }

    ftd_json(['ok' => false, 'error' => 'Unknown FTD/trusted action.'], 404);
} catch (InvalidArgumentException $e) {
    ftd_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    ftd_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
