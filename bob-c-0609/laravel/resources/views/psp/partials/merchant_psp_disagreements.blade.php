{{-- Drop this into the existing 0609 merchant PSP / Payment providers tab. No notifications are sent. --}}
<section class="card">
    <h3>Disagreements</h3>
    <p class="text-muted">Rows where orchestration-owner actual position differs from the System/CODA suggestion. Status: Needs Gerardus discussion.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Merchant</th>
                <th>Connection</th>
                <th>Suggested</th>
                <th>Actual</th>
                <th>System/CODA reason</th>
                <th>TADDY / owner reason</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pspDisagreements ?? [] as $row)
            <tr>
                <td>{{ $row['merchant_id'] }}</td>
                <td>{{ $row['connection_code'] }}</td>
                <td>{{ $row['suggested_position'] }}</td>
                <td>{{ $row['actual_position'] }}</td>
                <td>{{ $row['suggested_reason'] }}</td>
                <td>{{ $row['actual_reason'] }}</td>
                <td>{{ $row['status'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7">No disagreements.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</section>
