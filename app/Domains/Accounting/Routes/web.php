<?php

use App\Domains\Accounting\Controllers\AccountingAuditLogController;
use App\Domains\Accounting\Controllers\AccountingPeriodController;
use App\Domains\Accounting\Controllers\AccountingPostingFailureController;
use App\Domains\Accounting\Controllers\ApAgingController;
use App\Domains\Accounting\Controllers\ArAgingController;
use App\Domains\Accounting\Controllers\BalanceSheetController;
use App\Domains\Accounting\Controllers\BankReconciliationController;
use App\Domains\Accounting\Controllers\BudgetController;
use App\Domains\Accounting\Controllers\BudgetVsActualController;
use App\Domains\Accounting\Controllers\CashFlowController;
use App\Domains\Accounting\Controllers\ChartOfAccountController;
use App\Domains\Accounting\Controllers\CostCenterController;
use App\Domains\Accounting\Controllers\FixedAssets\AssetCategoryController;
use App\Domains\Accounting\Controllers\FixedAssets\AssetDepreciationController;
use App\Domains\Accounting\Controllers\FixedAssets\AssetDisposalController;
use App\Domains\Accounting\Controllers\FixedAssets\AssetRegisterController;
use App\Domains\Accounting\Controllers\FixedAssets\AssetRevaluationController;
use App\Domains\Accounting\Controllers\FixedAssets\AssetWriteOffController;
use App\Domains\Accounting\Controllers\DayBookController;
use App\Domains\Accounting\Controllers\FiscalYearController;
use App\Domains\Accounting\Controllers\GeneralLedgerController;
use App\Domains\Accounting\Controllers\GstSummaryController;
use App\Domains\Accounting\Controllers\Gstr1Controller;
use App\Domains\Accounting\Controllers\Gstr3bController;
use App\Domains\Accounting\Controllers\JournalController;
use App\Domains\Accounting\Controllers\PartyLedgerController;
use App\Domains\Accounting\Controllers\ProfitLossController;
use App\Domains\Accounting\Controllers\TaxRateController;
use App\Domains\Accounting\Controllers\TrialBalanceController;
use App\Domains\Accounting\Controllers\VoucherController;
use App\Domains\Accounting\Support\VoucherType;
use Illuminate\Support\Facades\Route;

