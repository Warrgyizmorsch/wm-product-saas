<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmailConfiguration;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailSettingController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    public function index(): View
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = current_company_id();
        $branchId = current_branch_id();

        $accounts = EmailConfiguration::forCurrentContext()
            ->orderBy('sort_order')
            ->orderByDesc('is_default')
            ->get();

        $companies = \App\Domains\HRMS\Models\Company::where('tenant_id', $tenantId)->get();
        $branches = $companyId 
            ? \App\Domains\HRMS\Models\Branch::where('company_id', $companyId)->get()
            : \App\Domains\HRMS\Models\Branch::where('tenant_id', $tenantId)->get();

        return view('modules.crm.email_settings.index', compact('accounts', 'companies', 'branches', 'tenantId', 'companyId', 'branchId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id'                  => 'nullable|exists:email_configurations,id',
            'name'                => 'required|string|max:100',
            'email_address'       => 'required|email|max:191',
            'from_name'           => 'nullable|string|max:191',
            'host'                => 'required|string|max:191',
            'port'                => 'required|integer',
            'encryption'          => 'required|in:tls,ssl,none',
            'username'            => 'required|string|max:191',
            'password'            => 'required|string',
            'incoming_host'       => 'nullable|string|max:191',
            'incoming_port'       => 'nullable|integer',
            'incoming_encryption' => 'nullable|in:ssl,tls,none',
            'incoming_username'   => 'nullable|string|max:191',
            'incoming_password'   => 'nullable|string',
            'company_id'          => 'nullable|exists:companies,id',
            'branch_id'           => 'nullable|exists:branches,id',
            'is_default'          => 'nullable|boolean',
        ]);

        $data['tenant_id'] = current_tenant_id() ?? 1;
        $data['company_id'] = $request->input('company_id') ?: current_company_id();
        $data['branch_id'] = $request->input('branch_id') ?: current_branch_id();
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            EmailConfiguration::where('tenant_id', $data['tenant_id'])
                ->where('company_id', $data['company_id'])
                ->update(['is_default' => false]);
        }

        if (!empty($data['id'])) {
            $config = EmailConfiguration::findOrFail($data['id']);
            $config->update($data);
            $msg = 'Database Email SMTP Configuration updated successfully!';
        } else {
            EmailConfiguration::create($data);
            $msg = 'Database Email SMTP Configuration saved successfully!';
        }

        return redirect()->route('crm.emailSettings.index')->with('success', $msg);
    }

    public function testConnection(int $id): JsonResponse
    {
        $config = EmailConfiguration::findOrFail($id);

        try {
            $result = $this->emailService->testConnections($config);
            $msg = "✓ SMTP connection succeeded for '{$config->name}' ({$config->email_address}).";
            if ($result['imap']) {
                $msg .= " IMAP connection also authenticated.";
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Connection Failed: " . $e->getMessage(),
            ], 422);
        }
    }

    public function sendTestMail(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'to_email'  => 'required|email',
            'subject'   => 'required|string|max:191',
            'body_html' => 'required|string',
        ]);

        $config = EmailConfiguration::findOrFail($id);

        try {
            $this->emailService->sendEmail([
                'account_id' => $config->id,
                'to'         => $request->input('to_email'),
                'subject'    => $request->input('subject'),
                'body_html'  => nl2br(e($request->input('body_html'))),
            ]);

            return response()->json([
                'success' => true,
                'message' => "✓ Test Email dispatched successfully to {$request->input('to_email')} using Database SMTP '{$config->name}'!",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to Send Email: " . $e->getMessage(),
            ], 422);
        }
    }
}
