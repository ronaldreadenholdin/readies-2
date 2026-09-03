<?php

namespace App\Http\Controllers;

use App\Services\TrustedListService;
use Illuminate\Http\Request;

class TrustedListController extends Controller
{
    public function status(TrustedListService $list)
    {
        return response()->json([
            'ok' => true,
            'list' => 'FTD vs trusted',
            'scope' => 'every provider',
            'match_order' => ['email', 'phone', 'card_first6_last4', 'birthday', 'full_name'],
            'rule' => 'not on the list → FTD; on the list or paid once successfully → trusted',
        ]);
    }

    public function classify(Request $request, TrustedListService $list)
    {
        return response()->json(['ok' => true] + $list->classify($request->all()));
    }

    public function paid(Request $request, TrustedListService $list)
    {
        return response()->json(['ok' => true] + $list->markPaid($request->all()));
    }
}
