<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'headers' => ['content-type' => 'application/json', 'accept' => '*/*'],
            'query_params' => null,
            'payload' => null,
        ];
    }
}
