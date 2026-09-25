{{-- Drop this into the existing 0609 merchant PSP / Payment providers tab. --}}
<section class="card">
    <h3>PSP cascade suggestions</h3>
    <p class="text-muted">Integration status: pending server access to /var/www/html/adapter. Suggested position is system/CODA; actual position is the orchestration-owner setting.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Connection</th>
                <th>Suggested position</th>
                <th>Actual position</th>
                <th>Score</th>
                <th>Live allowed</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pspOrderRows ?? [] as $row)
            <tr>
                <td>{{ $row['connection_code'] }}</td>
                <td>{{ $row['suggested_position'] }}</td>
                <td>{{ $row['actual_position'] }}</td>
                <td>{{ number_format((float) $row['score_percent'], 2) }}%</td>
                <td>{{ ! empty($row['live_position_allowed']) ? 'yes' : 'planned only' }}</td>
                <td>{{ $row['suggested_reason'] ?? 'Eligibility, cost, and conformance.' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No PSP connections are available for this merchant yet.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</section>
