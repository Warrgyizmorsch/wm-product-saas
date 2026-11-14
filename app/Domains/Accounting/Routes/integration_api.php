<?php

use App\Domains\Accounting\Controllers\Api\Integration\ConnectionApiController;
use App\Domains\Accounting\Controllers\Api\Integration\SyncApiController;
use App\Domains\Accounting\Controllers\Api\Integration\TallyApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting integration API, v1 (/api/v1/accounting/integrations)
|--------------------------------------------------------------------------
| Deliberately not named api.php: routes/web.php requires every module's
| Routes/api.php, and this API has its own middleware stack.
*/

Route::middleware([
    'tenant',
    'auth:sanctum',
    'production.api.tenant', // user must belong to the active tenant; billing lock
    'accounting.api.module',
    'throttle:accounting-integration',
])->name('api.accounting.integrations.')->group(function () {
    $entity = 'ledger-groups|ledgers|vouchers';

    Route::middleware('permission:accounting.integrations.view')->group(function () use ($entity) {
        Route::get('connections', [ConnectionApiController::class, 'index'])->name('connections.index');
        Route::get('connections/{connection}', [ConnectionApiController::class, 'show'])->whereNumber('connection')->name('connections.show');
        Route::get('connections/{connection}/mappings', [ConnectionApiController::class, 'mappings'])->whereNumber('connection')->name('mappings.index');
        Route::get('connections/{connection}/batches', [SyncApiController::class, 'batches'])->whereNumber('connection')->name('batches.index');
        Route::get('connections/{connection}/batches/{batch}', [SyncApiController::class, 'batch'])->whereNumber(['connection', 'batch'])->name('batches.show');
        Route::get('connections/{connection}/conflicts', [SyncApiController::class, 'conflicts'])->whereNumber('connection')->name('conflicts.index');
        Route::get('connections/{connection}/feed/{entity}', [SyncApiController::class, 'feed'])->whereNumber('connection')->where('entity', $entity)->name('feed');
    });

    Route::middleware('permission:accounting.integrations.manage')->group(function () {
        Route::post('connections', [ConnectionApiController::class, 'store'])->name('connections.store');
        Route::patch('connections/{connection}', [ConnectionApiController::class, 'update'])->whereNumber('connection')->name('connections.update');
        Route::delete('connections/{connection}', [ConnectionApiController::class, 'destroy'])->whereNumber('connection')->name('connections.destroy');
        Route::post('connections/{connection}/tokens', [ConnectionApiController::class, 'mintToken'])->whereNumber('connection')->name('tokens.store');
        Route::delete('connections/{connection}/tokens/{token}', [ConnectionApiController::class, 'revokeToken'])->whereNumber(['connection', 'token'])->name('tokens.destroy');
        Route::post('connections/{connection}/mappings', [ConnectionApiController::class, 'setMapping'])->whereNumber('connection')->name('mappings.store');
    });

    Route::middleware('permission:accounting.integrations.sync')->group(function () use ($entity) {
        Route::post('connections/{connection}/{entity}/batch', [SyncApiController::class, 'ingestBatch'])->whereNumber('connection')->where('entity', $entity)->name('batch.store');
        Route::post('connections/{connection}/feed/{entity}/ack', [SyncApiController::class, 'acknowledge'])->whereNumber('connection')->where('entity', $entity)->name('feed.ack');
        Route::post('connections/{connection}/batches/{batch}/retry', [SyncApiController::class, 'retryBatch'])->whereNumber(['connection', 'batch'])->name('batches.retry');

        Route::post('connections/{connection}/tally/sync', [TallyApiController::class, 'plan'])->whereNumber('connection')->name('tally.sync');
        Route::post('connections/{connection}/tally/retry-pushes', [TallyApiController::class, 'retryPushes'])->whereNumber('connection')->name('tally.retry-pushes');
        Route::post('connections/{connection}/tally/agent/poll', [TallyApiController::class, 'poll'])->whereNumber('connection')->name('tally.poll');
        Route::post('connections/{connection}/tally/agent/jobs/{job}/result', [TallyApiController::class, 'result'])->whereNumber(['connection', 'job'])->name('tally.result');
        Route::post('connections/{connection}/tally/import-file', [TallyApiController::class, 'importFile'])->whereNumber('connection')->name('tally.import-file');
        Route::get('connections/{connection}/tally/export-file', [TallyApiController::class, 'exportFile'])->whereNumber('connection')->name('tally.export-file');
    });

    Route::post('connections/{connection}/conflicts/{conflict}/resolve', [SyncApiController::class, 'resolveConflict'])
        ->middleware('permission:accounting.integrations.resolve')
        ->whereNumber(['connection', 'conflict'])
        ->name('conflicts.resolve');
});
