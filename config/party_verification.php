<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Second-person verification of customers and vendors (FRS 2.3, C-15)
    |--------------------------------------------------------------------------
    |
    | When enabled, a party created through a create screen or API starts as
    | "pending verification" and inactive. A different authorised person must
    | verify its GSTIN, PAN and bank details before it can become active.
    |
    | Set PARTY_VERIFICATION_ENABLED=false to switch the control off (parties
    | then behave exactly as before this feature existed).
    |
    */
    'enabled' => env('PARTY_VERIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Party Deletion Protection (FRS 2.3: Parties linked to transactions
    | are never hard-deleted)
    |--------------------------------------------------------------------------
    |
    | Set PARTY_DELETION_PROTECTION_ENABLED=false to bypass hard-delete blocking
    | (e.g. during certain bulk testing or development resets).
    |
    */
    'deletion_protection_enabled' => env('PARTY_DELETION_PROTECTION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Multi-branch customers, vendors & shared contacts (FRS 2.3, C-17)
    |--------------------------------------------------------------------------
    |
    | Set PARTY_BRANCHES_ENABLED=false to hide branch selectors on documents
    | and revert to legacy single-address document behaviour.
    |
    */
    'branches_enabled' => env('PARTY_BRANCHES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Callback-verified bank changes (FRS 2.3, C-16 bank part)
    |--------------------------------------------------------------------------
    |
    | When enabled, the bank details of a vendor that has been verified can only
    | change through a bank change request: a different person calls the vendor
    | back on the number already on file, records the call, and approves.
    | Set PARTY_BANK_CHANGE_CONTROL_ENABLED=false to edit bank details directly
    | again (this switch also needs the party verification switch above on).
    |
    */
    'bank_change_enabled' => env('PARTY_BANK_CHANGE_CONTROL_ENABLED', true),

    'bank_fields' => [
        'bank_name',
        'account_number',
        'ifsc_code',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inactive customers and vendors (FRS 2.3, C-16)
    |--------------------------------------------------------------------------
    |
    | There is one "inactive" state. When a verified party is made inactive a
    | reason is recorded. A party made inactive for a RISK reason can only be
    | reactivated by a different user who holds the reactivate permission.
    |
    | transaction_block_enabled: an inactive or unverified party cannot be used
    | on NEW sales orders, dispatches, purchase orders, RFQs, advance payments or
    | subcontract challans. Existing documents, invoices, bills, receipts, returns
    | and payments are not blocked. Set PARTY_TRANSACTION_BLOCK_ENABLED=false to
    | switch the blocking off.
    |
    */
    'transaction_block_enabled' => env('PARTY_TRANSACTION_BLOCK_ENABLED', true),

    'inactive_reasons' => [
        'dormant' => ['label' => 'Dormant, no recent business', 'risk' => false, 'blocks_payments' => false],
        'closed_business' => ['label' => 'Business closed', 'risk' => false, 'blocks_payments' => false],
        'duplicate' => ['label' => 'Duplicate record', 'risk' => false, 'blocks_payments' => false],
        'credit_risk' => ['label' => 'Credit risk', 'risk' => true, 'blocks_payments' => false],
        'fraud_suspicion' => ['label' => 'Suspected fraud', 'risk' => true, 'blocks_payments' => true],
        'compliance_issue' => ['label' => 'Compliance issue', 'risk' => true, 'blocks_payments' => true],
        'other' => ['label' => 'Other', 'risk' => false, 'blocks_payments' => false],
    ],

    /*
    | Fields that need independent verification. Changing any of them on a
    | verified party sends it back to "pending verification".
    */
    'sensitive_fields' => [
        'gstin',
        'pan',
        'phone',
        'bank_name',
        'account_number',
        'ifsc_code',
    ],
];
