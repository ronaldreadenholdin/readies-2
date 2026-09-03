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
            'biz_column' => 'biz',
            'biz_values' => ['gambling', 'gaming', 'mlm', 'food_supplements', 'pharma', 'forex', 'digital_products', 'other'],
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

    public function upload(Request $request, TrustedListService $list)
    {
        $file = $request->file('file');
        $csv = $file ? (string) file_get_contents($file->getRealPath()) : (string) $request->input('csv', '');

        return response()->json(['ok' => true] + $list->replaceFromCsv((string) $request->input('merchant', ''), $csv));
    }
}
