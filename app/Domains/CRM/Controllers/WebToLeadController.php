<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WebToLeadController extends Controller
{
    /**
     * Default Form Schema definition.
     */
    public static function getDefaultSchema(): array
    {
        return [
            ['id' => 'contact_person', 'name' => 'contact_person', 'label' => 'Full Name', 'type' => 'text', 'placeholder' => 'e.g. John Doe', 'required' => true, 'is_core' => true, 'width' => 'half'],
            ['id' => 'company_name', 'name' => 'company_name', 'label' => 'Company / Business', 'type' => 'text', 'placeholder' => 'e.g. Acme Corp', 'required' => false, 'is_core' => true, 'width' => 'half'],
            ['id' => 'phone', 'name' => 'phone', 'label' => 'Phone / Mobile', 'type' => 'tel', 'placeholder' => '+91 98765 43210', 'required' => true, 'is_core' => true, 'width' => 'half'],
            ['id' => 'email', 'name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'placeholder' => 'john@example.com', 'required' => true, 'is_core' => true, 'width' => 'half'],
            ['id' => 'city', 'name' => 'city', 'label' => 'City / Location', 'type' => 'text', 'placeholder' => 'e.g. Mumbai, Delhi, Bangalore', 'required' => false, 'is_core' => true, 'width' => 'full'],
            ['id' => 'requirement', 'name' => 'requirement', 'label' => 'Requirement Details / Message', 'type' => 'textarea', 'placeholder' => 'Briefly describe what you are looking for...', 'required' => false, 'is_core' => true, 'width' => 'full'],
        ];
    }

    /**
     * Display the Web-to-Lead Embed & Drag-and-Drop Dynamic Widget Builder in CRM Admin.
     */
    public function index(Request $request): View
    {
        $tenant = tenant();
        $baseUrl = $request->schemeAndHttpHost();
        $tenantIdentifier = $tenant ? ($tenant->slug ?: $tenant->id) : 1;
        $tenantId = $tenant ? $tenant->id : 1;

        // Auto-detect active company & branch from current session context
        $activeCompanyId = company_id() ?? current_company_id() ?? Company::where('tenant_id', $tenantId)->value('id');
        $activeBranchId = branch_id() ?? current_branch_id() ?? Branch::where('tenant_id', $tenantId)->where('company_id', $activeCompanyId)->value('id') ?? Branch::where('tenant_id', $tenantId)->value('id');

        $activeCompany = $activeCompanyId ? Company::find($activeCompanyId) : null;
        $activeBranch = $activeBranchId ? Branch::find($activeBranchId) : null;

        $settings = is_array($tenant?->settings) ? $tenant->settings : [];
        $savedSchema = $settings['web_lead_form_schema'] ?? self::getDefaultSchema();
        $defaultConfig = [
            'title' => 'Get in Touch with Us',
            'subtitle' => 'Fill out the form below and our team will reach out promptly.',
            'btn_text' => 'Submit Enquiry',
            'color' => '#28a745',
            'theme' => 'light',
            'position' => 'bottom-right',
            'redirect_url' => '',
            'company_id' => $activeCompanyId,
            'branch_id' => $activeBranchId,
        ];
        $savedConfig = array_merge($defaultConfig, $settings['web_lead_form_config'] ?? []);

        return view('modules.crm.web_forms.index', [
            'tenant' => $tenant,
            'tenantIdentifier' => $tenantIdentifier,
            'baseUrl' => rtrim($baseUrl, '/'),
            'savedSchema' => $savedSchema,
            'savedConfig' => $savedConfig,
            'activeCompanyId' => $activeCompanyId,
            'activeBranchId' => $activeBranchId,
            'activeCompany' => $activeCompany,
            'activeBranch' => $activeBranch,
        ]);
    }

    /**
     * Save customized Dynamic Form Schema and Configurations for the Tenant.
     */
    public function saveSchema(Request $request): JsonResponse
    {
        $tenant = tenant();
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        $fields = $request->input('fields', []);
        $config = $request->input('config', []);

        if (!is_array($fields) || empty($fields)) {
            $fields = self::getDefaultSchema();
        }

        // Auto-assign active company and branch if not explicitly given
        if (empty($config['company_id'])) {
            $config['company_id'] = company_id() ?? current_company_id() ?? Company::where('tenant_id', $tenant->id)->value('id');
        }
        if (empty($config['branch_id'])) {
            $config['branch_id'] = branch_id() ?? current_branch_id() ?? Branch::where('tenant_id', $tenant->id)->where('company_id', $config['company_id'])->value('id') ?? Branch::where('tenant_id', $tenant->id)->value('id');
        }

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $currentSettings['web_lead_form_schema'] = $fields;
        $currentSettings['web_lead_form_config'] = $config;

        $tenant->update(['settings' => $currentSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Dynamic Form Builder layout saved successfully!',
        ]);
    }

    /**
     * Get public JSON configuration for the tenant form/widget.
     */
    public function getFormConfig(Request $request, string|int $tenant): JsonResponse
    {
        $tenantModel = is_numeric($tenant)
            ? Tenant::find($tenant)
            : Tenant::where('slug', $tenant)->first();

        if (!$tenantModel) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        $settings = is_array($tenantModel->settings) ? $tenantModel->settings : [];
        $savedConfig = $settings['web_lead_form_config'] ?? [];
        $savedSchema = $settings['web_lead_form_schema'] ?? self::getDefaultSchema();

        $activeFields = array_values(array_filter($savedSchema, function ($f) {
            return !isset($f['is_active']) || $f['is_active'] === true || $f['is_active'] === 'true' || $f['is_active'] === 1;
        }));

        $companyId = $savedConfig['company_id'] ?? Company::where('tenant_id', $tenantModel->id)->value('id');
        $branchId = $savedConfig['branch_id'] ?? (Branch::where('tenant_id', $tenantModel->id)->where('company_id', $companyId)->value('id') ?? Branch::where('tenant_id', $tenantModel->id)->value('id'));

        return response()->json([
            'color' => $savedConfig['color'] ?? '#28a745',
            'title' => $savedConfig['title'] ?? 'Get in Touch with Us',
            'subtitle' => $savedConfig['subtitle'] ?? 'Fill out the form below and our sales team will reach out promptly.',
            'btn_text' => $savedConfig['btn_text'] ?? 'Submit Enquiry',
            'theme' => $savedConfig['theme'] ?? 'light',
            'position' => $savedConfig['position'] ?? 'bottom-right',
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'fields_count' => count($activeFields),
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, Origin',
        ]);
    }

    /**
     * Render the standalone public lead capture form (suitable for iframe or direct link).
     */
    public function renderForm(Request $request, string|int $tenant): View|Response
    {
        // Resolve tenant by ID or slug
        $tenantModel = is_numeric($tenant)
            ? Tenant::find($tenant)
            : Tenant::where('slug', $tenant)->first();

        if (!$tenantModel) {
            abort(404, 'Form not found or tenant inactive.');
        }

        $settings = is_array($tenantModel->settings) ? $tenantModel->settings : [];
        $savedSchema = $settings['web_lead_form_schema'] ?? self::getDefaultSchema();
        $savedConfig = $settings['web_lead_form_config'] ?? [];

        // Only include active enabled fields in public rendering
        $activeFields = array_values(array_filter($savedSchema, function ($f) {
            return !isset($f['is_active']) || $f['is_active'] === true || $f['is_active'] === 'true' || $f['is_active'] === 1;
        }));

        $title = $request->query('title', $savedConfig['title'] ?? 'Get in Touch with Us');
        $subtitle = $request->query('subtitle', $savedConfig['subtitle'] ?? 'Fill out the form below and our sales team will reach out promptly.');
        $btnText = $request->query('btn_text', $savedConfig['btn_text'] ?? 'Submit Enquiry');
        $theme = $request->query('theme', $savedConfig['theme'] ?? 'light');
        
        // Color priority: tenant's saved configuration in CRM takes top precedence
        $savedColor = $savedConfig['color'] ?? null;
        $queryColor = $request->query('color');
        if (!empty($savedColor)) {
            $primaryColor = ($queryColor && $queryColor !== '#4f46e5' && $queryColor !== '%234f46e5') ? $queryColor : $savedColor;
        } else {
            $primaryColor = $queryColor ?: '#4f46e5';
        }

        $redirectUrl = $request->query('redirect_url', $savedConfig['redirect_url'] ?? '');
        $source = $request->query('source', 'Website Form');

        // Resolve Company and Branch IDs
        $companyId = $request->query('company_id', $savedConfig['company_id'] ?? null);
        if (!$companyId) {
            $companyId = Company::where('tenant_id', $tenantModel->id)->value('id');
        }

        $branchId = $request->query('branch_id', $savedConfig['branch_id'] ?? null);
        if (!$branchId && $companyId) {
            $branchId = Branch::where('tenant_id', $tenantModel->id)->where('company_id', $companyId)->value('id');
        }
        if (!$branchId) {
            $branchId = Branch::where('tenant_id', $tenantModel->id)->value('id');
        }

        return response()
            ->view('modules.crm.web_forms.lead_form', [
                'tenant' => $tenantModel,
                'title' => $title,
                'subtitle' => $subtitle,
                'btnText' => $btnText,
                'theme' => $theme,
                'primaryColor' => $primaryColor,
                'fields' => $activeFields,
                'redirectUrl' => $redirectUrl,
                'source' => $source,
                'companyId' => $companyId,
                'branchId' => $branchId,
            ])
            ->header('X-Frame-Options', 'ALLOWALL'); // Allow embedding in iframes
    }

    /**
     * Serve the public embed JS widget file dynamically with correct headers.
     */
    public function renderWidgetJs(): Response
    {
        $jsPath = public_path('embed/lead-widget.js');
        if (!file_exists($jsPath)) {
            $jsContent = $this->getDefaultWidgetJs();
        } else {
            $jsContent = file_get_contents($jsPath);
        }

        return response($jsContent, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Handle public Web-to-Lead Form submission (from iframe, JS widget, or direct REST API).
     */
    public function submit(Request $request): JsonResponse|RedirectResponse
    {
        // Add CORS Headers helper
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS, GET',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin',
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json(['status' => 'ok'], 200, $corsHeaders);
        }

        // Anti-spam Honeypot: if hidden field is filled, silently succeed
        if (!empty($request->input('_hp_website'))) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you! Your enquiry has been received.',
            ], 200, $corsHeaders);
        }

        // Resolve Tenant
        $tenantId = $request->input('tenant_id') ?: $request->input('tenant');
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required tenant identifier.',
            ], 422, $corsHeaders);
        }

        $tenant = is_numeric($tenantId)
            ? Tenant::find($tenantId)
            : Tenant::where('slug', $tenantId)->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or suspended tenant account.',
            ], 404, $corsHeaders);
        }

        // Standard core validation
        $validator = Validator::make($request->all(), [
            'contact_person' => ['nullable', 'string', 'max:150'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'requirement' => ['nullable', 'string', 'max:3000'],
            'message' => ['nullable', 'string', 'max:3000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'redirect_url' => ['nullable', 'url', 'max:500'],
            'company_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422, $corsHeaders);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $contactPerson = trim((string) ($request->input('contact_person') ?: $request->input('name')));
        $email = trim((string) $request->input('email'));
        $phone = trim((string) $request->input('phone'));

        if (empty($contactPerson) && empty($email) && empty($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide at least a name, email address, or phone number.',
            ], 422, $corsHeaders);
        }

        $requirement = $request->input('requirement') ?: $request->input('message');
        $source = $request->input('source') ?: 'Website Form';

        // Resolve Company ID and Branch ID
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $savedConfig = $settings['web_lead_form_config'] ?? [];

        $companyId = $request->input('company_id') ?: ($savedConfig['company_id'] ?? null);
        if (!$companyId) {
            $companyId = Company::where('tenant_id', $tenant->id)->value('id');
        }

        $branchId = $request->input('branch_id') ?: ($savedConfig['branch_id'] ?? null);
        if (!$branchId && $companyId) {
            $branchId = Branch::where('tenant_id', $tenant->id)->where('company_id', $companyId)->value('id');
        }
        if (!$branchId) {
            $branchId = Branch::where('tenant_id', $tenant->id)->value('id');
        }

        // Extract Custom Dynamic Fields
        $coreKeys = [
            'tenant_id', 'tenant', 'company_id', 'branch_id', 'source', 'redirect_url', '_token', '_hp_website', 'custom_fields',
            'contact_person', 'name', 'company_name', 'email', 'phone', 'city', 'state', 'address',
            'requirement', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'page_url', 'priority'
        ];

        $customFields = $request->input('custom_fields', []);
        if (!is_array($customFields)) {
            $customFields = [];
        }

        foreach ($request->all() as $key => $val) {
            if (!in_array($key, $coreKeys, true) && !isset($customFields[$key])) {
                if (is_array($val)) {
                    $customFields[$key] = implode(', ', $val);
                } else {
                    $customFields[$key] = $val;
                }
            }
        }

        // Create the Lead with tenant_id, company_id, and branch_id
        $lead = new Lead();
        $lead->tenant_id = $tenant->id;
        $lead->company_id = $companyId ? (int)$companyId : null;
        $lead->branch_id = $branchId ? (int)$branchId : null;

        $lead->contact_person = $contactPerson ?: 'Website Visitor';
        $lead->company_name = $request->input('company_name');
        $lead->email = $email ?: null;
        $lead->phone = $phone ?: null;
        $lead->requirement = $requirement;
        $lead->city = $request->input('city');
        $lead->state = $request->input('state');
        $lead->address = $request->input('address');
        $lead->source = $source;
        $lead->custom_fields = !empty($customFields) ? $customFields : null;

        // Capture UTM parameters
        $lead->utm_source = $request->input('utm_source');
        $lead->utm_medium = $request->input('utm_medium');
        $lead->utm_campaign = $request->input('utm_campaign');
        $lead->utm_term = $request->input('utm_term');
        $lead->utm_content = $request->input('utm_content');

        $lead->status = 'New';
        $lead->priority = $request->input('priority', 'Medium');
        $lead->call_date = now();
        $lead->save();

        // Build Custom Fields Notes summary for Timeline
        $customDetailsSummary = '';
        if (!empty($customFields)) {
            $formatted = [];
            foreach ($customFields as $k => $v) {
                $label = ucwords(str_replace('_', ' ', $k));
                $formatted[] = "{$label}: " . (is_array($v) ? implode(', ', $v) : $v);
            }
            $customDetailsSummary = ' | Details: ' . implode(' • ', $formatted);
        }

        // Log History
        try {
            LeadHistory::create([
                'tenant_id' => $tenant->id,
                'company_id' => $lead->company_id,
                'branch_id' => $lead->branch_id,
                'lead_id' => $lead->id,
                'user_id' => null,
                'event_type' => 'created',
                'new_value' => 'New',
                'notes' => 'Lead received via Dynamic Web Form (Page: ' . ($request->input('page_url') ?: 'Website Form') . ')' . $customDetailsSummary,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log LeadHistory for web-to-lead: ' . $e->getMessage());
        }

        $successMessage = 'Thank you! Your enquiry has been submitted successfully. Our team will contact you shortly.';

        // Handle Redirect URL if requested
        $redirectUrl = $request->input('redirect_url');
        if ($redirectUrl && !$request->expectsJson() && !$request->ajax()) {
            $separator = str_contains($redirectUrl, '?') ? '&' : '?';
            return redirect()->away($redirectUrl . $separator . 'lead_status=success&lead_number=' . urlencode($lead->lead_number ?? ''));
        }

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'lead_id' => $lead->id,
            'lead_number' => $lead->lead_number,
            'custom_fields' => $customFields,
        ], 201, $corsHeaders);
    }

    /**
     * Fallback widget JS content generator
     */
    private function getDefaultWidgetJs(): string
    {
        return <<<'JS'
(function() {
    // Lead Widget Loader Logic
})();
JS;
    }
}
