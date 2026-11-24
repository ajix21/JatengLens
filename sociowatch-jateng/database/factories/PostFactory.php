<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'platform' => fn (array $attrs) => SocialAccount::find($attrs['social_account_id'])->platform,
            'post_url' => fake()->unique()->url(),
            'content' => fake()->sentence(),
            'media_type' => 'text',
            'likes_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
            'views_count' => 0,
            'posted_at' => fake()->dateTimeBetween('-30 days'),
            'is_flagged' => false,
            'sentiment' => 'netral',
            'sentiment_manual' => false,
        ];
    }
}
