<?php

namespace App\Http\Controllers;

use App\Services\PspTestHarnessService;
use Illuminate\Http\Request;

class PspSandboxController extends Controller
{
    public function index(Request $request, PspTestHarnessService $harness)
    {
        $performHttpChecks = $request->boolean('network', false);
        $results = $harness->runAll($performHttpChecks);
        $summary = $harness->summary($results);

        return view('psp_sandbox.index', [
            'performHttpChecks' => $performHttpChecks,
            'results' => $results,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, PspTestHarnessService $harness, $provider)
    {
        $modelClass = 'App\\Models\\PspProvider';

        abort_unless(class_exists($modelClass), 404);

        $providerModel = $modelClass::query()->findOrFail($provider);

        return response()->json(
            $harness->runProvider($providerModel, $request->boolean('network', false))
        );
    }
}
