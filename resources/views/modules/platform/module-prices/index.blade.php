@extends('layouts.duralux')

@section('title', 'Add-on Prices | SaaS ERP')
@section('page-title', 'Add-on Prices')
@section('breadcrumb', 'Tenant Console / Add-on Prices')

@section('content')
    <form action="{{ route('platform.module-prices.update') }}" method="POST">
        @csrf
        @method('PUT')

        <x-ui.card>
            <div class="mb-3">
                <span class="text-muted fs-12 text-uppercase">Module add-ons</span>
                <p class="fs-13 text-muted mb-0">
                    Per user per month, in whole {{ $currency }}, excluding GST ({{ rtrim(rtrim(number_format(config('billing.gst_rate'), 2), '0'), '.') }}% is added at checkout).
                    Tenants add these on top of their plan; a module their plan already includes is never charged.
                    Leave a price blank to stop selling that module on that cycle.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th style="width: 220px">Monthly billing</th>
                            <th style="width: 220px">Billed yearly</th>
                            <th style="width: 110px">For sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $key = $row['module']; @endphp
                            <tr>
                                <td class="fw-semibold text-dark">{{ $row['label'] }}</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ $currency }}</span>
                                        <input type="number" min="0" class="form-control @error("prices.$key.monthly_price_per_user") is-invalid @enderror"
                                            name="prices[{{ $key }}][monthly_price_per_user]"
                                            value="{{ old("prices.$key.monthly_price_per_user", $row['price']->monthly_price_per_user) }}">
                                        <span class="input-group-text">/user/mo</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ $currency }}</span>
                                        <input type="number" min="0" class="form-control @error("prices.$key.yearly_price_per_user") is-invalid @enderror"
                                            name="prices[{{ $key }}][yearly_price_per_user]"
                                            value="{{ old("prices.$key.yearly_price_per_user", $row['price']->yearly_price_per_user) }}">
                                        <span class="input-group-text">/user/mo</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="hidden" name="prices[{{ $key }}][is_active]" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="prices[{{ $key }}][is_active]" value="1"
                                            @checked(old("prices.$key.is_active", $row['price']->is_active))>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-slot name="footer">
                <div class="d-flex justify-content-end">
                    <x-ui.button type="submit" variant="primary" icon="feather-check-circle">Save Prices</x-ui.button>
                </div>
            </x-slot>
        </x-ui.card>
    </form>
@endsection
