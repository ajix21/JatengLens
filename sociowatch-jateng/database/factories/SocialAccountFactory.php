<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialAccount> */
class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'platform' => 'instagram',
            'username' => $username,
            'display_name' => fake()->name(),
            'profile_url' => 'https://instagram.com/' . $username,
            'followers_count' => fake()->numberBetween(0, 100000),
            'following_count' => fake()->numberBetween(0, 1000),
            'post_count' => 0,
            'category_id' => Category::factory(),
            'region_id' => Region::factory(),
            'is_active' => true,
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn () => [
            'platform' => 'youtube',
            'username' => 'JawaPosTV',
            'profile_url' => 'https://youtube.com/@JawaPosTV',
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn () => [
            'platform' => 'instagram',
            'username' => 'nu_jateng',
            'profile_url' => 'https://instagram.com/nu_jateng',
        ]);
    }
}
