<?php

namespace App\Domains\Platform\Support;

use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The /admin console sidebar for one user: Routes/admin-menu.php minus items
 * whose route is missing or whose permission the user lacks. Kept apart from
 * Core\Navigation\MenuBuilder, which filters by the current tenant's plan —
 * the console has no current tenant.
 */
class AdminMenu
{
    public function __construct(private readonly AccessService $access)
    {
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, icon: string, url: string, active: bool}>}>
     */
    public function build(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $current = Route::currentRouteName() ?? '';
        $sections = [];

        foreach (require app_path('Domains/Platform/Routes/admin-menu.php') as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (! Route::has($item['route']) || ! $this->access->allows($user, $item['permission'])) {
                    continue;
                }

                // platform.tenants.index is active on every platform.tenants.* page.
                $prefix = Str::beforeLast($item['route'], '.');

                $items[] = [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'url' => route($item['route']),
                    'active' => $current === $item['route'] || ($prefix !== 'admin' && Str::startsWith($current, $prefix.'.')),
                ];
            }

            if ($items !== []) {
                $sections[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $sections;
    }
}
