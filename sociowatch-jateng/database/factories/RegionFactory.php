<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Region> */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'type' => fake()->randomElement(['kabupaten', 'kota']),
            'latitude' => fake()->latitude(-8.2, -6.5),
            'longitude' => fake()->longitude(108.5, 111.5),
            'geojson_key' => strtolower(str_replace(' ', '-', $name)) . '-' . fake()->unique()->numerify('###'),
        ];
    }
}
