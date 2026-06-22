<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_no'     => 'CON-' . str_pad($this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'annual_usage'    => $this->faker->randomFloat(2, 500, 500_000),
            'contract_value'  => $this->faker->randomFloat(2, 1_000, 250_000),
            'contract_length' => $this->faker->randomElement([12, 24, 36, 48, 60]),
            'risk_score'      => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
