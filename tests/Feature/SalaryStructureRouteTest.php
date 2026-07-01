<?php

namespace Tests\Feature;

use Tests\TestCase;

class SalaryStructureRouteTest extends TestCase
{
    public function test_salary_structure_page_is_accessible(): void
    {
        $this->withoutMiddleware();

        $response = $this->get('/hrms/salary-structure');

        $response->assertStatus(200);
    }
}
