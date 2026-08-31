<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Court '.fake()->unique()->numberBetween(1, 40),
            'surface' => fake()->randomElement(['Outdoor acrylic', 'Cushioned acrylic', 'Painted concrete']),
            'description' => null,
            // Typical Pampanga rates: peak evenings cost more than a 9am weekday.
            'rate_peak_centavos' => 40000,      // ₱400/hour
            'rate_offpeak_centavos' => 30000,   // ₱300/hour
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
