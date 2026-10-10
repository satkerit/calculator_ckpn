<?php

namespace Database\Factories;

use App\Models\Snapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Snapshot>
 */
class SnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'loan_number' => 'LN-' . substr($this->faker->unique()->uuid(), 0, 8),
            'product_code' => $this->faker->randomElement(['MURA', 'MJA', 'MUSY', 'IMBT']),
            'office_code' => $this->faker->randomElement(['KC001', 'KC002', 'KPU']),
            'effective_date' => $this->faker->dateTimeBetween('-2 years')->format('Y-m-d'),
            'expired_date' => $this->faker->dateTimeBetween('+1 year', '+5 years')->format('Y-m-d'),
            'outstanding_balance' => $this->faker->numberBetween(1000000, 1000000000),
            'principal_arrears' => $this->faker->numberBetween(0, 50000000),
            'financing_contract_code' => $this->faker->unique()->numerify('FC-######'),
            'active_flag' => true,
            'write_off_flag' => false,
            'ppka_value' => 0,
            'periode_snapshot' => date('Ym'),
        ];
    }

    public function withHighEad(): static
    {
        return $this->state(fn (array $attributes) => [
            'outstanding_balance' => 600000000, // > 500M threshold
        ]);
    }

    public function writeOff(): static
    {
        return $this->state(fn (array $attributes) => [
            'write_off_flag' => true,
            'ppka_value' => 100000000,
        ]);
    }
}
