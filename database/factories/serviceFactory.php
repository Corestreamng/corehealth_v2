<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class serviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'service' => 'treatment',
            'patient_id'=>1
        ];
    }
}
