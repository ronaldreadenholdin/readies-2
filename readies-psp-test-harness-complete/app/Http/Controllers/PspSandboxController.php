<?php

namespace App\Http\Controllers;

use App\Models\PspProvider;
use App\Services\PspTestHarnessService;
use Illuminate\Http\Request;

class PspSandboxController extends Controller
{
    public function preFlightTest()
    {
        $path = base_path('standalone/pre-flight-test.html');

        if (! is_file($path)) {
            $path = base_path('pre-flight-test.html');
        }

        abort_unless(is_file($path), 404);

        return response()->file($path);
    }

    public function index(Request $request, PspTestHarnessService $harness)
    {
        $performHttpChecks = $request->boolean('network', false);
        $providerId = $request->input('provider');
        $results = $harness->runAll($performHttpChecks, $providerId);
        $summary = $harness->summary($results);

        return view('psp_sandbox.index', [
            'performHttpChecks' => $performHttpChecks,
            'results' => $results,
            'summary' => $summary,
        ]);
    }

    public function runTest(Request $request, PspTestHarnessService $harness, string $pspCode)
    {
        $provider = PspProvider::query()
            ->where('code', $pspCode)
            ->orWhere('slug', $pspCode)
            ->orWhere('key', $pspCode)
            ->orWhere('name', $pspCode)
            ->firstOrFail();

        return response()->json(
            $harness->runFullTest($provider, $request->all())
        );
    }

    public function show(Request $request, PspTestHarnessService $harness, $provider)
    {
        $providerModel = PspProvider::query()->findOrFail($provider);

        return response()->json(
            $harness->runProvider($providerModel, $request->boolean('network', false))
        );
    }

    public function goLive(Request $request, PspTestHarnessService $harness, string $pspCode)
    {
        $provider = PspProvider::query()
            ->where('code', $pspCode)
            ->orWhere('slug', $pspCode)
            ->orWhere('key', $pspCode)
            ->orWhere('name', $pspCode)
            ->firstOrFail();

        $result = $harness->runFullTest($provider, $request->all());
        $isGreen = ($result['status'] ?? null) === PspTestHarnessService::STATUS_PASS;

        if (! $isGreen) {
            return response()->json([
                'status' => 'blocked',
                'message' => 'Not all PSP pre-flight checks are green yet.',
                'result' => $result,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => sprintf('%s is ready for live integration.', $result['provider']['label'] ?? $pspCode),
            'result' => $result,
        ]);
    }
}
