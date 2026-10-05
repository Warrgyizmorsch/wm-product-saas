<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Gstr2bImport;
use App\Domains\Accounting\Models\Gstr2bLine;
use App\Domains\Accounting\Services\Gst\Gstr2bReconciliationService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * GST Returns → GSTR-2B Reconciliation: upload the portal's GSTR-2B JSON and
 * see which supplier invoices match the purchase bills in the books.
 */
class Gstr2bReconciliationController extends Controller
{
    public const FILTERS = ['all', Gstr2bLine::MATCHED, Gstr2bLine::MISMATCH, Gstr2bLine::MISSING_IN_BOOKS, 'books_only', Gstr2bLine::NOTE];

    public function __construct(
        private readonly AccessService $access,
        private readonly Gstr2bReconciliationService $service,
    ) {
    }

    public function index(): View
    {
        $this->authorizeFor('accounting.gst_returns.view');

        return view('modules.accounting.gst-returns.gstr2b.index', [
            'imports' => $this->imports()->with('importer:id,name')->latest('return_period')->latest('id')->paginate(15),
            'canFile' => $this->allows('accounting.gst_returns.file'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFor('accounting.gst_returns.file');
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ], [], ['file' => 'GSTR-2B JSON file']);

        $file = $request->file('file');
        if (! in_array(strtolower($file->getClientOriginalExtension()), ['json', 'txt'], true)) {
            return back()->with('error', 'Upload the .json file downloaded from the GST portal.');
        }

        try {
            $import = $this->service->import((string) file_get_contents($file->getRealPath()), $file->getClientOriginalName(), auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.gst-returns.gstr2b.show', $import)
            ->with('success', "GSTR-2B for {$import->periodLabel()} imported: {$import->line_count} documents matched against your bills.");
    }

    public function show(Request $request, int $import): View
    {
        $this->authorizeFor('accounting.gst_returns.view');
        $import = $this->imports()->findOrFail($import);

        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'all';
        $search = trim((string) $request->query('q', ''));

        $lines = collect();
        $booksOnly = collect();

        if ($filter === 'books_only') {
            $booksOnly = $this->service->booksOnly($import);
        } else {
            $lines = $import->lines()
                ->with('vendorBill')
                ->when($filter !== 'all', fn ($q) => $q->where('match_status', $filter))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('supplier_gstin', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")))
                ->orderByRaw("CASE match_status WHEN 'mismatch' THEN 0 WHEN 'missing_in_books' THEN 1 WHEN 'note' THEN 2 ELSE 3 END")
                ->orderBy('supplier_name')
                ->orderBy('document_date')
                ->paginate(50)
                ->withQueryString();
        }

        return view('modules.accounting.gst-returns.gstr2b.show', [
            'import' => $import,
            'summary' => $import->summary ?? $this->service->summarize($import),
            'filter' => $filter,
            'search' => $search,
            'lines' => $lines,
            'booksOnly' => $booksOnly,
            'withoutGstin' => $this->service->billsWithoutVendorGstin($import),
            'canFile' => $this->allows('accounting.gst_returns.file'),
            'service' => $this->service,
        ]);
    }

    public function rematch(int $import): RedirectResponse
    {
        $this->authorizeFor('accounting.gst_returns.file');
        $import = $this->service->match($this->imports()->findOrFail($import));

        return back()->with('success', 'Matching re-run against the current bills.');
    }

    public function destroy(int $import): RedirectResponse
    {
        $this->authorizeFor('accounting.gst_returns.file');
        $import = $this->imports()->findOrFail($import);
        $label = $import->periodLabel();
        $import->lines()->delete();
        $import->delete();

        return redirect()->route('accounting.gst-returns.gstr2b.index')->with('success', "GSTR-2B for {$label} removed.");
    }

    private function imports()
    {
        $companyId = company_id() ?? current_company_id();

        return Gstr2bImport::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));
    }

    private function authorizeFor(string $permission): void
    {
        abort_unless($this->allows($permission), 403);
    }

    private function allows(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $this->access->allows($user, $permission, [
            'tenant_id' => $user->tenant_id,
            'company_id' => current_company_id(),
            'branch_id' => current_branch_id(),
        ]);
    }
}
