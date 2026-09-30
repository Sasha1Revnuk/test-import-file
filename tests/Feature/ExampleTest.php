<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('fi-ta', false);
    }

    #[Test]
    public function does_not_keep_authentication_tables(): void
    {
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }
}
