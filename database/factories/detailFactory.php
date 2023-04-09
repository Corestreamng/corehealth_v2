<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class detailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

            return [
                'service_rendered' => 'lad test',
                'price'=>'800',
                'patient_id'=>1
            ];

    }
}
