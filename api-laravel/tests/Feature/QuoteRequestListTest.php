<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteRequestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_supports_search_filters_and_pagination(): void
    {
        $staff = User::factory()->create();
        Sanctum::actingAs($staff);

        QuoteRequest::factory()->count(3)->create(['service_type' => ServiceType::Consulting]);
        QuoteRequest::factory()->assignedTo($staff)->status(QuoteStatus::InReview)->create(['client_name' => 'Zara Findme']);
        QuoteRequest::factory()->assignedTo($staff)->status(QuoteStatus::Quoted)->create(['company' => 'Findme Corp']);

        $this->getJson('/api/quote-requests?search=findme')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/quote-requests?status=in_review&assigned_to='.$staff->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'Zara Findme');

        $this->getJson('/api/quote-requests?assigned_to=unassigned')
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/quote-requests?per_page=2&page=2&sort=client_name')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/quote-requests?status=bogus&sort=password&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'sort', 'per_page']);
    }

    public function test_list_does_not_run_n_plus_one_queries(): void
    {
        $staff = User::factory()->count(5)->create();
        Sanctum::actingAs($staff->first());

        foreach ($staff as $user) {
            QuoteRequest::factory()->count(4)->assignedTo($user)->create();
        }

        DB::enableQueryLog();
        $this->getJson('/api/quote-requests?per_page=20')->assertOk()->assertJsonCount(20, 'data');

        // Token lookup + token touch + user + count + page + eager-loaded assignees.
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
    }
}
