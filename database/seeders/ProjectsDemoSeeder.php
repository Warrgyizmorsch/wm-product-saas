<?php

namespace Database\Seeders;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use App\Domains\Projects\Seeders\ProjectsDemoGenerator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectsDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(array $options = []): array
    {
        $tenantParam = $options['tenant'] ?? null;
        $context = TenantIsolationContext::resolve($tenantParam);

        $generator = new ProjectsDemoGenerator();

        return $generator->execute($context, $options, $this->command ? $this->command->getOutput() : null);
    }
}
