{{--
    Dr/Cr side for a party's opening balance.
    Expects: $party ('customer' | 'vendor'), optional $selected ('debit' | 'credit').
--}}
@php
    $options = $party === 'vendor'
        ? ['credit' => 'We owe the vendor (Cr)', 'debit' => 'Advance paid to vendor (Dr)']
        : ['debit' => 'Customer owes us (Dr)', 'credit' => 'Advance received from customer (Cr)'];
    $current = old('opening_balance_type', $selected ?? array_key_first($options));
@endphp
<div class="mb-3">
    <label class="form-label fw-semibold text-dark" for="opening_balance_type">Opening Balance Type</label>
    <select name="opening_balance_type" id="opening_balance_type" class="form-select @error('opening_balance_type') is-invalid @enderror">
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <small class="form-text text-muted fs-11">Posted to the ledger on the first day of your first financial year.</small>
    @error('opening_balance_type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
