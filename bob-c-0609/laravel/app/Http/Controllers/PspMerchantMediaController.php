<?php

namespace App\Http\Controllers;

use App\Services\Psp\Media\WaitingMediaRepositoryInterface;
use Illuminate\Http\Request;

class PspMerchantMediaController extends Controller
{
    public function __construct(private WaitingMediaRepositoryInterface $media)
    {
        // Intentionally no routes are added in this package. Wire behind auth in the full host.
    }

    public function index(Request $request)
    {
        return response()->json([
            'ok' => true,
            'clips' => $this->media->approvedPlatformClips(),
        ]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'string'],
            'media_id' => ['required', 'string'],
            'slot' => ['required', 'in:redirect_wait,cascade_wait,final_status_wait'],
        ]);

        return response()->json([
            'ok' => true,
            'consent' => $this->media->approve($data['merchant_id'], $data['media_id'], $data['slot'], (string) optional($request->user())->id),
        ]);
    }

    public function revoke(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'string'],
            'media_id' => ['required', 'string'],
            'slot' => ['required', 'in:redirect_wait,cascade_wait,final_status_wait'],
        ]);

        return response()->json([
            'ok' => true,
            'consent' => $this->media->revoke($data['merchant_id'], $data['media_id'], $data['slot'], (string) optional($request->user())->id),
        ]);
    }
}
