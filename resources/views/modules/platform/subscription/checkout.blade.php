@extends('layouts.duralux')

@section('title', 'Subscribe | SaaS ERP')
@section('page-title', 'Subscribe')
@section('breadcrumb', 'Tenant Console / Subscription / Subscribe')

@php
    // GST state codes (first two digits of a GSTIN) — picks the state from the GSTIN.
    $gstStates = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];
@endphp

@section('content')
    <div class="erp-checkout" id="billingCheckout">
        @if (session('error'))
            <div class="alert alert-danger fs-13">{{ session('error') }}</div>
        @endif
        @if ($liveSubscription && ! $subscribed && ! $subscriptionChanged)
            <div class="alert alert-info fs-13">
                You're subscribed to {{ $liveSubscription->plan?->name }} for {{ $liveSubscription->seats }} users, billed {{ $liveSubscription->cycle }}
                @if ($liveSubscription->current_end)
                    (renews {{ $liveSubscription->current_end->format('d M Y') }})
                @endif.
                Change the plan, users or add-ons below: an upgrade is charged pro rata for the rest of this period and applies right away;
                a downgrade applies from your renewal.
                @if ($scheduledChange)
                    <div class="mt-1 fw-semibold">
                        Booked for {{ $scheduledChange['on'] }}: {{ $scheduledChange['plan'] }} for {{ $scheduledChange['seats'] }} users.
                        A new change replaces it.
                    </div>
                @endif
            </div>
        @endif
        <ol class="erp-checkout-steps mb-4">
            @foreach (['Plan', 'Add-Ons', 'Pay', 'Confirmation'] as $i => $label)
                <li data-step-dot="{{ $i + 1 }}" class="{{ $i === 0 ? 'active' : '' }}">
                    <span class="erp-checkout-step-num">{{ $i + 1 }}</span>
                    <span class="erp-checkout-step-label">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        <div class="row g-4">
            <div class="col-xl-8">
                {{-- Step 1: plan, billing cycle, users --}}
                <section data-step="1">
                    <x-ui.card class="mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                            <div>
                                <h5 class="mb-1">Choose your plan</h5>
                                <p class="fs-13 text-muted mb-0">Priced per user per month. GST ({{ rtrim(rtrim(number_format($gstRate, 2), '0'), '.') }}%) is added at checkout.</p>
                            </div>
                            <div class="btn-group erp-cycle-toggle" role="group" aria-label="Billing cycle">
                                @foreach ($cycles as $cycle)
                                    <input type="radio" class="btn-check" name="cycle" id="cycle_{{ $cycle['key'] }}" value="{{ $cycle['key'] }}" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="cycle_{{ $cycle['key'] }}">
                                        {{ $cycle['key'] === 'yearly' ? 'Billed yearly' : 'Month-to-month' }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        @if ($plans->isEmpty())
                            <div class="text-center py-5">
                                <p class="text-muted mb-3">No plans are on sale yet.</p>
                                @if ($canManagePrices)
                                    <p class="fs-13 text-muted mb-3">A plan is priced from its modules' per-user prices — set every module's price, or give the plan its own price.</p>
                                    <a href="{{ route('platform.module-prices.index') }}" class="btn btn-primary btn-sm">Set add-on prices</a>
                                    <a href="{{ route('platform.plans.index') }}" class="btn btn-light border btn-sm">Plans</a>
                                @else
                                    <a href="{{ route('platform.subscription.index') }}" class="btn btn-light border btn-sm">Back to Subscription</a>
                                @endif
                            </div>
                        @else
                            <div class="row g-3" id="planCards">
                                @foreach ($plans as $plan)
                                    <div class="col-md-6 col-xxl-4">
                                        <label class="card h-100 erp-plan-card mb-0" data-plan-card="{{ $plan['id'] }}">
                                            <input type="radio" name="plan_id" value="{{ $plan['id'] }}" class="d-none">
                                            <div class="card-body text-center py-4">
                                                <h6 class="text-uppercase fw-bolder text-primary mb-3">{{ $plan['name'] }}</h6>
                                                <div class="erp-plan-price">
                                                    <span class="fs-13 align-top">{{ $currency === 'INR' ? '₹' : $currency.' ' }}</span><span class="fs-2 fw-bolder text-dark" data-plan-price="{{ $plan['id'] }}">—</span>
                                                </div>
                                                <div class="fs-12 text-muted mb-1">/user/month <span data-cycle-note></span></div>
                                                <div class="fs-12 text-muted" data-plan-alt="{{ $plan['id'] }}"></div>
                                                @if ($plan['description'])
                                                    <p class="fs-13 text-muted mt-3 mb-0">{{ $plan['description'] }}</p>
                                                @endif
                                                <p class="fs-12 mt-3 mb-0">
                                                    @if ($plan['features'] === null)
                                                        All modules included
                                                    @else
                                                        {{ collect($plan['features'])->map(fn ($m) => config("navigation.apps.$m.label", ucfirst($m)))->implode(' · ') }}
                                                    @endif
                                                </p>
                                                <div class="fs-12 text-danger mt-2 d-none" data-plan-unavailable="{{ $plan['id'] }}">Not available on this billing cycle</div>
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="erp-seat-picker mt-4">
                                <div>
                                    <label for="seats" class="fw-semibold text-dark d-block">Number of users</label>
                                    <span class="fs-12 text-muted">Your workspace has {{ $minimumSeats }} {{ \Illuminate\Support\Str::plural('user', $minimumSeats) }} today — you can't go below that.</span>
                                </div>
                                <div class="input-group" style="max-width: 170px">
                                    <button class="btn btn-light border" type="button" data-seat-step="-1" aria-label="Fewer users">−</button>
                                    <input type="number" class="form-control text-center" id="seats" min="{{ $minimumSeats }}" value="{{ $selection['seats'] }}">
                                    <button class="btn btn-light border" type="button" data-seat-step="1" aria-label="More users">+</button>
                                </div>
                            </div>
                        @endif
                    </x-ui.card>
                </section>

                {{-- Step 2: add-ons --}}
                <section data-step="2" class="d-none">
                    <x-ui.card class="mb-4">
                        <h5 class="mb-1">Add modules</h5>
                        <p class="fs-13 text-muted mb-4">Modules your plan doesn't include, billed per user alongside it. Remove any later — the charge stops at renewal and the data is kept.</p>
                        <div class="row g-3">
                            @foreach ($modules as $module)
                                <div class="col-sm-6 col-lg-4" data-addon="{{ $module['key'] }}">
                                    <label class="card h-100 erp-addon-card mb-0">
                                        <div class="card-body d-flex gap-3 align-items-start">
                                            <span class="erp-app-icon erp-addon-icon" style="background: {{ $module['color'] }}"><i class="{{ $module['icon'] }}"></i></span>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between gap-2">
                                                    <span class="fw-bolder text-dark">{{ $module['label'] }}</span>
                                                    <input type="checkbox" class="form-check-input mt-0" value="{{ $module['key'] }}" data-addon-check>
                                                </div>
                                                <p class="fs-12 text-muted mb-1">{{ $module['description'] }}</p>
                                                <span class="fs-12 fw-semibold" data-addon-price="{{ $module['key'] }}"></span>
                                                @if ($module['requires'])
                                                    <span class="d-block fs-11 text-muted">Needs {{ collect($module['requires'])->map(fn ($m) => config("navigation.apps.$m.label", ucfirst($m)))->implode(', ') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="fs-13 text-muted mt-3 mb-0 d-none" id="noAddons">Your plan already includes every module.</p>
                    </x-ui.card>
                </section>

                {{-- Step 3: billing details + pay --}}
                <section data-step="3" class="d-none">
                    <x-ui.card class="mb-4">
                        <h5 class="mb-1">Billing details</h5>
                        <p class="fs-13 text-muted mb-4">Your tax invoice is made out to these details. Add your GSTIN to claim input tax credit.</p>
                        <form id="billingDetailsForm" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="billing_name">Company name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="billing_name" name="billing_name" value="{{ $tenant->billing_name ?? $tenant->name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="billing_email">Billing email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="billing_email" name="billing_email" value="{{ $tenant->billing_email }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="billing_gstin">GSTIN <span class="text-muted fs-12">(optional)</span></label>
                                    <input type="text" class="form-control text-uppercase" id="billing_gstin" name="billing_gstin" maxlength="15" value="{{ $tenant->billing_gstin }}" placeholder="27ABCDE1234F1Z5">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="billing_state">State</label>
                                    <select class="form-select" id="billing_state" name="billing_state">
                                        <option value="">Select state</option>
                                        @foreach ($gstStates as $code => $state)
                                            <option value="{{ $state }}" data-gst-code="{{ $code }}" @selected($tenant->billing_state === $state)>{{ $state }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="billing_address">Billing address</label>
                                    <textarea class="form-control" id="billing_address" name="billing_address" rows="2">{{ $tenant->billing_address }}</textarea>
                                </div>
                            </div>
                            <div class="alert alert-danger fs-13 mt-3 mb-0 d-none" id="billingDetailsErrors"></div>
                        </form>
                    </x-ui.card>
                </section>

                {{-- Step 4: confirmation (reached after payment) --}}
                <section data-step="4" class="d-none">
                    <x-ui.card class="mb-4 text-center py-5">
                        <span class="erp-app-icon erp-app-icon-lg mx-auto mb-3" style="background: #16A34A"><i class="feather-check"></i></span>
                        <h5 class="mb-1">@if ($subscriptionChanged) Subscription updated @else You're subscribed @endif</h5>
                        <p class="fs-13 text-muted mb-4" id="confirmationText">
                            @if ($subscriptionChanged)
                                {{ $subscriptionChanged }}
                            @elseif ($subscribed)
                                {{ $subscribed['plan'] }} for {{ $subscribed['seats'] }} users, billed {{ $subscribed['cycle'] }}
                                — ₹{{ number_format($subscribed['total'] / 100, 2) }} incl. GST.
                                @if ($subscribed['renews'])
                                    Renews automatically on {{ $subscribed['renews'] }}.
                                @endif
                            @endif
                        </p>
                        <a href="{{ route('platform.subscription.index') }}" class="btn btn-primary">Go to Subscription</a>
                    </x-ui.card>
                </section>

                <form action="{{ route('platform.billing.verify') }}" method="POST" id="subscriptionVerifyForm" class="d-none">
                    @csrf
                    <input type="hidden" name="gateway_subscription_id" id="sv_subscription_id">
                    <input type="hidden" name="gateway_payment_id" id="sv_payment_id">
                    <input type="hidden" name="gateway_signature" id="sv_signature">
                </form>

                <form action="{{ route('platform.billing.change.verify') }}" method="POST" id="changeVerifyForm" class="d-none">
                    @csrf
                    <input type="hidden" name="gateway_order_id" id="cv_order_id">
                    <input type="hidden" name="gateway_payment_id" id="cv_payment_id">
                    <input type="hidden" name="gateway_signature" id="cv_signature">
                </form>

                <div class="d-flex justify-content-between {{ $plans->isEmpty() ? 'd-none' : '' }}" id="stepNav">
                    <button type="button" class="btn btn-light border" id="stepBack">Back</button>
                    <button type="button" class="btn btn-primary" id="stepNext">Continue</button>
                </div>
            </div>

            {{-- Live order summary, from the server's quote --}}
            <div class="col-xl-4">
                <div class="erp-order-summary">
                    <x-ui.card>
                        <span class="text-muted fs-12 text-uppercase">Order summary</span>
                        <div class="mt-3" id="summaryBody">
                            <div class="text-muted fs-13">Pick a plan to see the price.</div>
                        </div>
                        <div class="alert alert-warning fs-13 mt-3 mb-0 d-none" id="quoteError"></div>
                    </x-ui.card>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
.erp-checkout-steps { display: flex; list-style: none; padding: 0; margin: 0; gap: 0; }
.erp-checkout-steps li { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; position: relative; color: var(--bs-secondary-color, #6c757d); font-size: 13px; }
.erp-checkout-steps li::before { content: ''; position: absolute; top: 14px; left: -50%; width: 100%; height: 2px; background: rgba(0,0,0,.08); z-index: 0; }
.erp-checkout-steps li:first-child::before { display: none; }
.erp-checkout-step-num { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: var(--bs-body-bg, #fff); border: 2px solid rgba(0,0,0,.12); font-weight: 700; z-index: 1; }
.erp-checkout-steps li.active, .erp-checkout-steps li.done { color: var(--bs-body-color, #212529); font-weight: 600; }
.erp-checkout-steps li.active .erp-checkout-step-num { border-color: var(--bs-primary, #3B82F6); color: var(--bs-primary, #3B82F6); }
.erp-checkout-steps li.done .erp-checkout-step-num { background: var(--bs-primary, #3B82F6); border-color: var(--bs-primary, #3B82F6); color: #fff; }
.erp-checkout-steps li.done::before, .erp-checkout-steps li.active::before { background: var(--bs-primary, #3B82F6); }
.erp-plan-card, .erp-addon-card { cursor: pointer; border: 1px solid rgba(0,0,0,.08); transition: border-color .15s ease, box-shadow .15s ease; }
.erp-plan-card:hover, .erp-addon-card:hover { border-color: rgba(var(--bs-primary-rgb, 59,130,246), .4); }
.erp-plan-card.selected, .erp-addon-card:has([data-addon-check]:checked) { border-color: var(--bs-primary, #3B82F6); box-shadow: 0 0 0 1px var(--bs-primary, #3B82F6) inset; }
.erp-plan-card.unavailable { opacity: .5; cursor: not-allowed; }
.erp-addon-card.disabled { opacity: .55; cursor: not-allowed; }
.erp-addon-icon { width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0; }
.erp-app-icon-lg { border-radius: 16px; height: 56px; width: 56px; display: grid; place-items: center; color: #fff; }
.erp-app-icon-lg i { font-size: 24px; }
.erp-seat-picker { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px; border-radius: 12px; background: rgba(var(--bs-primary-rgb, 59,130,246), .04); }
.erp-order-summary { position: sticky; top: 90px; }
.erp-summary-line { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; margin-bottom: 8px; }
.erp-summary-total { display: flex; justify-content: space-between; font-weight: 700; font-size: 16px; border-top: 1px solid rgba(0,0,0,.08); padding-top: 12px; margin-top: 12px; }
@media (max-width: 575.98px) { .erp-checkout-step-label { font-size: 11px; } }
</style>
@endpush

@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    var PLANS = @json($plans);
    var MODULES = @json($modules);
    var MIN_SEATS = {{ (int) $minimumSeats }};
    var CURRENCY = @json($currency);
    var ROUTES = {
        quote: @json(route('platform.billing.quote')),
        details: @json(route('platform.billing.details')),
        subscribe: @json(route('platform.billing.subscribe')),
        checkout: @json(route('platform.billing.checkout')),
    };
    var CSRF = @json(csrf_token());

    var state = @json($selection);
    var step = {{ (int) $startStep }};
    var lastQuote = null;
    var quoteTimer = null;
    var quoteSeq = 0;

    var root = document.getElementById('billingCheckout');
    var symbol = CURRENCY === 'INR' ? '₹' : CURRENCY + ' ';

    function money(paise) {
        return symbol + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function rupees(n) { return symbol + Number(n).toLocaleString('en-IN'); }
    function plan() { return PLANS.find(function (p) { return p.id === state.plan_id; }) || null; }
    function planIncludes(p, key) { return p.features === null || p.features.indexOf(key) !== -1; }
    function post(url, body, method) {
        return fetch(url, {
            method: method || 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        });
    }
    function firstError(data) {
        if (data.errors) { var k = Object.keys(data.errors)[0]; return data.errors[k][0]; }
        return data.message || 'Something went wrong.';
    }

    // ---------- Step 1: plan, cycle, users ----------
    function renderPlans() {
        root.querySelectorAll('input[name="cycle"]').forEach(function (r) { r.checked = r.value === state.cycle; });
        root.querySelectorAll('[data-cycle-note]').forEach(function (el) {
            el.textContent = state.cycle === 'yearly' ? 'billed yearly' : 'month-to-month';
        });

        PLANS.forEach(function (p) {
            var card = root.querySelector('[data-plan-card="' + p.id + '"]');
            var price = p.prices[state.cycle];
            var other = state.cycle === 'yearly' ? p.prices.monthly : p.prices.yearly;
            card.classList.toggle('selected', p.id === state.plan_id);
            card.classList.toggle('unavailable', price === null);
            root.querySelector('[data-plan-price="' + p.id + '"]').textContent = price === null ? '—' : Number(price).toLocaleString('en-IN');
            root.querySelector('[data-plan-unavailable="' + p.id + '"]').classList.toggle('d-none', price !== null);
            var alt = root.querySelector('[data-plan-alt="' + p.id + '"]');
            if (other === null || price === null) {
                alt.textContent = '';
            } else if (state.cycle === 'yearly' && other > price) {
                alt.textContent = rupees(other) + ' /user month-to-month — save ' + Math.round((1 - price / other) * 100) + '%';
            } else {
                alt.textContent = rupees(other) + ' /user/month billed yearly';
            }
        });
    }

    root.querySelectorAll('input[name="cycle"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            state.cycle = radio.value;
            var p = plan();
            if (p && p.prices[state.cycle] === null) {
                var fallback = PLANS.find(function (x) { return x.prices[state.cycle] !== null; });
                state.plan_id = fallback ? fallback.id : state.plan_id;
            }
            refresh();
        });
    });
    root.querySelectorAll('[data-plan-card]').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            var p = PLANS.find(function (x) { return String(x.id) === card.dataset.planCard; });
            if (!p || p.prices[state.cycle] === null) return;
            state.plan_id = p.id;
            refresh();
        });
    });

    var seatsInput = document.getElementById('seats');
    function setSeats(n) {
        state.seats = Math.max(MIN_SEATS, parseInt(n, 10) || MIN_SEATS);
        if (seatsInput) seatsInput.value = state.seats;
        requestQuote();
    }
    if (seatsInput) {
        seatsInput.addEventListener('change', function () { setSeats(seatsInput.value); });
        seatsInput.addEventListener('input', function () {
            var n = parseInt(seatsInput.value, 10);
            if (n >= MIN_SEATS) { state.seats = n; requestQuote(); }
        });
    }
    root.querySelectorAll('[data-seat-step]').forEach(function (btn) {
        btn.addEventListener('click', function () { setSeats(state.seats + parseInt(btn.dataset.seatStep, 10)); });
    });

    // ---------- Step 2: add-ons ----------
    function renderAddons() {
        var p = plan();
        var visible = 0;
        MODULES.forEach(function (m) {
            var wrap = root.querySelector('[data-addon="' + m.key + '"]');
            var check = wrap.querySelector('[data-addon-check]');
            var card = wrap.querySelector('.erp-addon-card');
            var price = m.prices[state.cycle];
            var hidden = !p || planIncludes(p, m.key) || m.lifetime;

            wrap.classList.toggle('d-none', hidden);
            if (hidden) {
                state.modules = state.modules.filter(function (k) { return k !== m.key; });
                return;
            }
            visible++;

            check.disabled = price === null;
            card.classList.toggle('disabled', price === null);
            if (price === null) state.modules = state.modules.filter(function (k) { return k !== m.key; });
            check.checked = state.modules.indexOf(m.key) !== -1;
            root.querySelector('[data-addon-price="' + m.key + '"]').textContent =
                price === null ? 'Not available on this billing cycle' : rupees(price) + ' /user/month';
        });
        document.getElementById('noAddons').classList.toggle('d-none', visible > 0);
    }

    root.querySelectorAll('[data-addon-check]').forEach(function (check) {
        check.addEventListener('change', function () {
            var key = check.value;
            if (check.checked) {
                if (state.modules.indexOf(key) === -1) state.modules.push(key);
                // Pull in what it needs, if that isn't already covered.
                var m = MODULES.find(function (x) { return x.key === key; });
                (m.requires || []).forEach(function (req) {
                    var reqModule = MODULES.find(function (x) { return x.key === req; });
                    var covered = planIncludes(plan(), req) || (reqModule && reqModule.lifetime);
                    if (!covered && state.modules.indexOf(req) === -1 && reqModule && reqModule.prices[state.cycle] !== null) {
                        state.modules.push(req);
                    }
                });
            } else {
                // Drop anything that needs it.
                state.modules = state.modules.filter(function (k) {
                    var m = MODULES.find(function (x) { return x.key === k; });
                    return k !== key && (m.requires || []).indexOf(key) === -1;
                });
            }
            refresh();
        });
    });

    // ---------- Summary (server quote) ----------
    function renderSummary(quote) {
        var body = document.getElementById('summaryBody');
        if (!quote) { body.innerHTML = '<div class="text-muted fs-13">Pick a plan to see the price.</div>'; return; }

        var html = '';
        quote.lines.forEach(function (line) {
            html += '<div class="erp-summary-line"><span><span class="text-dark fw-semibold"></span><span class="d-block fs-12 text-muted"></span></span><span class="text-dark"></span></div>';
        });
        html += '<div class="erp-summary-line border-top pt-2 mt-2"><span>Subtotal</span><span data-subtotal></span></div>';
        html += '<div class="erp-summary-line"><span data-gst-label></span><span data-gst></span></div>';
        html += '<div class="erp-summary-total"><span>Total</span><span data-total></span></div>';
        html += '<div class="fs-12 text-muted mt-1" data-renews></div>';
        body.innerHTML = html;

        // Fill as text, never HTML — labels come from config, but stay safe.
        var rows = body.querySelectorAll('.erp-summary-line');
        quote.lines.forEach(function (line, i) {
            var spans = rows[i].querySelectorAll('span');
            spans[1].textContent = line.label;
            spans[2].textContent = rupees(line.price_per_user) + ' × ' + line.seats + ' users × ' + line.months + (line.months === 1 ? ' month' : ' months');
            spans[3].textContent = money(line.amount);
        });
        body.querySelector('[data-subtotal]').textContent = money(quote.subtotal);
        body.querySelector('[data-gst-label]').textContent = 'GST (' + quote.gst_rate + '%)';
        body.querySelector('[data-gst]').textContent = money(quote.gst);
        body.querySelector('[data-total]').textContent = money(quote.total);
        body.querySelector('[data-renews]').textContent = 'Billed ' + (quote.cycle === 'yearly' ? 'every year' : 'every month') + ' · renews automatically';

        // Changing a live subscription: the total above is the new renewal amount.
        var change = quote.change;
        if (change && !change.error) {
            body.querySelector('.erp-summary-total span').textContent = 'New renewal total';
            var box = document.createElement('div');
            box.className = 'erp-summary-total';
            var label = document.createElement('span');
            var amount = document.createElement('span');
            var note = document.createElement('div');
            note.className = 'fs-12 text-muted mt-1';
            if (change.effective === 'now') {
                label.textContent = 'Due now';
                amount.textContent = money(change.due_now);
                note.textContent = 'Prorated for the rest of this period (to ' + change.renews_on + '), incl. GST. Applies right away.';
            } else {
                label.textContent = 'Due now';
                amount.textContent = money(0);
                note.textContent = 'Nothing to pay now — this applies from your renewal on ' + change.renews_on + '.';
            }
            box.appendChild(label);
            box.appendChild(amount);
            body.appendChild(box);
            body.appendChild(note);
        }
    }

    // Why the current pick can't be bought as a change (same as now, other cycle…), or null.
    function changeError() {
        return lastQuote && lastQuote.change && lastQuote.change.error ? lastQuote.change.error : null;
    }

    function requestQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(function () {
            if (!plan()) { lastQuote = null; renderSummary(null); return; }
            var seq = ++quoteSeq;
            post(ROUTES.quote, { plan_id: state.plan_id, cycle: state.cycle, seats: state.seats, modules: state.modules })
                .then(function (res) {
                    if (seq !== quoteSeq) return; // a newer selection is in flight
                    var err = document.getElementById('quoteError');
                    if (res.ok) {
                        lastQuote = res.data;
                        err.textContent = changeError() || '';
                        err.classList.toggle('d-none', !changeError());
                        renderSummary(res.data);
                    } else {
                        lastQuote = null;
                        err.textContent = firstError(res.data);
                        err.classList.remove('d-none');
                    }
                    renderNav();
                });
        }, 200);
    }

    // ---------- Steps ----------
    function renderNav() {
        root.querySelectorAll('[data-step]').forEach(function (s) { s.classList.toggle('d-none', Number(s.dataset.step) !== step); });
        root.querySelectorAll('[data-step-dot]').forEach(function (d) {
            var n = Number(d.dataset.stepDot);
            d.classList.toggle('active', n === step);
            d.classList.toggle('done', n < step);
        });
        var back = document.getElementById('stepBack');
        var next = document.getElementById('stepNext');
        document.getElementById('stepNav').classList.toggle('d-none', step === 4 || PLANS.length === 0);
        back.classList.toggle('invisible', step === 1);
        next.disabled = !lastQuote || (step === 3 && !!changeError());
        next.textContent = step === 3 ? payLabel() : 'Continue';
    }

    function payLabel() {
        if (!lastQuote) return 'Pay';
        var change = lastQuote.change;
        if (!change) return 'Pay ' + money(lastQuote.total);
        if (change.error) return 'Pay';
        if (change.effective === 'renewal') return 'Book change for ' + change.renews_on;
        return change.due_now >= 100 ? 'Pay ' + money(change.due_now) + ' now' : 'Confirm change';
    }

    function refresh() {
        renderPlans();
        renderAddons();
        renderNav();
        requestQuote();
    }

    document.getElementById('stepBack').addEventListener('click', function () {
        step = Math.max(1, step - 1);
        renderNav();
    });

    document.getElementById('stepNext').addEventListener('click', function () {
        if (!lastQuote) return;
        if (step < 3) { step++; renderNav(); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
        saveDetailsThenPay();
    });

    // ---------- Step 3: billing details, then pay ----------
    var gstin = document.getElementById('billing_gstin');
    gstin.addEventListener('input', function () {
        gstin.value = gstin.value.toUpperCase();
        var code = gstin.value.slice(0, 2);
        var opt = document.querySelector('#billing_state option[data-gst-code="' + code + '"]');
        if (opt) document.getElementById('billing_state').value = opt.value;
    });

    function saveDetailsThenPay() {
        var form = document.getElementById('billingDetailsForm');
        var errors = document.getElementById('billingDetailsErrors');
        var next = document.getElementById('stepNext');
        var payload = {};
        new FormData(form).forEach(function (v, k) { payload[k] = v; });

        next.disabled = true;
        post(ROUTES.details, payload, 'PUT').then(function (res) {
            if (!res.ok) {
                errors.classList.remove('alert-info');
                errors.classList.add('alert-danger');
                errors.textContent = firstError(res.data);
                errors.classList.remove('d-none');
                next.disabled = false;
                return;
            }
            errors.classList.add('d-none');
            startPayment();
        });
    }

    function showPayError(message) {
        var errors = document.getElementById('billingDetailsErrors');
        errors.classList.remove('alert-info');
        errors.classList.add('alert-danger');
        errors.textContent = message;
        errors.classList.remove('d-none');
        document.getElementById('stepNext').disabled = false;
        renderNav();
    }

    // Starts the recurring subscription on the server (amount re-priced there),
    // then opens the gateway's checkout for the first payment. Verified server-side.
    function startPayment() {
        var next = document.getElementById('stepNext');
        next.disabled = true;
        next.textContent = 'Starting payment…';

        post(ROUTES.subscribe, { plan_id: state.plan_id, cycle: state.cycle, seats: state.seats, modules: state.modules })
            .then(function (res) {
                if (!res.ok) { showPayError(firstError(res.data)); return; }
                var order = res.data;
                // Change booked for renewal, or applied with nothing to pay: show the confirmation.
                if (order.scheduled || order.applied) { window.location.href = ROUTES.checkout; return; }
                if (order.order_id) { payForChange(order); return; }
                if (order.gateway !== 'razorpay' || typeof Razorpay === 'undefined') {
                    showPayError('The payment window could not be opened. Please refresh and try again.');
                    return;
                }
                var rzp = new Razorpay({
                    key: order.key,
                    subscription_id: order.subscription_id,
                    name: 'SaaS ERP Platform',
                    description: lastQuote ? (lastQuote.lines[0].label + ' · ' + lastQuote.seats + ' users · ' + (lastQuote.cycle === 'yearly' ? 'yearly' : 'monthly')) : 'Subscription',
                    prefill: { name: order.tenant_name || '', email: order.tenant_email || '' },
                    handler: function (response) {
                        document.getElementById('sv_subscription_id').value = response.razorpay_subscription_id;
                        document.getElementById('sv_payment_id').value = response.razorpay_payment_id;
                        document.getElementById('sv_signature').value = response.razorpay_signature;
                        next.textContent = 'Confirming payment…';
                        document.getElementById('subscriptionVerifyForm').submit();
                    },
                    modal: { ondismiss: function () { next.disabled = false; renderNav(); } },
                });
                rzp.on('payment.failed', function (resp) {
                    showPayError((resp && resp.error && resp.error.description) || 'The payment failed. Please try again.');
                });
                rzp.open();
            })
            .catch(function () { showPayError('Something went wrong starting the payment.'); });
    }

    // An upgrade of a live subscription: a one-time payment of the prorated difference.
    function payForChange(order) {
        var next = document.getElementById('stepNext');
        if (order.gateway !== 'razorpay' || typeof Razorpay === 'undefined') {
            showPayError('The payment window could not be opened. Please refresh and try again.');
            return;
        }
        var rzp = new Razorpay({
            key: order.key,
            order_id: order.order_id,
            amount: order.amount,
            currency: order.currency,
            name: 'SaaS ERP Platform',
            description: 'Subscription upgrade (prorated)',
            prefill: { name: order.tenant_name || '', email: order.tenant_email || '' },
            handler: function (response) {
                document.getElementById('cv_order_id').value = response.razorpay_order_id;
                document.getElementById('cv_payment_id').value = response.razorpay_payment_id;
                document.getElementById('cv_signature').value = response.razorpay_signature;
                next.textContent = 'Confirming payment…';
                document.getElementById('changeVerifyForm').submit();
            },
            modal: { ondismiss: function () { next.disabled = false; renderNav(); } },
        });
        rzp.on('payment.failed', function (resp) {
            showPayError((resp && resp.error && resp.error.description) || 'The payment failed. Please try again.');
        });
        rzp.open();
    }

    refresh();
})();
</script>
@endpush
