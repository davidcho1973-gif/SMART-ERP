<?php

namespace Tests\Feature;

use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The root route renders the workforce app (login screen for guests).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(WorkforceSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
