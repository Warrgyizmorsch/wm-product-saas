<?php

use Illuminate\Support\Facades\Route;
use App\Domains\CRM\Controllers\Api\LeadApiController;

/*
|--------------------------------------------------------------------------
| CRM Domain REST API Routes
|--------------------------------------------------------------------------
| Location: app/Domains/CRM/Routes/api.php
| Automatically loaded by domain route loader in routes/web.php
| Endpoint: POST /api/crm/leads
| Supports both Single Lead and Bulk Leads in ONE endpoint.
|--------------------------------------------------------------------------
*/

Route::prefix('api/crm/leads')
    ->middleware(['auth:sanctum'])
    ->name('api.crm.leads.')
    ->group(function () {
        Route::post('/', [LeadApiController::class, 'store'])->name('store');
    });
