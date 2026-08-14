<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Document::with(['documentable', 'documentMaster', 'requestedBy'])
            ->where('documentable_type', Employee::class);

        // Search by employee name, ID or document name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('documentMaster', function ($q2) use ($search): void {
                      $q2->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('documentable', [Employee::class], function ($q3) use ($search): void {
                      $q3->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(15);

        // Fetch all active employees, templates and categories for the upload modal selection
        $employees = Employee::orderBy('full_name')->get();
        $categories = DocumentCategory::orderBy('name')->get();
        $templates = DocumentMaster::where('status', 'active')
            ->whereIn('upload_responsibility', ['hr', 'both'])
            ->orderBy('name')
            ->get();

        return view('modules.hrms.documents.index', compact('documents', 'employees', 'templates', 'categories'));
    }

    public function bulkUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id'        => 'required|string',
            'document_master_id' => 'required|exists:document_masters,id',
            'file'               => 'required|file|max:10240', // Max 10MB
            'expiry_date'        => 'nullable|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $file = $request->file('file');
        $documentMaster = DocumentMaster::findOrFail($request->integer('document_master_id'));

        // Resolve target employee IDs
        $employeeId = $request->input('employee_id');
        $targetEmployeeIds = [];
        if ($employeeId === 'all') {
            $targetEmployeeIds = Employee::pluck('id')->toArray();
        } else {
            $targetEmployeeIds = [$employeeId];
        }

        if (empty($targetEmployeeIds)) {
            return redirect()->back()->with('error', 'No employees found for this upload target.');
        }

        $approvalRequired = (bool) $documentMaster->approval_required;
        $status = $approvalRequired ? 'uploaded' : 'approved';

        foreach ($targetEmployeeIds as $empId) {
            $employee = Employee::find($empId);
            if (!$employee) {
                continue;
            }

            // Store file
            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            // Find or create document record for this employee and master
            $document = Document::where('documentable_type', Employee::class)
                ->where('documentable_id', $employee->id)
                ->where('document_master_id', $documentMaster->id)
                ->first();

            if ($document) {
                $document->update([
                    'file_name'   => $file->getClientOriginalName(),
                    'file_path'   => $path,
                    'file_type'   => $file->getClientMimeType(),
                    'file_size'   => $file->getSize(),
                    'expiry_date' => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                    'status'      => $status,
                ]);
            } else {
                Document::create([
                    'tenant_id'          => $tenantId,
                    'documentable_id'    => $employee->id,
                    'documentable_type'  => Employee::class,
                    'document_master_id' => $documentMaster->id,
                    'name'               => $documentMaster->name,
                    'description'        => $documentMaster->description,
                    'has_expiry'         => $documentMaster->expiry_applicable,
                    'file_name'          => $file->getClientOriginalName(),
                    'file_path'          => $path,
                    'file_type'          => $file->getClientMimeType(),
                    'file_size'          => $file->getSize(),
                    'expiry_date'        => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                    'status'             => $status,
                    'requested_by_id'    => auth()->id(),
                ]);
            }
        }

        return redirect()->route('hrms.documents.index')->with('success', 'Documents uploaded successfully.');
    }
}
