@extends('layouts.adminpanel')

@section('content')
@php
    $statusClasses = [
        'pass' => 'success',
        'warn' => 'warning',
        'fail' => 'danger',
    ];
@endphp

<div class="container-fluid psp-sandbox-dashboard">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h1 class="mb-1">PSP Pre-Flight Test Harness</h1>
            <p class="text-muted mb-0">Validate provider setup before routing real payment traffic.</p>
        </div>

        <form method="GET" action="{{ url('/psp-sandbox') }}" class="mt-3 mt-md-0">
            <input type="hidden" name="network" value="{{ $performHttpChecks ? 0 : 1 }}">
            <button type="submit" class="btn btn-{{ $performHttpChecks ? 'outline-secondary' : 'primary' }}">
                {{ $performHttpChecks ? 'Disable live endpoint ping' : 'Enable live endpoint ping' }}
            </button>
        </form>
    </div>

    <div class="row mb-4">
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Providers</div>
                    <div class="display-4 font-weight-bold">{{ $summary['providers'] }}</div>
                    <div class="text-muted">{{ $summary['ready'] }} fully ready</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Checks</div>
                    <div class="display-4 font-weight-bold">{{ $summary['checks'] }}</div>
                    <div class="text-muted">Configuration, credentials, API, webhook, payload</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Passed</div>
                    <div class="display-4 font-weight-bold text-success">{{ $summary['passed'] }}</div>
                    <div class="text-muted">Ready for sandbox validation</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Needs attention</div>
                    <div class="display-4 font-weight-bold text-danger">{{ $summary['failed'] }}</div>
                    <div class="text-muted">{{ $summary['warnings'] }} warning(s)</div>
                </div>
            </div>
        </div>
    </div>

    @if($performHttpChecks)
        <div class="alert alert-info shadow-sm">
            Live endpoint ping is enabled. The harness only performs safe GET requests and does not create charges.
        </div>
    @else
        <div class="alert alert-secondary shadow-sm">
            Live endpoint ping is disabled. Add <code>?network=1</code> or use the button above to test PSP endpoint reachability.
        </div>
    @endif

    @if($results->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <h4 class="mb-2">No PSP providers found</h4>
                <p class="text-muted mb-0">
                    The harness could not load any <code>PspProvider</code> records. Confirm the model, table, and seed data exist,
                    then refresh this page.
                </p>
            </div>
        </div>
    @else
        <div class="row">
            @foreach($results as $result)
                @php
                    $provider = $result['provider'];
                    $status = $result['status'];
                    $statusClass = $statusClasses[$status] ?? 'secondary';
                @endphp

                <div class="col-xl-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-0">{{ $provider['name'] ?: 'Unnamed provider' }}</h5>
                                <div class="text-muted small">
                                    @if($provider['code'])
                                        Code: {{ $provider['code'] }}
                                    @endif

                                    @if($provider['id'])
                                        <span class="ml-2">ID: {{ $provider['id'] }}</span>
                                    @endif
                                </div>
                            </div>

                            <span class="badge badge-{{ $statusClass }} text-uppercase">{{ $status }}</span>
                        </div>

                        <div class="card-body">
                            @foreach($result['checks'] as $check)
                                @php
                                    $checkStatus = $check['status'];
                                    $checkClass = $statusClasses[$checkStatus] ?? 'secondary';
                                @endphp

                                <div class="d-flex align-items-start py-3 border-bottom">
                                    <div class="mr-3">
                                        <span class="badge badge-{{ $checkClass }}">{{ strtoupper($checkStatus) }}</span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">{{ $check['name'] }}</h6>
                                        <div class="text-muted">{{ $check['message'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
    .psp-sandbox-dashboard .card {
        border-radius: 14px;
    }

    .psp-sandbox-dashboard .card-header {
        border-top-left-radius: 14px;
        border-top-right-radius: 14px;
    }
</style>
@endsection
