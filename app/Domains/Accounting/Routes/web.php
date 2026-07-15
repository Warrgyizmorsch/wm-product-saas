<?php

use App\Domains\Accounting\Controllers\AccountingPeriodController;
use App\Domains\Accounting\Controllers\ChartOfAccountController;
use App\Domains\Accounting\Controllers\FiscalYearController;
use App\Domains\Accounting\Controllers\GeneralLedgerController;
use App\Domains\Accounting\Controllers\JournalController;
use App\Domains\Accounting\Controllers\TaxRateController;
use App\Domains\Accounting\Controllers\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('accounting')
    ->as('accounting.')
    ->group(function (): void {
        Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
        Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
        Route::put('chart-of-accounts/{account}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
        Route::delete('chart-of-accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('chart-of-accounts.destroy');

        Route::get('fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
        Route::post('fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('fiscal-years/{fiscalYear}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');

        Route::post('periods/{period}/close', [AccountingPeriodController::class, 'close'])->name('periods.close');
        Route::post('periods/{period}/lock', [AccountingPeriodController::class, 'lock'])->name('periods.lock');
        Route::post('periods/{period}/reopen', [AccountingPeriodController::class, 'reopen'])->name('periods.reopen');

        Route::get('tax-rates', [TaxRateController::class, 'index'])->name('tax-rates.index');
        Route::post('tax-rates', [TaxRateController::class, 'store'])->name('tax-rates.store');
        Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('tax-rates.update');
        Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->name('tax-rates.destroy');

        Route::get('journals', [JournalController::class, 'index'])->name('journals.index');
        Route::get('journals/create', [JournalController::class, 'create'])->name('journals.create');
        Route::post('journals', [JournalController::class, 'store'])->name('journals.store');
        Route::get('journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
        Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse');

        Route::get('reports/trial-balance', [TrialBalanceController::class, 'index'])->name('reports.trial-balance');
        Route::get('reports/general-ledger', [GeneralLedgerController::class, 'index'])->name('reports.general-ledger');
    });
