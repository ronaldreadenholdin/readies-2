<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TrustedListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 0609 admin backend. Staff upload and maintain the trusted list for a merchant.
 * Merchants do not upload.
 */
class FtdTrustedAdminController extends Controller
{
    public function __construct(private readonly TrustedListService $trustedList)
    {
    }

    public function index(): View
    {
        return view('admin.ftd-trusted.index');
    }

    public function classify(Request $request): JsonResponse
    {
        $validated = $this->customerPayload($request);
        $result = $this->trustedList->classify($validated);

        return response()->json(['ok' => true] + $result);
    }

    public function paid(Request $request): JsonResponse
    {
        $validated = $this->customerPayload($request);
        $result = $this->trustedList->markPaidSuccessfully($validated['merchant_id'], $validated);

        return response()->json(['ok' => true] + $result);
    }

    public function upload(Request $request): JsonResponse
    {
        $merchant = (string) ($request->input('merchant_id') ?: $request->input('merchant', ''));
        $request->merge(['merchant_id' => $merchant]);
        $request->validate([
            'merchant_id' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file'],
        ]);

        $path = $request->file('file')->getRealPath();
        $result = $this->trustedList->replaceMerchantList($merchant, (string) $path);

        return response()->json(['ok' => true] + $result);
    }

    /**
     * @return array<string, string>
     */
    private function customerPayload(Request $request): array
    {
        $request->merge([
            'merchant_id' => (string) ($request->input('merchant_id') ?: $request->input('merchant', '')),
        ]);
        $validated = $request->validate([
            'merchant_id' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'card_first6' => ['nullable', 'string', 'max:6'],
            'card_last4' => ['nullable', 'string', 'max:4'],
            'birthday' => ['nullable', 'string', 'max:32'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['merchant'] = $validated['merchant_id'];

        foreach (['card_first6', 'card_last4'] as $field) {
            if (!empty($validated[$field]) && !ctype_digit((string) $validated[$field])) {
                abort(422, $field . ' must be digits only. Never send a full card number.');
            }
        }

        return $validated;
    }
}
