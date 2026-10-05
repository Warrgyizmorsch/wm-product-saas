<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankReconciliationRule;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\BankReconciliationRuleService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Narration rules used by bank reconciliation. Viewing needs bank
 * reconciliation view rights; changing them the same rights as reconciling.
 */
class BankReconciliationRuleController extends Controller
{
    public function __construct(
        private readonly BankReconciliationRuleService $rules,
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BankReconciliation::class);

        $filters = $request->only(['search', 'source', 'status']);

        $rules = BankReconciliationRule::with(['targetAccount', 'bankAccount'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('pattern', 'like', "%{$search}%")->orWhere('party_name', 'like', "%{$search}%")))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('priority')
            ->orderByDesc('hits')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $active = $this->accounts->active();
        $parentIds = ChartOfAccount::query()->whereNotNull('parent_id')->distinct()->pluck('parent_id')->flip();

        return view('modules.accounting.bank-rules.index', [
            'rules' => $rules,
            'filters' => $filters,
            'canManage' => auth()->user()?->can('create', BankReconciliation::class) ?? false,
            'postableAccounts' => $active->reject(fn ($a) => isset($parentIds[$a->id]))->sortBy('code')->values(),
            'bankAccounts' => $active->where('is_cash_or_bank', true)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        return $this->persist(fn () => $this->rules->save($this->validated($request), null, auth()->id()), 'Rule created.');
    }

    public function update(Request $request, BankReconciliationRule $rule): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        return $this->persist(fn () => $this->rules->save($this->validated($request), $rule, auth()->id()), 'Rule updated.');
    }

    public function toggle(BankReconciliationRule $rule): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Rule activated.' : 'Rule paused.');
    }

    public function destroy(BankReconciliationRule $rule): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $rule->delete();

        return back()->with('success', 'Rule deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $tenantId = require_tenant_id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'match_type' => ['required', Rule::in(BankReconciliationRule::MATCH_TYPES)],
            'pattern' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in(BankReconciliationRule::DIRECTIONS)],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'target_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'party_name' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            'auto_post' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['priority'] = $data['priority'] ?? 100;
        $data['auto_post'] = (bool) ($data['auto_post'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function persist(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pattern' => $e->getMessage()])->withInput();
        }

        return back()->with('success', $message);
    }
}
