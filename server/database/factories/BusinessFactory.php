<?php

namespace Database\Factories;

use App\Domain\Tenant\Models\Business;
use App\Domain\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'owner_id' => User::factory(),
            'is_active' => true,
        ];
    }
}
