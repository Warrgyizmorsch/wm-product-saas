{{-- Cash and bank accounts with their balance (ui-reference "bank-liquidity-summary"). --}}
<section class="ax-section mb-3 h-100">
    <div class="ax-section-head">
        <h2 class="ax-section-title text-uppercase" style="letter-spacing: .06em; font-size: 12px;">Cash &amp; Bank Accounts</h2>
        <a href="{{ route('accounting.bank-reconciliation.index') }}" class="fs-12 fw-semibold">Reconcile <i class="feather-arrow-right"></i></a>
    </div>
    <div class="ax-section-body py-2">
        @forelse ($cash['accounts'] as $row)
            <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                <span class="ax-kpi-icon ax-tone-neutral" style="width: 32px; height: 32px; flex-basis: 32px;"><i class="feather-home"></i></span>
                <span class="flex-grow-1 min-w-0">
                    <span class="d-block fs-13 fw-semibold text-dark text-truncate">{{ $row['account']->name }}</span>
                    <span class="d-block ax-mono fs-11 text-muted">{{ $row['account']->code }}</span>
                </span>
                <span class="ax-mono fs-13 fw-semibold {{ $row['balance'] < 0 ? 'text-danger' : 'text-dark' }}">{{ $money($row['balance']) }}</span>
            </div>
        @empty
            <div class="ax-empty"><i class="feather-home"></i>No cash or bank activity yet.</div>
        @endforelse
    </div>
</section>
