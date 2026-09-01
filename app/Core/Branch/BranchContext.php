<?php

namespace App\Core\Branch;

use App\Domains\HRMS\Models\Branch;

class BranchContext
{
    private ?Branch $branch = null;

    public function set(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function id(): ?int
    {
        return $this->branch?->id;
    }

    public function hasBranch(): bool
    {
        return $this->branch !== null;
    }

    public function clear(): void
    {
        $this->branch = null;
    }
}
