<?php

namespace App\Core\Navigation;

/**
 * Collects sidebar entries from every module's Routes/menu.php — discovered the
 * same way routes/web.php discovers module route files, so a new module adds
 * its menu by adding one file.
 *
 * Each file returns a list of top-level entries:
 *
 *     ['section' => 'finance', 'order' => 10, 'module' => 'accounting',
 *      'label' => 'Accounting', 'icon' => 'feather-credit-card', 'children' => [
 *          ['label' => 'Dashboard', 'route' => 'accounting.dashboard', 'permission' => 'accounting.reports.view'],
 *      ]]
 *
 * Supported keys: section, order, label (text or translation key), default
 * (fallback label), icon, route, parameters, module, permission (string or
 * any-of list), when (fn (?User, ?Tenant): bool), active_routes (extra route
 * names that highlight the entry), children. An entry with neither route nor
 * children is a placeholder for a screen that doesn't exist yet.
 */
class MenuRegistry
{
    /** @var list<array<string, mixed>>|null */
    private ?array $entries = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        if ($this->entries === null) {
            $this->entries = [];

            foreach (glob(str_replace('/', DIRECTORY_SEPARATOR, app_path('Domains/*/Routes/menu.php'))) ?: [] as $file) {
                foreach (require $file as $entry) {
                    $this->entries[] = $entry;
                }
            }
        }

        return $this->entries;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function add(array $entry): void
    {
        $this->entries();
        $this->entries[] = $entry;
    }
}
