<?php

namespace App\Core\Navigation;

use App\Http\Middleware\EnsureTenantModuleAccess;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Turns the registered menu entries into the sidebar a given user may see.
 *
 * An entry is kept only when every rule passes:
 *  1. its module is in the tenant's plan (tenant_allowed_modules());
 *  2. the user's roles grant something in that module (AccessService::allowedModulesFor());
 *  3. the user holds its `permission` (any one of a list), if it has one;
 *  4. its `when` callback returns true, if it has one;
 *  5. its route exists — or, for a placeholder, placeholders are switched on.
 * A group whose children are all removed is removed too, and so is an empty section.
 *
 * The module of an entry is its own `module`, else its parent's, else the
 * first segment of its route name when that is a plan-gated module.
 */
class MenuBuilder
{
    private ?User $user = null;

    /** @var list<string>|null */
    private ?array $planModules = null;

    /** @var list<string>|null */
    private ?array $roleModules = null;

    /** @var array<string, bool> */
    private array $permissionResults = [];

    public function __construct(
        private readonly MenuRegistry $registry,
        private readonly AccessService $access,
    ) {
    }

    /**
     * @return list<array{key: string, label: string, slug: string, items: list<array>}>
     */
    public function build(?User $user, ?string $currentRoute = null): array
    {
        $this->user = $user;
        $this->planModules = tenant_allowed_modules();
        $this->roleModules = $user ? $this->access->allowedModulesFor($user) : null;
        $this->permissionResults = [];

        $sections = [];
        foreach (config('navigation.sections', []) as $key => $section) {
            $label = $this->label($section);
            $sections[$key] = ['key' => $key, 'label' => $label, 'slug' => Str::slug($label), 'items' => []];
        }

        $entries = $this->registry->entries();
        usort($entries, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));

        foreach ($entries as $entry) {
            $key = $entry['section'] ?? null;

            if ($key !== null && isset($sections[$key]) && ($item = $this->resolve($entry, null)) !== null) {
                $sections[$key]['items'][] = $item;
            }
        }

        $sections = array_values(array_filter($sections, fn (array $section) => $section['items'] !== []));
        $this->markActive($sections, $currentRoute);

        return $sections;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{label: string, icon: string, route: ?string, url: string, active: bool, placeholder: bool, active_routes: list<string>, children: list<array>}|null
     */
    private function resolve(array $entry, ?string $parentModule): ?array
    {
        $module = $entry['module'] ?? $parentModule ?? $this->moduleOf($entry['route'] ?? null);

        if (! $this->moduleAllowed($module)
            || ! $this->permitted($entry['permission'] ?? null)
            || (isset($entry['when']) && ! ($entry['when'])($this->user, tenant()))) {
            return null;
        }

        $item = [
            'label' => $this->label($entry),
            'icon' => $entry['icon'] ?? 'feather-circle',
            'route' => null,
            'url' => '#',
            'active' => false,
            'placeholder' => false,
            'active_routes' => $entry['active_routes'] ?? [],
            'children' => [],
        ];

        if (isset($entry['children'])) {
            $item['children'] = array_values(array_filter(array_map(
                fn (array $child) => $this->resolve($child, $entry['module'] ?? $parentModule),
                $entry['children'],
            )));

            return $item['children'] === [] ? null : $item;
        }

        if (isset($entry['route'])) {
            if (! Route::has($entry['route'])) {
                return null;
            }

            return ['route' => $entry['route'], 'url' => route($entry['route'], $entry['parameters'] ?? [])] + $item;
        }

        return config('navigation.show_placeholders') ? ['placeholder' => true] + $item : null;
    }

    private function moduleOf(?string $routeName): ?string
    {
        $prefix = $routeName === null ? null : Str::before($routeName, '.');

        return in_array($prefix, EnsureTenantModuleAccess::GATED_MODULES, true) ? $prefix : null;
    }

    private function moduleAllowed(?string $module): bool
    {
        if ($module === null) {
            return true;
        }

        return ($this->planModules === null || in_array($module, $this->planModules, true))
            && ($this->roleModules === null || in_array($module, $this->roleModules, true));
    }

    /**
     * @param string|list<string>|null $permissions any one is enough
     */
    private function permitted(string|array|null $permissions): bool
    {
        if ($permissions === null) {
            return true;
        }

        if ($this->user === null) {
            return false;
        }

        foreach ((array) $permissions as $permission) {
            // Platform permissions are checked without a tenant: tenant owners hold every
            // permission at tenant scope, which must not open platform-wide screens.
            //
            // For every other permission, the context describes "this user's own
            // records" (owner/branch/department/company = the user's own id/values)
            // rather than any specific record — a sidebar link has no single record
            // to check against, so it should show whenever the user could see *at
            // least their own* data under the grant. Without this, a grant held only
            // at SCOPE_OWN/SCOPE_BRANCH/etc. (e.g. sales_executive's crm.leads.view)
            // would never match here, since scopeMatches() fails closed when the
            // relevant context key is absent — hiding the link from the very role
            // the grant was written for.
            $this->permissionResults[$permission] ??= $this->access->allows(
                $this->user,
                $permission,
                str_starts_with($permission, 'platform.') ? [] : [
                    'tenant_id' => $this->user->tenant_id,
                    'owner_id' => $this->user->id,
                    'branch_id' => $this->user->branch_id,
                    'department_id' => $this->user->department_id,
                    'company_id' => $this->user->company_id,
                ],
            );

            if ($this->permissionResults[$permission]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{label?: string, default?: string} $entry
     */
    private function label(array $entry): string
    {
        $label = (string) ($entry['label'] ?? '');
        $translated = __($label);

        return is_string($translated) && $translated !== $label ? $translated : ($entry['default'] ?? $label);
    }

    /**
     * Highlights the entry for the current page: exact route names first (every
     * match), otherwise the first entry of the same resource — so
     * crm.leads.index stays highlighted on crm.leads.show.
     */
    private function markActive(array &$sections, ?string $currentRoute): void
    {
        if ($currentRoute === null) {
            return;
        }

        $exact = fn (array $item) => $item['route'] === $currentRoute || in_array($currentRoute, $item['active_routes'], true);
        $sameResource = fn (array $item) => $item['route'] !== null
            && substr_count($item['route'], '.') >= 2
            && str_starts_with($currentRoute, Str::beforeLast($item['route'], '.').'.');

        foreach ([[$exact, false], [$sameResource, true]] as [$matches, $firstOnly]) {
            $found = false;

            foreach ($sections as &$section) {
                foreach ($section['items'] as &$item) {
                    if ($item['children'] === [] && $matches($item)) {
                        $item['active'] = $found = true;
                    }

                    foreach ($item['children'] as &$child) {
                        if ($matches($child)) {
                            $child['active'] = $item['active'] = $found = true;
                        }

                        if ($found && $firstOnly) {
                            return;
                        }
                    }
                    unset($child);

                    if ($found && $firstOnly) {
                        return;
                    }
                }
                unset($item);
            }
            unset($section);

            if ($found) {
                return;
            }
        }
    }
}
