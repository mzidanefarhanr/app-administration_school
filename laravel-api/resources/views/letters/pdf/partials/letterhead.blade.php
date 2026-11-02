<table class="kop">
    <tr>
        @if (! empty($logo))
            <td class="kop-logo"><img src="{{ $logo }}" alt=""></td>
        @endif
        <td class="kop-text">
            @if (! empty($school['government_line']))
                <div class="kop-government">{{ $school['government_line'] }}</div>
            @endif
            <div class="kop-school">{{ $school['school_name'] }}</div>
            @if (! empty($school['address_line']))
                <div>{{ $school['address_line'] }}</div>
            @endif
            @if (! empty($school['contact_line']))
                <div>{{ $school['contact_line'] }}</div>
            @endif
        </td>
    </tr>
</table>
<div class="kop-line"></div>
<div class="kop-line-thin"></div>
