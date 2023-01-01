<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AdminFactory extends Factory
{
    public function definition()
    {
        return [

            'is_admin' => true,
            'surname' => $this->faker->lastName(),
            'firstname' => $this->faker->firstName(),
            'othername' => $this->faker->firstName(),
            'assignRole',
            'assignPermission',
            'status' => 'active',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        ];
    }
}
