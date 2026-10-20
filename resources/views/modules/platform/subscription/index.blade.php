@extends('layouts.duralux')

@section('title', 'Subscription | SaaS ERP')
@section('page-title', 'Subscription')
@section('breadcrumb', 'Tenant Console / Subscription')

@section('content')
    @php
        // "₹1,250 /user/month billed yearly" (or month-to-month when only monthly is sold).
        $perUser = fn (array $prices) => $prices['yearly'] !== null
            ? '₹'.number_format($prices['yearly']).' /user/month billed yearly'
            : '₹'.number_format($prices['monthly']).' /user/month';
        $checkoutAvailable = collect($planPrices)->contains(fn ($p) => $p['monthly'] !== null || $p['yearly'] !== null);
    @endphp

    @if ($tenant->isBillingLocked())
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="feather-lock"></i>
            <div>
                <strong>Your subscription has lapsed.</strong>
                Choose a plan and pay to reopen the workspace for everyone — all your data is still here.
            </div>
        </div>
    @endif

    <x-ui.card class="mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="text-muted fs-12 text-uppercase">Current Plan</span>
                <h3 class="mb-0 mt-1">{{ $currentPlan?->name ?? ucfirst($tenant->plan ?? 'Starter') }}</h3>
                @if ($currentPlan)
                    @php $current = $planPrices[$currentPlan->id] ?? ['monthly' => null, 'yearly' => null]; @endphp
                    <span class="fs-13 text-muted">
                        @if ($liveSubscription)
                            {{ $liveSubscription->seats }} users &middot; billed {{ $liveSubscription->cycle }}
                            &middot; ₹{{ number_format($liveSubscription->total / 100, 2) }} incl. GST
                            @if ($liveSubscription->isCancelling())
                                <span class="d-block text-warning">Cancelled — you won't be charged again. Everything stays available until {{ $liveSubscription->current_end?->format('d M Y') }}.</span>
                            @elseif ($liveSubscription->current_end)
                                &middot; renews {{ $liveSubscription->current_end->format('d M Y') }}
                            @endif
                            @if ($liveSubscription->grace_ends_at)
                                <span class="d-block text-danger">Renewal payment failed — please update your payment method before {{ $liveSubscription->grace_ends_at->format('d M Y') }}.</span>
                            @endif
                        @elseif ($current['yearly'] !== null || $current['monthly'] !== null)
                            {{ $perUser($current) }} &middot; no paid subscription yet
                        @else
                            {{ $currentPlan->price > 0 ? $currentPlan->currency.' '.number_format($currentPlan->price).' / '.$currentPlan->billing_cycle : 'Free' }}
                        @endif
                    </span>
                @endif
            </div>
            <div class="text-end fs-13 text-muted">
                @if ($checkoutAvailable)
                    @can('updateSubscription', $tenant)
                        <a href="{{ route('platform.billing.checkout') }}" class="btn btn-primary btn-sm mb-2">Choose plan, users &amp; add-ons</a>
                    @endcan
                @endif
                @if ($liveSubscription && $liveSubscription->status === \App\Domains\Platform\Models\TenantSubscription::STATUS_ACTIVE && ! $liveSubscription->isCancelling())
                    @can('updateSubscription', $tenant)
                        <form action="{{ route('platform.subscription.cancel') }}" method="POST" id="cancelSubscriptionForm" class="mb-2">
                            @csrf
                            <button type="button" class="btn btn-light border btn-sm"
                                onclick="confirmAction({title: 'Cancel subscription', message: 'Renewals stop and you won\'t be charged again. Everyone keeps full access until {{ $liveSubscription->current_end?->format('d M Y') }}; after that only billing is available until you subscribe again. Your data is never deleted. This can\'t be undone.', variant: 'danger', confirmText: 'Cancel subscription'}, function() { document.getElementById('cancelSubscriptionForm').submit(); })">
                                Cancel subscription
                            </button>
                        </form>
                    @endcan
                @endif
                <div>Status: <x-ui.badge variant="{{ $tenant->subscription_status === 'active' ? 'success' : 'warning' }}" soft>{{ \App\Models\Tenant::subscriptionStatuses()[$tenant->subscription_status] ?? ucfirst($tenant->subscription_status ?? 'trial') }}</x-ui.badge></div>
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
        $isRecurring = fn (array $module) => $module['prices']['monthly'] !== null || $module['prices']['yearly'] !== null;
        // Only modules without per-user pricing can still be bought with the old one-time fee.
        $installableModules = collect($modules)->where('state', 'available')->reject($isRecurring);
        $canManageModules = auth()->user()?->can('updateSubscription', $tenant) ?? false;
    @endphp

    <x-ui.card class="mb-4">
        <div class="mb-3">
            <span class="text-muted fs-12 text-uppercase">Modules</span>
            <p class="fs-13 text-muted mb-0">Every module included in your plan, plus any installed as a paid add-on. Uninstalling an add-on only hides it — its data is kept and reinstalling is free.</p>
        </div>

        <div class="row g-3 erp-apps-grid">
            @foreach ($modules as $module)
                @php
                    $legacyBuy = $module['state'] === 'available' && ! $isRecurring($module);
                    $tag = $legacyBuy ? 'label' : 'div';
                @endphp
                <div class="col-sm-6 col-lg-3">
                    <{{ $tag }} class="card h-100 erp-app-tile {{ $module['installed'] ? 'installed' : ($module['state'] === 'available' ? ($legacyBuy ? 'selectable' : '') : 'uninstalled') }}">
                        <div class="card-body d-flex flex-column align-items-center text-center gap-3 py-4 position-relative">
                            @if ($legacyBuy)
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
                                    @if ($isRecurring($module))
                                        <span class="fs-13 fw-bolder text-dark">{{ $perUser($module['prices']) }}</span>
                                        @if ($canManageModules && $checkoutAvailable)
                                            <a href="{{ route('platform.billing.checkout', ['modules' => [$module['key']]]) }}" class="btn btn-sm btn-primary">Add to subscription</a>
                                        @endif
                                    @else
                                        <span class="fs-13 fw-bolder text-dark">{{ $moduleCurrency }} {{ number_format($modulePrice) }} one-time</span>
                                    @endif
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

    @php
        $customPlan = config('billing.custom_plan');
        $showCustom = (bool) ($customPlan['enabled'] ?? false);
        $sellsMonthly = collect($planPrices)->contains(fn ($p) => $p['monthly'] !== null);
        $sellsYearly = collect($planPrices)->contains(fn ($p) => $p['yearly'] !== null);
        $showCycleToggle = $sellsMonthly && $sellsYearly;
        $defaultCycle = $sellsYearly ? 'yearly' : 'monthly';
        // Best yearly saving across plans, for the "Save up to N%" hint on the toggle.
        $bestSaving = collect($planPrices)
            ->filter(fn ($p) => $p['monthly'] && $p['yearly'] !== null && $p['yearly'] < $p['monthly'])
            ->map(fn ($p) => (int) round((1 - $p['yearly'] / $p['monthly']) * 100))
            ->max();
        $contactHref = $customPlan['contact_url']
            ?? (($customPlan['sales_email'] ?? null) ? 'mailto:'.$customPlan['sales_email'].'?subject='.rawurlencode('Custom plan enquiry — '.($tenant->name ?? 'workspace')) : null);
        $includes = fn ($plan, string $module) => $plan->features === null || in_array($module, $plan->features, true);
        $compareColumns = $plans->count() + 1 + ($showCustom ? 1 : 0);
        $planFeatures = app(\App\Domains\Platform\Services\PlanFeatureGate::class);
        $featureCatalog = $planFeatures->catalog();
    @endphp

    <x-ui.card class="mb-4 sub-pricing" id="subPricing" data-cycle="{{ $defaultCycle }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <span class="text-muted fs-12 text-uppercase">Plans &amp; pricing</span>
                <p class="fs-13 text-muted mb-0">Priced per user per month. GST is added at checkout.</p>
            </div>
            @if ($showCycleToggle)
                <div class="sub-cycle-toggle" role="group" aria-label="Billing period">
                    <button type="button" data-cycle-btn="monthly" aria-pressed="{{ $defaultCycle === 'monthly' ? 'true' : 'false' }}">Monthly</button>
                    <button type="button" data-cycle-btn="yearly" aria-pressed="{{ $defaultCycle === 'yearly' ? 'true' : 'false' }}">
                        Yearly
                        @if ($bestSaving)
                            <span class="sub-save">Save up to {{ $bestSaving }}%</span>
                        @endif
                    </button>
                </div>
            @endif
        </div>

        <div class="row g-3">
            @forelse ($plans as $plan)
                @php
                    $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
                    $prices = $planPrices[$plan->id];
                    $perUserPlan = $prices['monthly'] !== null || $prices['yearly'] !== null;
                    $saving = $prices['monthly'] && $prices['yearly'] !== null && $prices['yearly'] < $prices['monthly']
                        ? (int) round((1 - $prices['yearly'] / $prices['monthly']) * 100) : null;
                @endphp
                <div class="col-xxl col-md-6">
                    <div class="sub-plan {{ $isCurrent ? 'is-current' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="sub-plan-name">{{ $plan->name }}</span>
                            @if ($isCurrent)
                                <x-ui.badge variant="primary" soft>Current</x-ui.badge>
                            @endif
                        </div>

                        <div class="sub-plan-price">
                            @if ($perUserPlan)
                                @foreach (['monthly', 'yearly'] as $cycle)
                                    <div data-show-cycle="{{ $cycle }}">
                                        @if ($prices[$cycle] !== null)
                                            <div class="sub-amount">₹{{ number_format($prices[$cycle]) }}</div>
                                            <div class="fs-13 text-dark">per user/month <span class="text-muted">{{ $cycle === 'yearly' ? 'billed annually' : 'billed monthly' }}</span></div>
                                            @if ($cycle === 'yearly' && $saving)
                                                <span class="sub-save mt-2">Save {{ $saving }}% vs monthly</span>
                                            @endif
                                        @else
                                            <div class="sub-amount text-muted">—</div>
                                            <div class="fs-13 text-muted">Not sold {{ $cycle }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="sub-amount">{{ $plan->price > 0 ? $plan->currency.' '.number_format($plan->price) : 'Free' }}</div>
                                @if ($plan->price > 0)
                                    <div class="fs-13 text-muted">per {{ $plan->billing_cycle === 'yearly' ? 'year' : 'month' }}</div>
                                @endif
                            @endif
                        </div>

                        @if ($plan->description)
                            <p class="fs-13 text-muted mb-3">{{ $plan->description }}</p>
                        @endif

                        <div class="mt-auto">
                            @if ($perUserPlan)
                                @can('updateSubscription', $tenant)
                                    @foreach (['monthly', 'yearly'] as $cycle)
                                        <div data-show-cycle="{{ $cycle }}">
                                            @if ($prices[$cycle] !== null)
                                                <a href="{{ route('platform.billing.checkout', ['plan' => $plan->id, 'cycle' => $cycle]) }}" class="btn w-100 {{ $isCurrent ? 'btn-light border' : 'btn-primary' }}">
                                                    {{ $isCurrent ? ($liveSubscription ? 'Manage users & add-ons' : 'Subscribe') : 'Choose '.$plan->name }}
                                                </a>
                                            @else
                                                <button type="button" class="btn w-100 btn-light border" disabled>Not available {{ $cycle }}</button>
                                            @endif
                                        </div>
                                    @endforeach
                                @endcan
                            @elseif ($isCurrent)
                                <x-ui.button variant="light" class="w-100 border" disabled>Current Plan</x-ui.button>
                            @elseif ($plan->price > 0)
                                <x-ui.button type="button" variant="primary" class="w-100 js-razorpay-checkout"
                                    data-plan-id="{{ $plan->id }}" data-plan-name="{{ $plan->name }}">
                                    Switch to {{ $plan->name }}
                                </x-ui.button>
                            @else
                                <form action="{{ route('platform.subscription.update') }}" method="POST" id="switchPlanForm{{ $plan->id }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <x-ui.button type="button" variant="primary" class="w-100"
                                        onclick="confirmAction({title: 'Switch Plan', message: 'Switch your subscription to the {{ $plan->name }} plan?', variant: 'primary', confirmText: 'Switch Plan'}, function() { document.getElementById('switchPlanForm{{ $plan->id }}').submit(); })">
                                        Switch to {{ $plan->name }}
                                    </x-ui.button>
                                </form>
                            @endif
                            <div class="sub-min">
                                @if ($plan->min_users)
                                    Minimum users: {{ $plan->min_users }}
                                @else
                                    &nbsp;
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="text-center py-5 text-muted">No plans are available right now.</div>
                </div>
            @endforelse

            @if ($showCustom)
                <div class="col-xxl col-md-6">
                    <div class="sub-plan sub-plan-custom">
                        <span class="sub-plan-name mb-3">Custom</span>
                        <div class="sub-plan-price">
                            <div class="sub-amount">Let's talk</div>
                            <div class="fs-13 text-muted">pricing for your scale</div>
                        </div>
                        <p class="fs-13 text-muted mb-3">{{ $customPlan['description'] }}</p>
                        <ul class="list-unstyled fs-13 mb-3">
                            @foreach ($customPlan['highlights'] ?? [] as $point)
                                <li class="mb-2 d-flex gap-2"><i class="feather-check text-primary mt-1"></i><span>{{ $point }}</span></li>
                            @endforeach
                        </ul>
                        <div class="mt-auto">
                            @if ($contactHref)
                                <a href="{{ $contactHref }}" class="btn btn-dark w-100" @if ($customPlan['contact_url']) target="_blank" rel="noopener" @endif>
                                    <i class="feather-message-circle me-1"></i> Contact us
                                </a>
                            @endif
                            <div class="sub-min">
                                @if ($customPlan['sales_phone'])
                                    or call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $customPlan['sales_phone']) }}">{{ $customPlan['sales_phone'] }}</a>
                                @else
                                    &nbsp;
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Feature comparison (Zoho-style) --}}
        @if ($plans->isNotEmpty())
            <div class="sub-compare mt-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="mb-0 fw-bold">Compare plans</h6>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="sub-compare-search">
                            <i class="feather-search"></i>
                            <input type="search" class="form-control form-control-sm" id="compareSearch" placeholder="Search features like &quot;payroll&quot;" aria-label="Search features">
                        </div>
                        <label class="form-check mb-0 fs-13">
                            <input type="checkbox" class="form-check-input" id="hideCommonRows">
                            <span class="form-check-label">Hide common features</span>
                        </label>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 sub-compare-table">
                        <thead>
                            <tr>
                                <th class="sub-compare-feature">Feature</th>
                                @foreach ($plans as $plan)
                                    <th class="text-center">{{ $plan->name }}</th>
                                @endforeach
                                @if ($showCustom)
                                    <th class="text-center">Custom</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="sub-compare-group" data-group="limits"><td colspan="{{ $compareColumns }}"><button type="button" aria-expanded="true"><i class="feather-chevron-down"></i> Plan &amp; limits</button></td></tr>
                            <tr>
                                <td>Price per user / month</td>
                                @foreach ($plans as $plan)
                                    @php
                                        $prices = $planPrices[$plan->id];
                                        $flat = $plan->price > 0 ? $plan->currency.' '.number_format($plan->price).' flat' : 'Free';
                                        $perUserPlan = $prices['monthly'] !== null || $prices['yearly'] !== null;
                                    @endphp
                                    <td class="text-center">
                                        @if ($perUserPlan)
                                            @foreach (['monthly', 'yearly'] as $cycle)
                                                <span data-show-cycle="{{ $cycle }}">{{ $prices[$cycle] !== null ? '₹'.number_format($prices[$cycle]) : '—' }}</span>
                                            @endforeach
                                        @else
                                            {{ $flat }}
                                        @endif
                                    </td>
                                @endforeach
                                @if ($showCustom)
                                    <td class="text-center">Custom quote</td>
                                @endif
                            </tr>
                            <tr>
                                <td>Minimum users</td>
                                @foreach ($plans as $plan)
                                    <td class="text-center">{{ $plan->min_users ?? '—' }}</td>
                                @endforeach
                                @if ($showCustom)
                                    <td class="text-center">Flexible</td>
                                @endif
                            </tr>
                            <tr>
                                <td>Maximum users</td>
                                @foreach ($plans as $plan)
                                    <td class="text-center">{{ $plan->max_users ? number_format($plan->max_users) : 'Unlimited' }}</td>
                                @endforeach
                                @if ($showCustom)
                                    <td class="text-center">Unlimited</td>
                                @endif
                            </tr>
                            <tr>
                                <td>Storage</td>
                                @foreach ($plans as $plan)
                                    <td class="text-center">{{ $plan->max_storage_mb ? rtrim(rtrim(number_format($plan->max_storage_mb / 1024, 1), '0'), '.').' GB' : 'Unlimited' }}</td>
                                @endforeach
                                @if ($showCustom)
                                    <td class="text-center">Unlimited</td>
                                @endif
                            </tr>
                            <tr>
                                <td>Free trial</td>
                                @foreach ($plans as $plan)
                                    <td class="text-center">{{ $plan->trial_days ? $plan->trial_days.' days' : '—' }}</td>
                                @endforeach
                                @if ($showCustom)
                                    <td class="text-center">On request</td>
                                @endif
                            </tr>

                            {{-- One group per module (header shows whether the plan has the module), then its features. --}}
                            @foreach ($modules as $module)
                                @php
                                    $addon = $isRecurring($module);
                                    $group = $featureCatalog[$module['key']] ?? null;
                                @endphp
                                <tr class="sub-compare-group sub-compare-module" data-group="{{ $module['key'] }}">
                                    <td>
                                        <button type="button" aria-expanded="true">
                                            <i class="feather-chevron-down"></i>
                                            <span class="sub-compare-dot" style="background: {{ $module['color'] }}"></span>
                                            {{ $module['label'] }}
                                        </button>
                                    </td>
                                    @foreach ($plans as $plan)
                                        <td class="text-center">
                                            @if ($includes($plan, $module['key']))
                                                <i class="feather-check sub-yes" aria-label="Included"></i>
                                            @elseif ($addon)
                                                <span class="sub-addon">Add-on</span>
                                            @else
                                                <i class="feather-minus sub-no" aria-label="Not included"></i>
                                            @endif
                                        </td>
                                    @endforeach
                                    @if ($showCustom)
                                        <td class="text-center"><i class="feather-check sub-yes" aria-label="Included"></i></td>
                                    @endif
                                </tr>
                                @foreach ($group['features'] ?? [] as $featureKey => $feature)
                                    <tr>
                                        <td class="sub-compare-sub">{{ $feature['label'] }}</td>
                                        @foreach ($plans as $plan)
                                            <td class="text-center">
                                                @if (! $includes($plan, $module['key']))
                                                    @if ($addon)
                                                        <span class="sub-addon">Add-on</span>
                                                    @else
                                                        <i class="feather-minus sub-no" aria-label="Not included"></i>
                                                    @endif
                                                @elseif ($planFeatures->includes($plan, $featureKey))
                                                    <i class="feather-check sub-yes" aria-label="Included"></i>
                                                @else
                                                    <i class="feather-x sub-x" title="View only on this plan" aria-label="View only"></i>
                                                @endif
                                            </td>
                                        @endforeach
                                        @if ($showCustom)
                                            <td class="text-center"><i class="feather-check sub-yes" aria-label="Included"></i></td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="sub-compare-legend">
                    <span><i class="feather-check sub-yes"></i>Included</span>
                    <span><i class="feather-x sub-x"></i>View only — existing records stay visible, upgrade to create or change</span>
                    <span><span class="sub-addon">Add-on</span> Module can be added to this plan</span>
                </div>
            </div>
        @endif
    </x-ui.card>

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
                                    @if ($payment->purpose === \App\Domains\Platform\Models\SubscriptionPayment::PURPOSE_SUBSCRIPTION)
                                        Subscription: {{ $payment->plan?->name ?? '—' }}
                                    @elseif ($payment->purpose === \App\Domains\Platform\Models\SubscriptionPayment::PURPOSE_MODULE_ADDON)
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
/* Plans & pricing (Zoho-style): cycle toggle, plan cards, Custom card, comparison */
.sub-pricing[data-cycle="monthly"] [data-show-cycle="yearly"],
.sub-pricing[data-cycle="yearly"] [data-show-cycle="monthly"] { display: none !important; }
.sub-cycle-toggle { display: inline-flex; padding: 4px; gap: 4px; border-radius: 999px; background: var(--ax-surface-sunken, #f1f5f9); border: 1px solid var(--ax-border, #e2e8f0); }
.sub-cycle-toggle button { border: 0; background: transparent; border-radius: 999px; padding: 7px 18px; font-size: 13px; font-weight: 600; color: var(--ax-text-muted, #64748b); display: inline-flex; align-items: center; gap: 8px; transition: background .2s, color .2s, box-shadow .2s; }
.sub-cycle-toggle button[aria-pressed="true"] { background: var(--ax-surface, #fff); color: var(--ax-text, #0f172a); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
.sub-save { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; color: var(--ax-positive-strong, #047857); background: var(--ax-positive-soft, #ecfdf5); }
.sub-plan { height: 100%; display: flex; flex-direction: column; padding: 24px 22px 18px; border-radius: 14px; border: 1px solid var(--ax-border, #e2e8f0); background: var(--ax-surface, #fff); transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
.sub-plan:hover { transform: translateY(-3px); box-shadow: 0 14px 30px -14px rgba(15, 23, 42, .25); border-color: var(--ax-brand-soft-border, #c7d2fe); }
.sub-plan.is-current { border: 2px solid var(--ax-brand, #4f46e5); }
.sub-plan-name { display: block; font-family: var(--ax-font-display); font-size: 13px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--ax-brand, #4f46e5); }
.sub-plan-price { min-height: 108px; margin-bottom: 14px; }
.sub-amount { font-family: var(--ax-font-display); font-size: 34px; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; color: var(--ax-text, #0f172a); }
.sub-min { min-height: 20px; margin-top: 10px; text-align: center; font-size: 13px; font-weight: 600; color: var(--ax-text-body, #334155); }
.sub-plan-custom { background: linear-gradient(160deg, var(--ax-brand-soft, #eef2ff), var(--ax-surface, #fff) 60%); }
.sub-compare { border-top: 1px solid var(--ax-divider, #e2e8f0); padding-top: 20px; }
.sub-compare-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--ax-text-muted, #64748b); white-space: nowrap; }
.sub-compare-table td { vertical-align: middle; font-size: 13px; }
.sub-compare-feature { min-width: 220px; }
.sub-compare-group td { background: var(--ax-surface-muted, #f8fafc) !important; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--ax-brand, #4f46e5); }
.sub-compare-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
.sub-yes { color: var(--ax-positive, #10b981); font-size: 16px; }
.sub-no { color: var(--ax-text-subtle, #cbd5e1); }
.sub-addon { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; color: var(--ax-pending-strong, #b45309); background: var(--ax-pending-soft, #fffbeb); }
.sub-compare-table tr.is-common, .sub-compare-table tr.is-filtered, .sub-compare-table tr.is-collapsed { display: none; }
.sub-compare-module td { text-transform: none !important; letter-spacing: 0 !important; font-size: 13.5px !important; color: var(--ax-text, #0f172a) !important; }
.sub-compare-sub { padding-left: 34px !important; color: var(--ax-text-body, #334155); }
.sub-x { color: var(--ax-text-subtle, #94a3b8); font-size: 15px; }
.sub-compare-legend { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 10px; font-size: 12px; color: var(--ax-text-muted, #64748b); }
.sub-compare-legend i { margin-right: 4px; }
.sub-compare-group button { border: 0; background: none; padding: 0; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; display: inline-flex; align-items: center; gap: 6px; }
.sub-compare-group button i { transition: transform .2s ease; }
.sub-compare-group button[aria-expanded="false"] i { transform: rotate(-90deg); }
.sub-compare-search { position: relative; }
.sub-compare-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--ax-text-muted, #64748b); font-size: 13px; pointer-events: none; }
.sub-compare-search input { padding-left: 30px; min-width: 240px; }
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
        // Plans: Monthly/Yearly switch and "Hide common features" in the comparison.
        (function () {
            var pricing = document.getElementById('subPricing');
            if (!pricing) return;
            pricing.querySelectorAll('[data-cycle-btn]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    pricing.dataset.cycle = btn.dataset.cycleBtn;
                    pricing.querySelectorAll('[data-cycle-btn]').forEach(function (b) {
                        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                    });
                });
            });
            // Comparison: hide rows every plan shares, filter by search, collapse a group.
            var rows = Array.prototype.slice.call(pricing.querySelectorAll('.sub-compare-table tbody tr'));
            var hide = document.getElementById('hideCommonRows');
            var search = document.getElementById('compareSearch');
            var group = null;
            rows.forEach(function (row) {
                if (row.classList.contains('sub-compare-group')) { group = row.dataset.group; return; }
                row.dataset.inGroup = group;
                var cells = Array.prototype.slice.call(row.cells, 1).map(function (c) { return c.innerHTML.replace(/\s+/g, ' ').trim(); });
                row.dataset.common = cells.every(function (c) { return c === cells[0]; }) ? '1' : '0';
            });
            function applyFilters() {
                var q = search ? search.value.trim().toLowerCase() : '';
                rows.forEach(function (row) {
                    if (row.classList.contains('sub-compare-group')) return;
                    row.classList.toggle('is-common', !!(hide && hide.checked) && row.dataset.common === '1');
                    row.classList.toggle('is-filtered', q !== '' && row.cells[0].textContent.toLowerCase().indexOf(q) === -1);
                });
            }
            if (hide) hide.addEventListener('change', applyFilters);
            if (search) search.addEventListener('input', applyFilters);
            pricing.querySelectorAll('.sub-compare-group button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var open = btn.getAttribute('aria-expanded') !== 'true';
                    var key = btn.closest('tr').dataset.group;
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    rows.forEach(function (row) { if (row.dataset.inGroup === key) row.classList.toggle('is-collapsed', !open); });
                });
            });
        })();
    </script>
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
