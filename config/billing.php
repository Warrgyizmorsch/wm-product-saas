<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recurring per-user billing (Zoho-style)
    |--------------------------------------------------------------------------
    |
    | Plans and module add-ons are priced per user per month, with a lower
    | per-month price when billed yearly (plans.monthly_price_per_user /
    | yearly_price_per_user, module_prices). Listed prices exclude GST; the
    | quote adds gst_rate on top (see SubscriptionPricing).
    |
    */

    'currency' => env('BILLING_CURRENCY', 'INR'),

    'gst_rate' => (float) env('BILLING_GST_RATE', 18),

    // Days a tenant keeps access after a renewal charge fails, before suspension.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'cycles' => [
        'monthly' => ['label' => 'Monthly', 'months' => 1],
        'yearly' => ['label' => 'Yearly', 'months' => 12],
    ],

    /*
    | The "Custom" card after the plans (Zoho's "Contact us" tier) — tailored
    | deployments sold by the sales team, not through checkout. Contact goes to
    | the url when set, otherwise an email to sales_email.
    */
    'custom_plan' => [
        'enabled' => (bool) env('BILLING_CUSTOM_PLAN', true),
        'sales_email' => env('BILLING_SALES_EMAIL', env('MAIL_FROM_ADDRESS')),
        'sales_phone' => env('BILLING_SALES_PHONE'),
        'contact_url' => env('BILLING_CONTACT_URL'),
        'description' => 'For organisations with millions of transactions, complex workflows or large data that need a tailored deployment.',
        'highlights' => [
            'Tailored ERP deployment',
            'Expert implementation assistance',
            'Advanced customisations and integrations',
            'Dedicated account manager',
        ],
    ],

];
