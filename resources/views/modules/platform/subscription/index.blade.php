@extends('layouts.duralux')

@section('title', 'Subscription | SaaS ERP')
@section('page-title', 'Subscription')
@section('breadcrumb', 'Tenant Console / Subscription')

@section('content')

    <x-ui.card class="mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="text-muted fs-12 text-uppercase">Current Plan</span>
                <h3 class="mb-0 mt-1">{{ $currentPlan?->name ?? ucfirst($tenant->plan ?? 'Starter') }}</h3>
                @if ($currentPlan)
                    <span class="fs-13 text-muted">
                        {{ $currentPlan->price > 0 ? $currentPlan->currency.' '.number_format($currentPlan->price).' / '.$currentPlan->billing_cycle : 'Free' }}
                    </span>
                @endif
            </div>
            <div class="text-end fs-13 text-muted">
                @can('updateSubscription', $tenant)
                    <a href="{{ route('platform.billing.checkout') }}" class="btn btn-primary btn-sm mb-2">Choose plan, users &amp; add-ons</a>
                @endcan
                <div>Status: <x-ui.badge variant="{{ $tenant->subscription_status === 'active' ? 'success' : 'warning' }}" soft>{{ ucfirst($tenant->subscription_status ?? 'trial') }}</x-ui.badge></div>
                @if ($tenant->plan_started_at)
                    <div class="mt-1">Since {{ $tenant->plan_started_at->format('d M Y') }}</div>
                @endif
                @if ($tenant->trial_ends_at)
                    <div class="mt-1">Trial ends {{ $tenant->trial_ends_at->format('d M Y') }}</div>
                @endif
            </div>
        </div>
    </x-ui.card>

    @php
        $installableModules = collect($modules)->where('state', 'available');
        $canManageModules = auth()->user()?->can('updateSubscription', $tenant) ?? false;
    @endphp

    <x-ui.card class="mb-4">
        <div class="mb-3">
            <span class="text-muted fs-12 text-uppercase">Modules</span>
            <p class="fs-13 text-muted mb-0">Every module included in your plan, plus any installed as a paid add-on. Uninstalling an add-on only hides it — its data is kept and reinstalling is free.</p>
        </div>

        <div class="row g-3 erp-apps-grid">
            @foreach ($modules as $module)
                @php $tag = $module['state'] === 'available' ? 'label' : 'div'; @endphp
                <div class="col-sm-6 col-lg-3">
                    <{{ $tag }} class="card h-100 erp-app-tile {{ $module['installed'] ? 'installed' : ($module['state'] === 'available' ? 'selectable' : 'uninstalled') }}">
                        <div class="card-body d-flex flex-column align-items-center text-center gap-3 py-4 position-relative">
                            @if ($module['state'] === 'available')
                                <input type="checkbox" name="modules[]" value="{{ $module['key'] }}" class="form-check-input erp-app-tile-check">
                            @endif

                            <span class="erp-app-icon erp-app-icon-lg" style="background: {{ $module['color'] }}">
                                <i class="{{ $module['icon'] }}"></i>
                            </span>
                            <div>
                                <h6 class="fw-bolder text-dark mb-1">{{ $module['label'] }}</h6>
                                <p class="fs-12 text-muted mb-0">{{ $module['description'] }}</p>
                                @if ($module['requires'] && ! $module['installed'])
                                    <p class="fs-11 text-muted mb-0 mt-1">Needs {{ $module['requires'] }}</p>
                                @endif
                            </div>

                            @switch($module['state'])
                                @case('plan')
                                    <span class="badge bg-soft-success text-success">Included in plan</span>
                                    @break
                                @case('addon')
                                    <span class="badge bg-soft-success text-success">Installed add-on</span>
                                    @if ($canManageModules)
                                        <form action="{{ route('platform.modules.uninstall', $module['key']) }}" method="POST" id="uninstallModule-{{ $module['key'] }}">
                                            @csrf
                                            <button type="button" class="btn btn-sm btn-light border"
                                                onclick="confirmAction({title: 'Uninstall {{ $module['label'] }}', message: '{{ $module['label'] }} will be hidden for everyone in your workspace. Its data is kept, and you can reinstall it any time for free.', variant: 'danger', confirmText: 'Uninstall'}, function() { document.getElementById('uninstallModule-{{ $module['key'] }}').submit(); })">
                                                Uninstall
                                            </button>
                                        </form>
                                    @endif
                                    @break
                                @case('uninstalled')
                                    <span class="badge bg-soft-secondary text-secondary">Uninstalled &middot; data kept</span>
                                    @if ($canManageModules)
                                        <form action="{{ route('platform.modules.reinstall', $module['key']) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">Reinstall &mdash; free</button>
                                        </form>
                                    @endif
                                    @break
                                @default
                                    <span class="fs-13 fw-bolder text-dark">{{ $moduleCurrency }} {{ number_format($modulePrice) }}</span>
                            @endswitch
                        </div>
                    </{{ $tag }}>
                </div>
            @endforeach
        </div>

        @if ($installableModules->isNotEmpty())
            <div class="erp-module-select-bar mt-3">
                <span class="fs-13 text-muted">
                    <span id="module-select-count">0</span> module(s) selected
                    &middot; Total: <strong id="module-select-total" class="text-dark">{{ $moduleCurrency }} 0</strong>
                </span>
                <button type="button" class="btn btn-primary" id="module-select-submit" disabled>Pay &amp; Install Selected Modules</button>
            </div>
        @endif
    </x-ui.card>

    <div class="row g-4">
        @forelse ($plans as $plan)
            @php $isCurrent = $currentPlan && $currentPlan->id === $plan->id; @endphp
            <div class="col-xxl-3 col-md-6">
                <x-ui.card stretch class="{{ $isCurrent ? 'border border-primary' : '' }}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="mb-0">{{ $plan->name }}</h5>
                        @if ($isCurrent)
                            <x-ui.badge variant="primary" soft>Current</x-ui.badge>
                        @endif
                    </div>
                    <div class="mb-3">
                        <span class="fs-24 fw-bold text-dark">
                            {{ $plan->price > 0 ? $plan->currency.' '.number_format($plan->price) : 'Free' }}
                        </span>
                        @if ($plan->price > 0)
                            <span class="fs-12 text-muted">/ {{ $plan->billing_cycle }}</span>
                        @endif
                    </div>
                    @if ($plan->description)
                        <p class="fs-13 text-muted mb-3">{{ $plan->description }}</p>
                    @endif
                    <ul class="list-unstyled fs-13 mb-4">
                        <li class="mb-2"><i class="feather-check text-success me-2"></i>{{ $plan->max_users ? $plan->max_users.' users' : 'Unlimited users' }}</li>
                        <li class="mb-2"><i class="feather-check text-success me-2"></i>{{ $plan->max_storage_mb ? number_format($plan->max_storage_mb / 1024, 1).' GB storage' : 'Unlimited storage' }}</li>
                        @if ($plan->features)
                            <li class="mb-2"><i class="feather-check text-success me-2"></i>{{ count($plan->features) }} modules included</li>
                        @else
                            <li class="mb-2"><i class="feather-check text-success me-2"></i>All modules included</li>
                        @endif
                    </ul>

                    @if ($isCurrent)
                        <x-ui.button variant="light" size="sm" class="w-100 border" disabled>Current Plan</x-ui.button>
                    @elseif ($plan->price > 0)
                        <x-ui.button type="button" variant="primary" size="sm" class="w-100 js-razorpay-checkout"
                            data-plan-id="{{ $plan->id }}" data-plan-name="{{ $plan->name }}">
                            Switch to {{ $plan->name }}
                        </x-ui.button>
                    @else
                        <form action="{{ route('platform.subscription.update') }}" method="POST" id="switchPlanForm{{ $plan->id }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <x-ui.button type="button" variant="primary" size="sm" class="w-100"
                                onclick="confirmAction({title: 'Switch Plan', message: 'Switch your subscription to the {{ $plan->name }} plan?', variant: 'primary', confirmText: 'Switch Plan'}, function() { document.getElementById('switchPlanForm{{ $plan->id }}').submit(); })">
                                Switch to {{ $plan->name }}
                            </x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            </div>
        @empty
            <div class="col-12">
                <x-ui.card>
                    <div class="text-center py-5 text-muted">No plans are available right now.</div>
                </x-ui.card>
            </div>
        @endforelse
    </div>

    <x-ui.card class="mt-4">
        <div class="mb-3">
            <span class="text-muted fs-12 text-uppercase">Payment History</span>
        </div>

        @if ($payments->isEmpty())
            <div class="text-center py-4 text-muted">No payments yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="fs-13">{{ $payment->created_at->format('d M Y, h:i A') }}</td>
                                <td class="fs-13">
                                    @if ($payment->purpose === \App\Domains\Platform\Models\SubscriptionPayment::PURPOSE_MODULE_ADDON)
                                        Module add-on:
                                        {{ collect($payment->modules)->map(fn ($m) => config("navigation.apps.$m.label", ucfirst($m)))->implode(', ') }}
                                    @else
                                        Plan switch: {{ $payment->plan?->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="fs-13">{{ $payment->currency }} {{ number_format($payment->amount / 100, 2) }}</td>
                                <td>
                                    <x-ui.badge variant="{{ $payment->status === 'paid' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'warning') }}" soft>
                                        {{ ucfirst($payment->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="fs-12 text-muted">{{ $payment->gateway_payment_id ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <form action="{{ route('platform.subscription.verify') }}" method="POST" id="gatewayVerifyForm" class="d-none">
        @csrf
        <input type="hidden" name="gateway_order_id" id="gw_order_id">
        <input type="hidden" name="gateway_payment_id" id="gw_payment_id">
        <input type="hidden" name="gateway_signature" id="gw_signature">
    </form>

    <form action="{{ route('platform.modules.verify') }}" method="POST" id="moduleGatewayVerifyForm" class="d-none">
        @csrf
        <input type="hidden" name="gateway_order_id" id="mgw_order_id">
        <input type="hidden" name="gateway_payment_id" id="mgw_payment_id">
        <input type="hidden" name="gateway_signature" id="mgw_signature">
    </form>

@endsection

<style>
.erp-app-tile { transition: all .2s ease; border: 1px solid rgba(0,0,0,.08); margin-bottom: 0; cursor: default; }
.erp-app-tile.selectable { cursor: pointer; }
.erp-app-tile.selectable:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.1); border-color: rgba(var(--bs-primary-rgb, 59,130,246), .3); }
.erp-app-tile.installed { border-color: rgba(25, 135, 84, .3); background: rgba(25, 135, 84, .03); }
.erp-app-tile.uninstalled { border-style: dashed; }
.erp-app-tile.uninstalled .erp-app-icon-lg { filter: grayscale(1); opacity: .6; }
.erp-app-tile:has(.erp-app-tile-check:checked) { border-color: var(--bs-primary, #3B82F6); box-shadow: 0 0 0 1px var(--bs-primary, #3B82F6) inset; background: rgba(var(--bs-primary-rgb, 59,130,246), .04); }
.erp-app-icon-lg { border-radius: 16px; height: 56px; width: 56px; box-shadow: 0 6px 14px -6px rgba(0,0,0,.35); }
.erp-app-icon-lg i { font-size: 24px; }
.erp-app-tile-check { position: absolute; top: 12px; right: 12px; width: 18px; height: 18px; }
.erp-module-select-bar {
    position: sticky; bottom: 0; padding: 14px 20px; background: #fff;
    border: 1px solid rgba(0,0,0,.08); border-radius: 12px; box-shadow: 0 -4px 16px rgba(0,0,0,.06);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
</style>

{{--
    Gateway-agnostic on the backend (SubscriptionController/PaymentGatewayManager
    never hardcode a provider), but a checkout WIDGET is inherently
    provider-specific JS — this still branches on order.gateway. Only
    'razorpay' exists today; adding a gateway means one more branch here plus
    its SDK's <script> tag, not touching the fetch/verify plumbing around it.
--}}
@push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function submitGatewayVerify(orderId, paymentId, signature) {
            document.getElementById('gw_order_id').value = orderId;
            document.getElementById('gw_payment_id').value = paymentId;
            document.getElementById('gw_signature').value = signature;
            document.getElementById('gatewayVerifyForm').submit();
        }

        function openRazorpayCheckout(order, planName, onDismiss, onFailed) {
            var rzp = new Razorpay({
                key: order.key,
                amount: order.amount,
                currency: order.currency,
                order_id: order.order_id,
                name: 'SaaS ERP Platform',
                description: 'Switch to ' + planName + ' plan',
                prefill: {
                    name: order.tenant_name || '',
                    email: order.tenant_email || '',
                },
                handler: function (response) {
                    submitGatewayVerify(response.razorpay_order_id, response.razorpay_payment_id, response.razorpay_signature);
                },
                modal: { ondismiss: onDismiss },
            });
            rzp.on('payment.failed', onFailed);
            rzp.open();
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-razorpay-checkout').forEach(function (button) {
                button.addEventListener('click', function () {
                    var planId = button.dataset.planId;
                    var planName = button.dataset.planName;
                    var originalText = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = 'Starting checkout&hellip;';
                    var reset = function () {
                        button.disabled = false;
                        button.innerHTML = originalText;
                    };

                    fetch(@json(route('platform.subscription.checkout')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ plan_id: planId }),
                    })
                        .then(function (res) {
                            if (!res.ok) {
                                return res.json().then(function (body) {
                                    throw new Error(body.message || 'Could not start checkout for this plan.');
                                });
                            }
                            return res.json();
                        })
                        .then(function (order) {
                            if (order.gateway === 'razorpay') {
                                openRazorpayCheckout(order, planName, reset, reset);
                            } else {
                                throw new Error('Unsupported payment gateway: ' + order.gateway);
                            }
                        })
                        .catch(function (err) {
                            alert(err.message || 'Something went wrong starting checkout.');
                            reset();
                        });
                });
            });
        });
    </script>

    {{-- Module add-on checkout: same pattern as the plan checkout above, its own
        order endpoint/verify form/hidden inputs since it's a separate SubscriptionPayment
        purpose (see TenantModuleController, SubscriptionPaymentService::markPaid). --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var pricePerModule = {{ (int) $modulePrice }};
        var currency = @json($moduleCurrency);
        var checkboxes = document.querySelectorAll('.erp-app-tile-check');
        var submitBtn = document.getElementById('module-select-submit');
        var countEl = document.getElementById('module-select-count');
        var totalEl = document.getElementById('module-select-total');

        function selectedModules() {
            return Array.from(document.querySelectorAll('.erp-app-tile-check:checked')).map(function (cb) { return cb.value; });
        }

        function syncModuleSelection() {
            if (!submitBtn) return;
            var count = selectedModules().length;
            countEl.textContent = count;
            totalEl.textContent = currency + ' ' + (count * pricePerModule).toLocaleString();
            submitBtn.disabled = count === 0;
        }
        checkboxes.forEach(function (cb) { cb.addEventListener('change', syncModuleSelection); });
        syncModuleSelection();

        function submitModuleGatewayVerify(orderId, paymentId, signature) {
            document.getElementById('mgw_order_id').value = orderId;
            document.getElementById('mgw_payment_id').value = paymentId;
            document.getElementById('mgw_signature').value = signature;
            document.getElementById('moduleGatewayVerifyForm').submit();
        }

        function openModuleRazorpayCheckout(order, moduleCount, onDismiss, onFailed) {
            var rzp = new Razorpay({
                key: order.key,
                amount: order.amount,
                currency: order.currency,
                order_id: order.order_id,
                name: 'SaaS ERP Platform',
                description: 'Install ' + moduleCount + ' module(s)',
                prefill: {
                    name: order.tenant_name || '',
                    email: order.tenant_email || '',
                },
                handler: function (response) {
                    submitModuleGatewayVerify(response.razorpay_order_id, response.razorpay_payment_id, response.razorpay_signature);
                },
                modal: { ondismiss: onDismiss },
            });
            rzp.on('payment.failed', onFailed);
            rzp.open();
        }

        if (!submitBtn) return;
        submitBtn.addEventListener('click', function () {
            var modules = selectedModules();
            if (modules.length === 0) return;

            var originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Starting checkout&hellip;';
            var reset = function () {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            };

            fetch(@json(route('platform.modules.checkout')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ modules: modules }),
            })
                .then(function (res) {
                    if (!res.ok) {
                        return res.json().then(function (body) {
                            throw new Error(body.message || 'Could not start checkout for these modules.');
                        });
                    }
                    return res.json();
                })
                .then(function (order) {
                    if (order.gateway === 'razorpay') {
                        openModuleRazorpayCheckout(order, modules.length, reset, reset);
                    } else {
                        throw new Error('Unsupported payment gateway: ' + order.gateway);
                    }
                })
                .catch(function (err) {
                    alert(err.message || 'Something went wrong starting checkout.');
                    reset();
                });
        });
    });
    </script>
@endpush
