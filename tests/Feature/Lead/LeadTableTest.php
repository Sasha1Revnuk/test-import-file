<?php

namespace Tests\Feature\Lead;

use App\Livewire\Home;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadTableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function leads_page_shows_clear_table_button(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('import.clear_table'), false);
    }

    #[Test]
    public function clear_leads_action_deletes_all_leads(): void
    {
        Lead::factory()->count(2)->create();

        Livewire::test(Home::class)
            ->callAction('clearLeads')
            ->assertHasNoActionErrors()
            ->assertNotified(__('import.flash.cleared', ['count' => 2]));

        $this->assertDatabaseCount(Lead::class, 0);
    }

    #[Test]
    public function delete_table_action_removes_a_single_lead(): void
    {
        $lead = Lead::factory()->create([
            'external_id' => 'LD-DELETE-ME',
        ]);
        $other = Lead::factory()->create([
            'external_id' => 'LD-KEEP',
        ]);

        Livewire::test(Home::class)
            ->callTableAction('delete', $lead)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($lead);
        $this->assertModelExists($other);
    }

    #[Test]
    public function table_search_filters_leads_by_external_id(): void
    {
        Lead::factory()->create([
            'external_id' => 'LD-SEARCHABLE',
            'first_name' => 'Visible',
        ]);
        Lead::factory()->create([
            'external_id' => 'LD-OTHER',
            'first_name' => 'Hidden',
        ]);

        Livewire::test(Home::class)
            ->searchTable('LD-SEARCHABLE')
            ->assertCanSeeTableRecords(Lead::query()->where('external_id', 'LD-SEARCHABLE')->get())
            ->assertCanNotSeeTableRecords(Lead::query()->where('external_id', 'LD-OTHER')->get());
    }
}
