<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingRouteTest extends TestCase
{
    /**
     * Test if the testing page returns a successful response.
     */
    public function test_testing_page_returns_successful_response(): void
    {
        $response = $this->get('/ui-elements');

        $response->assertStatus(200);
    }
}
