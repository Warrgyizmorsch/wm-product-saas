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

    <form action="{{ route('platform.subscription.verify') }}" method="POST" id="gatewayVerifyForm" class="d-none">
        @csrf
        <input type="hidden" name="gateway_order_id" id="gw_order_id">
        <input type="hidden" name="gateway_payment_id" id="gw_payment_id">
        <input type="hidden" name="gateway_signature" id="gw_signature">
    </form>

@endsection

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
@endpush
