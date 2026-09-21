<x-ui.card title="GST &amp; TDS" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-slot:headerAction>
        <a href="{{ route('accounting.reports.gst-summary') }}" class="fs-12">GST Summary <i class="feather-arrow-right"></i></a>
    </x-slot:headerAction>
    <x-ui.table>
        <tbody class="fs-13 text-dark">
            <tr class="table-light"><td class="ps-4 fw-semibold" colspan="2">Return for {{ $gst['return_month'] }}</td></tr>
            <tr><td class="ps-4">Output GST</td><td class="text-end pe-4">{{ $money($gst['output']) }}</td></tr>
            <tr><td class="ps-4">Input tax credit</td><td class="text-end pe-4">({{ $money($gst['input']) }})</td></tr>
            <tr class="fw-semibold"><td class="ps-4">{{ $gst['payable'] < 0 ? 'Input credit carried forward' : 'Net GST payable' }}</td><td class="text-end pe-4">{{ $money(abs($gst['payable'])) }}</td></tr>
            @foreach (['GSTR-1' => $gst['gstr1_due'], 'GSTR-3B' => $gst['gstr3b_due']] as $return => $due)
                @php
                    $daysLeft = (int) round($today->diffInDays($due, false));
                @endphp
                <tr>
                    <td class="ps-4">{{ $return }} due {{ $due->format('d M') }}</td>
                    <td class="text-end pe-4">
                        <span class="badge {{ $daysLeft < 0 ? 'bg-soft-secondary text-secondary' : ($daysLeft <= 3 ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning') }}">
                            {{ $daysLeft < 0 ? 'Date passed' : ($daysLeft === 0 ? 'Due today' : $daysLeft . ' day(s) left') }}
                        </span>
                    </td>
                </tr>
            @endforeach
            <tr class="table-light"><td class="ps-4 fw-semibold" colspan="2">Building up</td></tr>
            <tr><td class="ps-4">{{ $gst['accruing_payable'] < 0 ? 'Input credit carried forward' : 'GST payable' }} for {{ $gst['accruing_month'] }} so far</td><td class="text-end pe-4">{{ $money(abs($gst['accruing_payable'])) }}</td></tr>
            <tr><td class="ps-4">TDS payable</td><td class="text-end pe-4">{{ $money($tdsPayable) }}</td></tr>
        </tbody>
    </x-ui.table>
</x-ui.card>
