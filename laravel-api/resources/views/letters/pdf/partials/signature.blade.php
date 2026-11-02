<table class="signature">
    <tr>
        <td class="space"></td>
        <td class="block">
            <div>{{ $school['place_of_issue'] }}, {{ $dates['letter'] ?? '—' }}</div>
            <div>Kepala {{ $school['school_name'] }}</div>
            <div class="images">
                @if (! empty($signature))
                    <img src="{{ $signature }}" class="signature-image" alt="">
                @endif
                @if (! empty($stamp))
                    <img src="{{ $stamp }}" class="stamp-image" alt="">
                @endif
            </div>
            <div class="name">{{ $signatory['name'] }}</div>
            @if (! empty($signatory['identifier_label']) || ! empty($signatory['identifier']))
                <div>{{ trim(($signatory['identifier_label'] ?? '').' '.($signatory['identifier'] ?? '')) }}</div>
            @endif
        </td>
    </tr>
</table>
