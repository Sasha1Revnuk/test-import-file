<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stores_lead_when_only_external_id_is_present(): void
    {
        $lead = Lead::query()->create([
            'external_id' => 'LD-000002',
        ]);

        $this->assertModelExists($lead);
        $this->assertSame('LD-000002', $lead->external_id);
        $this->assertNull($lead->budget_uah);
        $this->assertNull($lead->comment);
        $this->assertNull($lead->created_at);
        $this->assertNull($lead->next_contact_at);
    }

    #[Test]
    public function stores_imported_lead_fields(): void
    {
        $lead = Lead::query()->create([
            'external_id' => 'LD-000001',
            'created_at' => '2025-07-10 05:06:54',
            'first_name' => 'Олександр',
            'last_name' => 'Поліщук',
            'phone' => '380672341057',
            'email' => 'oleksandr.polishchuk693@ukr.net',
            'city' => 'Івано-Франківськ',
            'source' => 'Facebook Ads',
            'utm_campaign' => 'catalog_2026',
            'product' => 'Контекстна реклама',
            'budget_uah' => 23700,
            'status' => 'in_progress',
            'manager' => 'Литвиненко Н.',
            'comment' => null,
            'next_contact_at' => '2025-07-23 05:06:54',
        ]);

        $lead->refresh();

        $this->assertModelExists($lead);
        $this->assertSame('Олександр', $lead->first_name);
        $this->assertSame('Поліщук', $lead->last_name);
        $this->assertSame('380672341057', $lead->phone);
        $this->assertSame(23700, $lead->budget_uah);
        $this->assertNull($lead->comment);
        $this->assertDatabaseHas(Lead::class, [
            'id' => $lead->id,
            'created_at' => '2025-07-10 05:06:54',
            'next_contact_at' => '2025-07-23 05:06:54',
        ]);
    }

    #[Test]
    public function rejects_duplicate_external_id(): void
    {
        Lead::query()->create([
            'external_id' => 'LD-000001',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Lead::query()->create([
            'external_id' => 'LD-000001',
        ]);
    }
}
