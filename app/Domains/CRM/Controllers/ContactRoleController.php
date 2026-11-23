<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContactRole;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactRoleController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $tenantId = tenant_id() ?? $request->user()?->tenant_id ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $slug = Str::slug($validated['name'], '_');
        if ($slug === '') {
            return back()->with('error', 'Please enter a valid contact role name.');
        }

        $exists = ContactRole::query()
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            return redirect()->route('crm.settings.index', ['tab' => 'contact-roles'])
                ->with('error', "Contact role '{$validated['name']}' already exists.");
        }

        ContactRole::create([
            'tenant_id' => $tenantId,
            'name' => trim($validated['name']),
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
        ]);

        return redirect()->route('crm.settings.index', ['tab' => 'contact-roles'])
            ->with('success', "Contact role '{$validated['name']}' added.");
    }

    public function destroy(Request $request, ContactRole $contactRole): RedirectResponse
    {
        $this->authorizeManage($request);
        $tenantId = tenant_id() ?? $request->user()?->tenant_id ?? 1;

        if ($contactRole->is_system || (int) $contactRole->tenant_id !== (int) $tenantId) {
            return redirect()->route('crm.settings.index', ['tab' => 'contact-roles'])
                ->with('error', 'System default contact roles cannot be deleted.');
        }

        $contactRole->delete();

        return redirect()->route('crm.settings.index', ['tab' => 'contact-roles'])
            ->with('success', 'Contact role deleted.');
    }

    private function authorizeManage(Request $request): void
    {
        $allowed = $this->access->allows($request->user(), 'crm.contact_roles.manage', [
            'tenant_id' => tenant_id() ?? $request->user()?->tenant_id,
        ]);

        abort_unless($allowed, 403);
    }
}
