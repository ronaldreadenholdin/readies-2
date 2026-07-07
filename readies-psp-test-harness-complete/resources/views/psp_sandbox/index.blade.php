@extends('layouts.adminpanel')

@section('content')
@php
    $statusClasses = [
        'pass' => 'success',
        'warn' => 'warning',
        'fail' => 'danger',
        'skip' => 'secondary',
    ];

    $statusLabels = [
        'pass' => 'Pass',
        'warn' => 'Warning',
        'fail' => 'Fail',
        'skip' => 'Skipped',
    ];

    $scoreClass = function ($score) {
        if ($score >= 85) {
            return 'success';
        }

        if ($score >= 60) {
            return 'warning';
        }

        return 'danger';
    };
@endphp

<div class="container-fluid psp-sandbox-dashboard">
    <div class="psp-hero mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="text-uppercase small font-weight-bold mb-2">Sandbox Operations</div>
                <h1 class="mb-2">PSP Pre-Flight Test Harness</h1>
                <p class="mb-0">
                    Validate provider records, credentials, endpoints, callbacks, routing metadata, and dry-run payment payloads
                    before sending PSP traffic.
                </p>
            </div>
            <div class="col-lg-4 text-lg-right mt-4 mt-lg-0">
                <form method="GET" action="{{ url('/psp-sandbox') }}">
                    <input type="hidden" name="network" value="{{ $performHttpChecks ? 0 : 1 }}">
                    <button type="submit" class="btn btn-light btn-lg shadow-sm">
                        {{ $performHttpChecks ? 'Disable live endpoint ping' : 'Enable live endpoint ping' }}
                    </button>
                </form>
                <div class="small mt-3 opacity-75">
                    Generated {{ optional($summary['generated_at'])->format('M d, Y H:i:s') }}
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="metric-card shadow-sm">
                <div class="metric-label">Providers</div>
                <div class="metric-value">{{ $summary['providers'] }}</div>
                <div class="metric-help">{{ $summary['ready'] }} ready with no warnings or failures</div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="metric-card shadow-sm">
                <div class="metric-label">Average readiness</div>
                <div class="metric-value text-{{ $scoreClass($summary['average_score']) }}">{{ $summary['average_score'] }}%</div>
                <div class="metric-help">Based on pass / warn / fail check scores</div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="metric-card shadow-sm">
                <div class="metric-label">Passed checks</div>
                <div class="metric-value text-success">{{ $summary['passed'] }}</div>
                <div class="metric-help">{{ $summary['checks'] }} total checks</div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="metric-card shadow-sm">
                <div class="metric-label">Needs attention</div>
                <div class="metric-value text-danger">{{ $summary['failed'] }}</div>
                <div class="metric-help">{{ $summary['warnings'] }} warning(s), {{ $summary['skipped'] }} skipped</div>
            </div>
        </div>
    </div>

    @if($performHttpChecks)
        <div class="alert alert-info shadow-sm">
            <strong>Live endpoint ping enabled.</strong>
            The harness performs a safe GET request against the first configured HTTPS endpoint only. It does not create charges.
        </div>
    @else
        <div class="alert alert-secondary shadow-sm">
            <strong>Live endpoint ping disabled.</strong>
            Add <code>?network=1</code> or use the button above when you want the harness to test PSP endpoint reachability.
        </div>
    @endif

    @if($results->isEmpty())
        <div class="empty-state shadow-sm">
            <div class="empty-icon">PSP</div>
            <h4 class="mb-2">No PSP providers found</h4>
            <p class="text-muted mb-0">
                The harness could not load any <code>App\Models\PspProvider</code> records. Confirm the model, table, and seed data
                exist, then refresh this page.
            </p>
        </div>
    @else
        <div class="row">
            @foreach($results as $result)
                @php
                    $provider = $result['provider'];
                    $status = $result['status'];
                    $statusClass = $statusClasses[$status] ?? 'secondary';
                    $providerScoreClass = $scoreClass($result['score']);
                    $checksByCategory = $result['checks']->groupBy('category');
                @endphp

                <div class="col-12 mb-4">
                    <div class="provider-card shadow-sm">
                        <div class="provider-header">
                            <div>
                                <div class="d-flex flex-wrap align-items-center">
                                    <h4 class="mb-0 mr-3">{{ $provider['name'] ?: 'Unnamed provider' }}</h4>
                                    <span class="badge badge-{{ $statusClass }} text-uppercase">{{ $statusLabels[$status] ?? $status }}</span>
                                </div>
                                <div class="provider-meta mt-2">
                                    @if($provider['code'])
                                        <span>Code: <strong>{{ $provider['code'] }}</strong></span>
                                    @endif

                                    @if($provider['id'])
                                        <span>ID: <strong>{{ $provider['id'] }}</strong></span>
                                    @endif

                                    @if($provider['mode'])
                                        <span>Mode: <strong>{{ $provider['mode'] }}</strong></span>
                                    @endif
                                </div>
                            </div>

                            <div class="score-pill bg-{{ $providerScoreClass }}">
                                <span>{{ $result['score'] }}%</span>
                                <small>ready</small>
                            </div>
                        </div>

                        <div class="provider-summary row no-gutters">
                            <div class="col-sm-3">
                                <div class="summary-tile text-success">
                                    <strong>{{ $result['summary']['pass'] }}</strong>
                                    <span>passed</span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="summary-tile text-warning">
                                    <strong>{{ $result['summary']['warn'] }}</strong>
                                    <span>warnings</span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="summary-tile text-danger">
                                    <strong>{{ $result['summary']['fail'] }}</strong>
                                    <span>failed</span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="summary-tile text-muted">
                                    <strong>{{ $result['summary']['skip'] }}</strong>
                                    <span>skipped</span>
                                </div>
                            </div>
                        </div>

                        <div class="provider-body">
                            @foreach($checksByCategory as $category => $checks)
                                <div class="category-block">
                                    <div class="category-title">{{ ucwords(str_replace('_', ' ', $category)) }}</div>

                                    @foreach($checks as $check)
                                        @php
                                            $checkStatus = $check['status'];
                                            $checkClass = $statusClasses[$checkStatus] ?? 'secondary';
                                        @endphp

                                        <div class="check-row">
                                            <div class="check-status">
                                                <span class="badge badge-{{ $checkClass }}">{{ strtoupper($statusLabels[$checkStatus] ?? $checkStatus) }}</span>
                                            </div>
                                            <div class="check-content">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                                    <h6 class="mb-1">{{ $check['name'] }}</h6>
                                                </div>
                                                <div class="text-muted">{{ $check['message'] }}</div>
                                                <div class="recommendation mt-2">
                                                    <strong>Next step:</strong> {{ $check['recommendation'] }}
                                                </div>

                                                @if(!empty($check['meta']))
                                                    <details class="mt-2">
                                                        <summary>Show test details</summary>
                                                        <pre class="meta-pre">{{ json_encode($check['meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </details>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
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
    .psp-sandbox-dashboard {
        color: #172033;
    }

    .psp-hero {
        background: linear-gradient(135deg, #121a35 0%, #2752ff 55%, #22c1c3 100%);
        border-radius: 24px;
        color: #fff;
        overflow: hidden;
        padding: 34px;
        position: relative;
    }

    .psp-hero:after {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 999px;
        content: "";
        height: 220px;
        position: absolute;
        right: -60px;
        top: -90px;
        width: 220px;
    }

    .metric-card,
    .provider-card,
    .empty-state {
        background: #fff;
        border: 1px solid rgba(23, 32, 51, 0.07);
        border-radius: 18px;
    }

    .metric-card {
        height: 100%;
        padding: 24px;
    }

    .metric-label {
        color: #778099;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .metric-value {
        font-size: 42px;
        font-weight: 800;
        line-height: 1;
        margin: 12px 0;
    }

    .metric-help,
    .provider-meta {
        color: #778099;
        font-size: 13px;
    }

    .provider-card {
        overflow: hidden;
    }

    .provider-header {
        align-items: center;
        display: flex;
        justify-content: space-between;
        padding: 24px;
    }

    .provider-meta span {
        display: inline-block;
        margin-right: 16px;
    }

    .score-pill {
        align-items: center;
        border-radius: 999px;
        color: #fff;
        display: flex;
        flex-direction: column;
        height: 82px;
        justify-content: center;
        min-width: 82px;
    }

    .score-pill span {
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
    }

    .score-pill small {
        font-size: 11px;
        opacity: 0.85;
        text-transform: uppercase;
    }

    .provider-summary {
        border-bottom: 1px solid rgba(23, 32, 51, 0.08);
        border-top: 1px solid rgba(23, 32, 51, 0.08);
    }

    .summary-tile {
        padding: 18px 24px;
    }

    .summary-tile strong {
        display: block;
        font-size: 26px;
        line-height: 1;
    }

    .summary-tile span {
        color: #778099;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .provider-body {
        padding: 8px 24px 24px;
    }

    .category-block {
        margin-top: 22px;
    }

    .category-title {
        color: #778099;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.08em;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .check-row {
        align-items: flex-start;
        border: 1px solid rgba(23, 32, 51, 0.08);
        border-radius: 14px;
        display: flex;
        margin-bottom: 10px;
        padding: 16px;
    }

    .check-status {
        margin-right: 16px;
        min-width: 82px;
    }

    .check-content {
        flex: 1;
    }

    .recommendation {
        background: #f7f9fc;
        border-radius: 10px;
        color: #34405a;
        padding: 10px 12px;
    }

    .meta-pre {
        background: #101828;
        border-radius: 12px;
        color: #e6edf7;
        font-size: 12px;
        margin: 8px 0 0;
        padding: 14px;
        white-space: pre-wrap;
    }

    .empty-state {
        padding: 48px;
        text-align: center;
    }

    .empty-icon {
        align-items: center;
        background: #eef3ff;
        border-radius: 50%;
        color: #2752ff;
        display: inline-flex;
        font-weight: 800;
        height: 72px;
        justify-content: center;
        margin-bottom: 18px;
        width: 72px;
    }

    @media (max-width: 767.98px) {
        .psp-hero,
        .provider-header {
            padding: 22px;
        }

        .provider-header,
        .check-row {
            display: block;
        }

        .score-pill {
            margin-top: 16px;
        }

        .check-status {
            margin-bottom: 10px;
        }
    }
</style>
@endsection
