<?php

namespace Database\Factories;

use App\Enums\BudgetRange;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteRequest>
 */
class QuoteRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-90 days');

        return [
            'client_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company' => fake()->optional(0.8)->company(),
            'service_type' => fake()->randomElement(ServiceType::cases()),
            'budget_range' => fake()->randomElement(BudgetRange::cases()),
            'message' => fake()->paragraph(3),
            'status' => QuoteStatus::New,
            'assigned_to' => null,
            'submitted_ip' => fake()->ipv4(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    public function status(QuoteStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => ['assigned_to' => $user->id]);
    }
}
