<?php

namespace Tests\Feature;

use App\Models\Lead;
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
        $response->assertSee('External id');
        $response->assertSee('Next contact at');
    }

    #[Test]
    public function shows_saved_lead_in_the_table(): void
    {
        Lead::query()->create([
            'external_id' => 'LD-000002',
            'created_at' => '2026-03-25 10:37:26',
            'first_name' => 'Микола',
            'last_name' => 'Мороз',
            'phone' => '380671166941',
            'email' => 'mykola.moroz234@outlook.com',
            'city' => 'Одеса',
            'source' => 'Instagram',
            'utm_campaign' => 'organic',
            'product' => 'Корпоративний сайт',
            'budget_uah' => null,
            'status' => 'in_progress',
            'manager' => 'Пономаренко А.',
            'comment' => 'Порівнює з конкурентами',
            'next_contact_at' => '2026-03-29 10:37:26',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('LD-000002');
        $response->assertSee('Микола');
        $response->assertSee('Мороз');
        $response->assertSee('380671166941');
        $response->assertSee('mykola.moroz234@outlook.com');
        $response->assertSee('Одеса');
        $response->assertSee('Instagram');
        $response->assertSee('organic');
        $response->assertSee('Корпоративний сайт');
        $response->assertSee('in_progress');
        $response->assertSee('Пономаренко А.');
        $response->assertSee('Порівнює з конкурентами');
        $response->assertSee('2026-03-25 10:37:26');
        $response->assertSee('2026-03-29 10:37:26');
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
