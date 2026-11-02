<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @include('letters.pdf.partials.styles')
</head>
<body>
    @include('letters.pdf.partials.letterhead', ['school' => $school, 'logo' => $logo])

    <div class="title">SURAT KETERANGAN AKTIF SISWA</div>
    @if (! empty($letter['official_number']))
        <div class="number">Nomor: {{ $letter['official_number'] }}</div>
    @endif

    <p>Yang bertanda tangan di bawah ini, Kepala {{ $school['school_name'] }}, dengan ini menerangkan bahwa:</p>

    <table class="identity">
        <tr>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td>{{ $subject['name'] }}</td>
        </tr>
        <tr>
            <td class="label">NIS</td>
            <td class="sep">:</td>
            <td>{{ $subject['nis'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">NISN</td>
            <td class="sep">:</td>
            <td>{{ $subject['nisn'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Tempat/Tanggal Lahir</td>
            <td class="sep">:</td>
            <td>{{ $subject['birthplace'] ?: '—' }}, {{ $dates['birthdate'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Jenis Kelamin</td>
            <td class="sep">:</td>
            <td>{{ $subject['gender'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td>
            <td class="sep">:</td>
            <td>{{ $subject['class'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Tahun Ajaran</td>
            <td class="sep">:</td>
            <td>{{ $subject['school_year'] ?: '—' }}</td>
        </tr>
    </table>

    <p>adalah benar siswa aktif pada {{ $school['school_name'] }} tahun ajaran {{ $subject['school_year'] ?: '—' }}.</p>

    <p>Surat keterangan ini dibuat untuk keperluan {{ $fields['purpose'] }}.</p>

    <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

    @include('letters.pdf.partials.signature', [
        'school' => $school,
        'signatory' => $signatory,
        'dates' => $dates,
        'signature' => $signature,
        'stamp' => $stamp,
    ])
</body>
</html>
