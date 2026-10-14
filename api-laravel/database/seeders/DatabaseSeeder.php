<?php

namespace Database\Seeders;

use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed one admin, two staff users and 200 quote requests.
     * All seeded users share the password "password" (development only).
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@comtanix.test',
        ]);

        $staff = collect([
            User::factory()->create(['name' => 'Sara Staff', 'email' => 'staff1@comtanix.test']),
            User::factory()->create(['name' => 'Omar Staff', 'email' => 'staff2@comtanix.test']),
        ]);

        // 40 brand-new, unassigned requests waiting for triage.
        QuoteRequest::factory()->count(40)->create();

        // 160 requests spread across staff and statuses.
        foreach (range(1, 160) as $i) {
            QuoteRequest::factory()
                ->assignedTo($staff->random())
                ->status(fake()->randomElement(QuoteStatus::cases()))
                ->create();
        }
    }
}
