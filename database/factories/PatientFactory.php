<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => function () {
                return User::factory()->create(['is_admin' => 19])->id;
            },
            'file_no' => 'PAT-' . $this->faker->unique()->numberBetween(10000, 99999),
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'phone_no' => $this->faker->phoneNumber(),
        ];
    }
}

