{{-- Drop this into the existing 0609 merchant PSP / Payment providers tab. No notifications are sent. --}}
<section class="card">
    <h3>New PSPs on trial</h3>
    <p class="text-muted">A new PSP may be placed higher than the stats-based suggestion to build a performance record. The 100% conformance rule still applies.</p>
    <p class="text-muted">If trial thresholds are unset, show: Trial length not set, needs Gerardus.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Merchant</th>
                <th>Connection</th>
                <th>Trial position</th>
                <th>Suggested position</th>
                <th>Set by</th>
                <th>Why</th>
                <th>Progress</th>
                <th>Live allowed</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pspTrialRows ?? [] as $row)
            <tr>
                <td>{{ $row['merchant_id'] }}</td>
                <td>{{ $row['connection_code'] }}</td>
                <td>{{ $row['trial_position'] }}</td>
                <td>{{ $row['suggested_position'] }}</td>
                <td>{{ $row['set_by'] }} at {{ $row['set_at'] }}</td>
                <td>{{ $row['why'] }}</td>
                <td>
                    {{ $row['message'] }}<br>
                    Transactions: {{ $row['transactions'] }}
                    @if($row['trial_min_transactions'] !== null)
                        / {{ $row['trial_min_transactions'] }}
                    @endif
                    <br>
                    Days: {{ $row['days_elapsed'] }}
                    @if($row['trial_max_days'] !== null)
                        / {{ $row['trial_max_days'] }}
                    @endif
                </td>
                <td>{{ ! empty($row['live_position_allowed']) ? 'yes' : 'no - planned only' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8">No PSPs are on trial.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</section>
