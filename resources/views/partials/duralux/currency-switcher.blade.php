@php
    $currentCode = active_currency();
    $currentInfo = active_currency_info();
    $currencies = config('currency.currencies', []);
@endphp

<div class="dropdown nxl-h-item nxl-header-language">
    <a href="javascript:void(0);" class="nxl-head-link me-0 d-flex align-items-center gap-1" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Select Currency" title="Select Currency">
        <span class="avatar-text avatar-sm bg-soft-primary text-primary fw-bold fs-12 me-1">{{ $currentInfo['symbol'] }}</span>
        <span class="fw-semibold text-dark fs-12 d-none d-sm-inline">{{ $currentCode }}</span>
        <i class="feather-chevron-down fs-11 text-muted ms-1"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown">
        <div class="px-4 py-3 border-bottom">
            <h6 class="mb-0 text-dark fw-bold">Select Currency</h6>
            <p class="fs-11 text-muted mb-0">Production Module Currency</p>
        </div>
        <div class="py-2">
            @foreach ($currencies as $code => $currency)
                <a href="{{ route('currency.switch', $code) }}" class="dropdown-item d-flex align-items-center justify-content-between py-2 px-4 {{ $currentCode === $code ? 'active bg-light' : '' }}">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-text avatar-xs bg-soft-primary text-primary fw-bold fs-12">{{ $currency['symbol'] }}</span>
                        <div>
                            <span class="d-block fw-bold fs-12 text-dark">{{ $currency['name'] }}</span>
                            <span class="fs-10 text-muted">{{ $code }} &bull; Rate: {{ $currency['rate'] }}</span>
                        </div>
                    </div>
                    @if ($currentCode === $code)
                        <i class="feather-check text-primary fs-14 ms-3"></i>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
