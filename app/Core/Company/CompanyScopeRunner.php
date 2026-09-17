<?php

namespace App\Core\Company;

use App\Core\Branch\BranchContext;
use Closure;

/**
 * Runs code across every company and branch of the current tenant — the
 * company/branch global scopes only filter while a company or branch is
 * selected — then puts the selection back. Used for consolidated views.
 */
class CompanyScopeRunner
{
    public function __construct(
        private readonly CompanyContext $companies,
        private readonly BranchContext $branches,
    ) {
    }

    public function acrossCompanies(Closure $callback): mixed
    {
        $company = $this->companies->company();
        $branch = $this->branches->branch();

        $this->companies->clear();
        $this->branches->clear();

        try {
            return $callback();
        } finally {
            $this->companies->set($company);
            $this->branches->set($branch);
        }
    }
}
