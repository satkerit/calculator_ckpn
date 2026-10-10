<?php

namespace Database\Factories;

use App\Models\Financing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Financing>
 */
class FinancingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_number' => 'LN-' . substr($this->faker->unique()->uuid(), 0, 8),
            'cif_number' => $this->faker->unique()->numerify('##########'),
            'name' => $this->faker->company(),
            'tglwo' => $this->faker->optional(0.2)->dateTimeBetween('-3 years')->format('Y-m-d'),
            'usage_code' => $this->faker->randomElement(['MODAL', 'INVESTASI', 'KONSUMSI']),
            'active_flag' => true,
        ];
    }
}
