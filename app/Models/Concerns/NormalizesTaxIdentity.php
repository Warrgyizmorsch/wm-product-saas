<?php

namespace App\Models\Concerns;

use App\Services\TaxIdentityService;

trait NormalizesTaxIdentity
{
    /**
     * Boot the trait to normalize tax identifiers before saving.
     */
    public static function bootNormalizesTaxIdentity(): void
    {
        static::saving(function ($model) {
            $gstinFields = ['gstin', 'seller_gstin', 'billing_gstin', 'customer_gstin', 'vendor_gstin'];
            foreach ($gstinFields as $field) {
                if (array_key_exists($field, $model->attributes) && $model->attributes[$field] !== null) {
                    $model->attributes[$field] = TaxIdentityService::normalizeGstin($model->attributes[$field]);
                }
            }

            $panFields = ['pan', 'pan_number', 'pan_card_number'];
            foreach ($panFields as $field) {
                if (array_key_exists($field, $model->attributes) && $model->attributes[$field] !== null) {
                    $model->attributes[$field] = TaxIdentityService::normalizePan($model->attributes[$field]);
                }
            }
        });
    }
}
