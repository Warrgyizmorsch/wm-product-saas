<?php

namespace App\Http\Middleware;

use App\Core\Branch\BranchContext;
use App\Core\Branch\BranchResolver;
use App\Support\Branching;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranch
{
    public function __construct(
        private readonly BranchResolver $branches,
        private readonly BranchContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $companyId = company_id();

        $branch = $companyId !== null ? $this->branches->resolve($request, $companyId) : null;

        $this->context->set($branch);
        app(Branching::class)->set($branch);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
            app(Branching::class)->clear();
        }
    }
}
