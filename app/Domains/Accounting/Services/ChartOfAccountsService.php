<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ChartOfAccountsService
{
    public function __construct(
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    public function list(array $filters = []): Collection
    {
        return $this->accounts->getAll($filters);
    }

    public function active(): Collection
    {
        return $this->accounts->getActive();
    }

    public function ofType(string $type): Collection
    {
        return $this->accounts->getByType($type);
    }

    public function find(int $id): ?ChartOfAccount
    {
        return $this->accounts->find($id);
    }

    public function create(array $data): ChartOfAccount
    {
        $tenantId = $data['tenant_id'] ?? tenant_id();

        if ($this->accounts->findByCode($data['code'], $tenantId) !== null) {
            throw new InvalidArgumentException("Account code '{$data['code']}' already exists.");
        }

        return $this->accounts->create($data);
    }

    public function update(int $id, array $data): ChartOfAccount
    {
        $account = $this->accounts->find($id);

        if ($account === null) {
            throw new InvalidArgumentException('Chart of account not found.');
        }

        if (isset($data['code']) && $this->accounts->findByCode($data['code'], $account->tenant_id, $id) !== null) {
            throw new InvalidArgumentException("Account code '{$data['code']}' already exists.");
        }

        return $this->accounts->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $account = $this->accounts->find($id);

        if ($account === null) {
            throw new InvalidArgumentException('Chart of account not found.');
        }

        if ($account->is_system) {
            throw new InvalidArgumentException('System-seeded accounts cannot be deleted.');
        }

        if ($account->journalEntries()->exists()) {
            throw new InvalidArgumentException('Account has posted journal entries and cannot be deleted.');
        }

        return $this->accounts->delete($id);
    }

    /**
     * Flat list annotated with a `depth` key, ordered so children follow their
     * parent — the shape Blade views need to render an indented COA tree
     * without recursive partials.
     *
     * @return array<int, array{account: ChartOfAccount, depth: int}>
     */
    public function tree(): array
    {
        $accounts = $this->accounts->getAll();
        $byParent = $accounts->groupBy('parent_id');

        $flatten = function ($parentId, int $depth) use (&$flatten, $byParent): array {
            $rows = [];

            foreach ($byParent->get($parentId, collect()) as $account) {
                $rows[] = ['account' => $account, 'depth' => $depth];
                $rows = array_merge($rows, $flatten($account->id, $depth + 1));
            }

            return $rows;
        };

        return $flatten(null, 0);
    }
}
