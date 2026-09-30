<?php

namespace Tests\Unit\Services\Lead;

use App\Models\Lead;
use App\Services\Lead\Contracts\LeadServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LeadServiceInterface
    {
        return $this->app->make(LeadServiceInterface::class);
    }

    #[Test]
    public function delete_removes_the_lead(): void
    {
        $lead = Lead::factory()->create();

        $deleted = $this->service()->delete($lead);

        $this->assertTrue($deleted);
        $this->assertModelMissing($lead);
    }

    #[Test]
    public function delete_all_removes_every_lead(): void
    {
        Lead::factory()->count(3)->create();

        $deleted = $this->service()->deleteAll();

        $this->assertSame(3, $deleted);
        $this->assertDatabaseCount(Lead::class, 0);
    }

    #[Test]
    public function delete_all_returns_zero_when_table_is_empty(): void
    {
        $deleted = $this->service()->deleteAll();

        $this->assertSame(0, $deleted);
        $this->assertDatabaseCount(Lead::class, 0);
    }
}