Route::prefix('accounting')
    ->as('accounting.')
    ->group(function (): void {
        Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
        Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
        Route::put('chart-of-accounts/{account}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
        Route::delete('chart-of-accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('chart-of-accounts.destroy');

        Route::get('cost-centers', [CostCenterController::class, 'index'])->name('cost-centers.index');
        Route::post('cost-centers', [CostCenterController::class, 'store'])->name('cost-centers.store');
        Route::put('cost-centers/{costCenter}', [CostCenterController::class, 'update'])->name('cost-centers.update');
        Route::delete('cost-centers/{costCenter}', [CostCenterController::class, 'destroy'])->name('cost-centers.destroy');

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

        Route::get('bank-reconciliation', [BankReconciliationController::class, 'index'])->name('bank-reconciliation.index');
        Route::get('bank-reconciliation/create', [BankReconciliationController::class, 'create'])->name('bank-reconciliation.create');
        Route::post('bank-reconciliation', [BankReconciliationController::class, 'store'])->name('bank-reconciliation.store');
        Route::get('bank-reconciliation/{reconciliation}', [BankReconciliationController::class, 'show'])->name('bank-reconciliation.show');
        Route::post('bank-reconciliation/{reconciliation}/import', [BankReconciliationController::class, 'import'])->name('bank-reconciliation.import');
        Route::post('bank-reconciliation/{reconciliation}/auto-match', [BankReconciliationController::class, 'autoMatch'])->name('bank-reconciliation.auto-match');
        Route::post('bank-reconciliation/{reconciliation}/match', [BankReconciliationController::class, 'match'])->name('bank-reconciliation.match');
        Route::post('bank-reconciliation/{reconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('bank-reconciliation.complete');

        Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::get('budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
        Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
        Route::get('budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit');
        Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
        Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
        Route::post('budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve');

        Route::get('journals', [JournalController::class, 'index'])->name('journals.index');
        Route::get('journals/create', [JournalController::class, 'create'])->name('journals.create');
        Route::post('journals', [JournalController::class, 'store'])->name('journals.store');
        Route::get('journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
        Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse');

        Route::get('reports/day-book', [DayBookController::class, 'index'])->name('reports.day-book');
        Route::get('reports/trial-balance', [TrialBalanceController::class, 'index'])->name('reports.trial-balance');
        Route::get('reports/general-ledger', [GeneralLedgerController::class, 'index'])->name('reports.general-ledger');
        Route::get('reports/party-ledger', [PartyLedgerController::class, 'index'])->name('reports.party-ledger');
        Route::get('reports/balance-sheet', [BalanceSheetController::class, 'index'])->name('reports.balance-sheet');
        Route::get('reports/balance-sheet/pdf', [BalanceSheetController::class, 'downloadPdf'])->name('reports.balance-sheet.pdf');
        Route::get('reports/profit-loss', [ProfitLossController::class, 'index'])->name('reports.profit-loss');
        Route::get('reports/ar-aging', [ArAgingController::class, 'index'])->name('reports.ar-aging');
        Route::get('reports/ap-aging', [ApAgingController::class, 'index'])->name('reports.ap-aging');
        Route::get('reports/cash-flow', [CashFlowController::class, 'index'])->name('reports.cash-flow');
        Route::get('reports/gst-summary', [GstSummaryController::class, 'index'])->name('reports.gst-summary');
        Route::get('reports/gstr1', [Gstr1Controller::class, 'index'])->name('reports.gstr1');
        Route::get('reports/gstr3b', [Gstr3bController::class, 'index'])->name('reports.gstr3b');
        Route::get('reports/audit-trail', [AccountingAuditLogController::class, 'index'])->name('reports.audit-trail');
        Route::get('reports/budget-vs-actual', [BudgetVsActualController::class, 'index'])->name('reports.budget-vs-actual');

        Route::get('posting-failures', [AccountingPostingFailureController::class, 'index'])->name('posting-failures.index');
        Route::post('posting-failures/{failure}/retry', [AccountingPostingFailureController::class, 'retry'])->name('posting-failures.retry');
        Route::post('posting-failures/{failure}/dismiss', [AccountingPostingFailureController::class, 'dismiss'])->name('posting-failures.dismiss');

        Route::prefix('fixed-assets')
            ->as('fixed-assets.')
            ->group(function (): void {
                // Sub-resource groups (depreciation/disposals/write-offs/revaluations)
                // must be registered before the /{asset} show route below — otherwise
                // that wildcard greedily matches segments like "depreciation" first,
                // attempts to resolve them as an Asset id, and 404s before the more
                // specific routes are ever reached.
                Route::get('/', [AssetRegisterController::class, 'index'])->name('index');

                Route::prefix('depreciation')
                    ->as('depreciation.')
                    ->group(function (): void {
                        Route::get('/', [AssetDepreciationController::class, 'index'])->name('index');
                        Route::post('/generate', [AssetDepreciationController::class, 'generate'])->name('generate');
                        Route::post('/{schedule}/review', [AssetDepreciationController::class, 'review'])->name('review');
                        Route::post('/{schedule}/approve', [AssetDepreciationController::class, 'approve'])->name('approve');
                        Route::post('/{schedule}/post', [AssetDepreciationController::class, 'post'])->name('post');
                    });

                Route::prefix('disposals')
                    ->as('disposals.')
                    ->group(function (): void {
                        Route::get('/', [AssetDisposalController::class, 'index'])->name('index');
                        Route::get('/create', [AssetDisposalController::class, 'create'])->name('create');
                        Route::post('/', [AssetDisposalController::class, 'store'])->name('store');
                        Route::post('/{disposal}/approve', [AssetDisposalController::class, 'approve'])->name('approve');
                        Route::post('/{disposal}/reject', [AssetDisposalController::class, 'reject'])->name('reject');
                        Route::post('/{disposal}/post', [AssetDisposalController::class, 'post'])->name('post');
                    });

                Route::prefix('write-offs')
                    ->as('write-offs.')
                    ->group(function (): void {
                        Route::get('/', [AssetWriteOffController::class, 'index'])->name('index');
                        Route::get('/create', [AssetWriteOffController::class, 'create'])->name('create');
                        Route::post('/', [AssetWriteOffController::class, 'store'])->name('store');
                        Route::post('/{writeOff}/approve', [AssetWriteOffController::class, 'approve'])->name('approve');
                        Route::post('/{writeOff}/reject', [AssetWriteOffController::class, 'reject'])->name('reject');
                        Route::post('/{writeOff}/post', [AssetWriteOffController::class, 'post'])->name('post');
                    });

                Route::prefix('revaluations')
                    ->as('revaluations.')
                    ->group(function (): void {
                        Route::get('/', [AssetRevaluationController::class, 'index'])->name('index');
                        Route::get('/create', [AssetRevaluationController::class, 'create'])->name('create');
                        Route::post('/', [AssetRevaluationController::class, 'store'])->name('store');
                        Route::post('/{revaluation}/approve', [AssetRevaluationController::class, 'approve'])->name('approve');
                        Route::post('/{revaluation}/reject', [AssetRevaluationController::class, 'reject'])->name('reject');
                        Route::post('/{revaluation}/post', [AssetRevaluationController::class, 'post'])->name('post');
                    });

                Route::prefix('categories')
                    ->as('categories.')
                    ->group(function (): void {
                        Route::get('/', [AssetCategoryController::class, 'index'])->name('index');
                        Route::post('/', [AssetCategoryController::class, 'store'])->name('store');
                        Route::put('/{category}', [AssetCategoryController::class, 'update'])->name('update');
                        Route::delete('/{category}', [AssetCategoryController::class, 'destroy'])->name('destroy');
                    });

                Route::get('/register', [AssetRegisterController::class, 'create'])->name('register');
                Route::post('/register', [AssetRegisterController::class, 'store'])->name('register.store');

                Route::get('/{asset}', [AssetRegisterController::class, 'show'])->name('show');
                Route::post('/{asset}/capitalize', [AssetRegisterController::class, 'capitalize'])->name('capitalize');
            });

        foreach (VoucherType::ALL as $voucherType) {
            Route::prefix("vouchers/{$voucherType}")
                ->as("vouchers.{$voucherType}.")
                ->group(function () use ($voucherType): void {
                    Route::get('/', [VoucherController::class, 'index'])->name('index')->defaults('type', $voucherType);
                    Route::get('/create', [VoucherController::class, 'create'])->name('create')->defaults('type', $voucherType);
                    Route::post('/', [VoucherController::class, 'store'])->name('store')->defaults('type', $voucherType);
                    Route::get('/{journal}', [VoucherController::class, 'show'])->name('show')->defaults('type', $voucherType);
                    Route::post('/{journal}/reverse', [VoucherController::class, 'reverse'])->name('reverse')->defaults('type', $voucherType);
                });
        }
    });
