{{--
    Make a verified customer/vendor inactive (with a reason) or reactivate it (FRS 2.3, C-16).
    Expects: $party, $type ('customer'|'vendor'), $mode ('deactivate'|'reactivate'), $reasons,
             $blockedReason (string|null), $statusService, $storeRoute, $backRoute.
--}}
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm" style="max-width: 640px;">
    @if($mode === 'deactivate')
        <h5 class="fw-bold mb-1">Make {{ $party->name }} inactive</h5>
        <p class="text-muted fs-12 mb-3">
            While inactive, this {{ $type }} cannot be used for new
            {{ $type === 'customer' ? 'sales orders or dispatches' : 'purchase orders, RFQs, advance payments or subcontract challans' }}.
            Existing documents, invoices/bills, receipts, returns and payments are not affected.
            If you choose a risk reason, only a different authorised person can reactivate it.
        </p>

        <form method="POST" action="{{ $storeRoute }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <select name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                    <option value="">Choose a reason</option>
                    @foreach($reasons as $key => $info)
                        <option value="{{ $key }}" @selected(old('reason') === $key)>
                            {{ $info['label'] }}{{ $info['risk'] ? ' (risk: needs a different person to reactivate)' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Note</label>
                <textarea name="note" rows="3" maxlength="1000" class="form-control @error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                <div class="form-text">Required when the reason is "Other".</div>
                @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-danger">Make inactive</button>
            <a href="{{ $backRoute }}" class="btn btn-light border ms-1">Cancel</a>
        </form>
    @else
        <h5 class="fw-bold mb-1">Reactivate {{ $party->name }}</h5>

        <div class="border rounded-3 p-3 mb-3 bg-light fs-13">
            <div><span class="text-muted">Made inactive:</span>
                {{ $party->inactivated_at?->format('d M Y H:i') ?? 'date not recorded' }}
                @if($party->inactivator) by {{ $party->inactivator->name }} @endif</div>
            <div><span class="text-muted">Reason:</span> {{ $statusService->reasonLabel($party->inactive_reason) }}</div>
            @if($party->inactive_note)
                <div><span class="text-muted">Note:</span> {{ $party->inactive_note }}</div>
            @endif
        </div>

        @if($blockedReason)
            <div class="alert alert-warning fs-13">{{ $blockedReason }}</div>
            <a href="{{ $backRoute }}" class="btn btn-light border">Back</a>
        @else
            <form method="POST" action="{{ $storeRoute }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Note</label>
                    <textarea name="note" rows="3" maxlength="1000" class="form-control" placeholder="Why is it safe to reactivate?">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="btn btn-success">Reactivate</button>
                <a href="{{ $backRoute }}" class="btn btn-light border ms-1">Cancel</a>
            </form>
        @endif
    @endif
</div>
