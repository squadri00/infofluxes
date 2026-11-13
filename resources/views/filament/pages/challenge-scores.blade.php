@if ($rows->isEmpty())
    <p>No test has been finished yet.</p>
@else
    <table style="width:100%;border-collapse:collapse">
        <thead><tr style="text-align:left"><th style="padding:.4rem">Test</th><th style="padding:.4rem">Rounds finished</th><th style="padding:.4rem">Average score</th></tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr style="border-top:1px solid rgba(128,128,128,.3)">
                    <td style="padding:.4rem">{{ $row->quiz }}</td>
                    <td style="padding:.4rem">{{ $row->rounds }}</td>
                    <td style="padding:.4rem">{{ $row->total > 0 ? round($row->score / $row->total * 100) : 0 }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
