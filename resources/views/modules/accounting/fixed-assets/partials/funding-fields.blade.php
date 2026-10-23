{{--
    "Funded by" for an asset added by hand: decides the credit side of the
    capitalization journal (Dr Fixed Assets). Only one account picker is enabled
    at a time, so only the relevant field is submitted.
    Expects: $prefix (unique per form), $equityAccounts, $cashBankAccounts, $vendors.
--}}
@php
    $source = old('funding_source', \App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService::FUNDING_OPENING_BALANCE);
@endphp
<div class="col-12" data-funding-fields>
    <div class="border rounded px-3 py-3">
        <label class="form-label fw-semibold fs-13 mb-2">Funded by <span class="text-danger">*</span></label>
        <div class="d-flex flex-wrap gap-3 mb-3 fs-13">
            @foreach ([
                'opening_balance' => 'Opening balance (owned before using this ERP)',
                'cash_bank' => 'Paid from cash / bank',
                'vendor' => 'Owed to a vendor',
            ] as $value => $label)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="funding_source" value="{{ $value }}" id="{{ $prefix }}_funding_{{ $value }}" @checked($source === $value)>
                    <label class="form-check-label" for="{{ $prefix }}_funding_{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>

        <div data-funding-for="opening_balance">
            <label class="form-label fs-12 text-muted" for="{{ $prefix }}_equity_account">Capital / equity account</label>
            <select class="form-select" name="funding_account_id" id="{{ $prefix }}_equity_account">
                <option value="">Select account...</option>
                @foreach ($equityAccounts as $account)
                    <option value="{{ $account->id }}" @selected((string) old('funding_account_id') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
            </select>
        </div>

        <div data-funding-for="cash_bank">
            <label class="form-label fs-12 text-muted" for="{{ $prefix }}_cash_account">Cash / bank account</label>
            <select class="form-select" name="funding_account_id" id="{{ $prefix }}_cash_account">
                <option value="">Select account...</option>
                @foreach ($cashBankAccounts as $account)
                    <option value="{{ $account->id }}" @selected((string) old('funding_account_id') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
            </select>
        </div>

        <div data-funding-for="vendor">
            <label class="form-label fs-12 text-muted" for="{{ $prefix }}_vendor">Vendor</label>
            <select class="form-select" name="vendor_id" id="{{ $prefix }}_vendor">
                <option value="">Select vendor...</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected((string) old('vendor_id') === (string) $vendor->id)>{{ $vendor->name }}</option>
                @endforeach
            </select>
        </div>

        <small class="form-text text-muted fs-11 d-block mt-2">
            Posts Dr Fixed Assets against this account. Recoverable GST is debited to Input Tax for cash/bank and vendor purchases.
        </small>
    </div>
</div>
