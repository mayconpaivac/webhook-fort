<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
