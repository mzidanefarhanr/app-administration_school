<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @include('letters.pdf.partials.styles')
</head>
<body>
    @include('letters.pdf.partials.letterhead', ['school' => $school, 'logo' => $logo])

    <div class="title">SURAT TUGAS</div>
    @if (! empty($letter['official_number']))
        <div class="number">Nomor: {{ $letter['official_number'] }}</div>
    @endif

    <p>Yang bertanda tangan di bawah ini, Kepala {{ $school['school_name'] }}, menugaskan kepada:</p>

    <table class="identity">
        <tr>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td>{{ $subject['name'] }}</td>
        </tr>
        <tr>
            <td class="label">NUPTK</td>
            <td class="sep">:</td>
            <td>{{ $subject['nuptk'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Jenis/Status</td>
            <td class="sep">:</td>
            <td>{{ $subject['type'] ?: '—' }} / {{ $subject['status'] ?: '—' }}</td>
        </tr>
    </table>

    <p>untuk melaksanakan tugas: {{ $fields['duty_description'] }}</p>

    <table class="identity">
        <tr>
            <td class="label">Waktu</td>
            <td class="sep">:</td>
            <td>{{ $dates['start'] ?? '—' }} s.d. {{ $dates['end'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Tempat</td>
            <td class="sep">:</td>
            <td>{{ $fields['location'] }}</td>
        </tr>
    </table>

    <p>Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.</p>

    @include('letters.pdf.partials.signature', [
        'school' => $school,
        'signatory' => $signatory,
        'dates' => $dates,
        'signature' => $signature,
        'stamp' => $stamp,
    ])
</body>
</html>
