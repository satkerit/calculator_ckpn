<?php

namespace Database\Factories;

use App\Models\AssessmentPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentPeriod>
 */
class AssessmentPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period_code' => $this->faker->unique()->numerify('######'),
            'assessment_date' => $this->faker->dateTimeBetween('-1 year')->format('Y-m-d'),
            'status' => 'PENDING',
            'source_version' => '1.0',
            'created_by' => 1,
        ];
    }
}
