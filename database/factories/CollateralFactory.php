<?php

namespace Database\Factories;

use App\Models\Collateral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collateral>
 */
class CollateralFactory extends Factory
{
    public function definition(): array
    {
        return [
            'loan_number' => 'LN-' . substr($this->faker->unique()->uuid(), 0, 8),
            'collateral_code' => $this->faker->unique()->numerify('COL-######'),
            'collateral_order' => $this->faker->numberBetween(1, 3),
            'collateral_type' => $this->faker->randomElement(['TANAH', 'KENDARAAN', 'PIUTANG', 'LAINNYA']),
            'liquidity_value' => $this->faker->numberBetween(100000000, 500000000),
            'estimated_value' => $this->faker->numberBetween(100000000, 500000000),
            'valuation_date' => $this->faker->dateTimeBetween('-6 months')->format('Y-m-d'),
            'active_flag' => true,
        ];
    }
}
