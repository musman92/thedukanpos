<table class="brand-table">
    <tr>
        @if(! empty($company['logo_src']))
            <td class="logo-cell">
                <img src="{{ $company['logo_src'] }}" alt="" class="logo">
            </td>
        @endif
        <td class="company-cell">
            <p class="company-name">{{ $company['name'] }}</p>
            @if(! empty($company['address']))
                <p class="company-line">{{ $company['address'] }}</p>
            @endif
            @if(! empty($company['phone']))
                <p class="company-line">Tel: {{ $company['phone'] }}</p>
            @endif
            @if(! empty($company['email']))
                <p class="company-line">{{ $company['email'] }}</p>
            @endif
            @if(! empty($company['tax_id']))
                <p class="company-line">NTN: {{ $company['tax_id'] }}</p>
            @endif
        </td>
    </tr>
</table>
