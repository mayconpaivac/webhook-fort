<?php

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// --- WebhookController ---

it('redirects guests from webhooks index', function () {
    $this->get('/webhooks')->assertRedirect('/login');
});

it('lists user webhooks', function () {
    $webhooks = Webhook::factory(3)->for($this->user)->create();
    Webhook::factory()->create(); // another user's webhook

    $this->actingAs($this->user)
        ->get('/webhooks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('webhooks/Index')
            ->has('webhooks', 3)
        );
});

it('creates a webhook with slug', function () {
    $this->actingAs($this->user)
        ->post('/webhooks', ['name' => 'My Webhook'])
        ->assertRedirect('/webhooks');

    $this->assertDatabaseHas('webhooks', [
        'user_id' => $this->user->id,
        'name' => 'My Webhook',
        'slug' => 'my-webhook',
    ]);
});

it('generates unique slug on conflict', function () {
    Webhook::factory()->for($this->user)->create(['slug' => 'test']);

    $this->actingAs($this->user)
        ->post('/webhooks', ['name' => 'Test'])
        ->assertRedirect('/webhooks');

    $this->assertDatabaseHas('webhooks', ['slug' => 'test-1']);
});

it('shows webhook detail to owner', function () {
    $webhook = Webhook::factory()->for($this->user)->create();
    WebhookLog::factory(5)->for($webhook)->create();

    $this->actingAs($this->user)
        ->get("/webhooks/{$webhook->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('webhooks/Show')
            ->has('logs.data', 5)
        );
});

it('forbids show for non-owner', function () {
    $other = User::factory()->create();
    $webhook = Webhook::factory()->for($other)->create();

    $this->actingAs($this->user)
        ->get("/webhooks/{$webhook->slug}")
        ->assertNotFound();
});

it('deletes webhook', function () {
    $webhook = Webhook::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->delete("/webhooks/{$webhook->slug}")
        ->assertRedirect('/webhooks');

    $this->assertModelMissing($webhook);
});

it('forbids delete for non-owner', function () {
    $webhook = Webhook::factory()->for(User::factory())->create();

    $this->actingAs($this->user)
        ->delete("/webhooks/{$webhook->slug}")
        ->assertNotFound();
});

// --- WebhookReceiverController ---

it('receives GET request and saves log', function () {
    $webhook = Webhook::factory()->create();

    $this->get("/w/{$webhook->slug}")
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('webhook_logs', [
        'webhook_id' => $webhook->id,
        'method' => 'GET',
    ]);
});

it('receives POST with JSON payload and saves log', function () {
    $webhook = Webhook::factory()->create();

    $this->postJson("/w/{$webhook->slug}", ['event' => 'order.created'])
        ->assertOk();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->method)->toBe('POST')
        ->and($log->payload)->toContain('order.created');
});

it('returns 404 for unknown slug', function () {
    $this->post('/w/nonexistent')->assertNotFound();
});

it('cascades delete logs when webhook deleted', function () {
    $webhook = Webhook::factory()->create();
    WebhookLog::factory(3)->for($webhook)->create();

    $webhook->delete();

    expect(WebhookLog::where('webhook_id', $webhook->id)->count())->toBe(0);
});

// --- read_at ---

it('marks a log as read', function () {
    $webhook = Webhook::factory()->for($this->user)->create();
    $log = WebhookLog::factory()->for($webhook)->create(['read_at' => null]);

    $this->actingAs($this->user)
        ->patch("/webhooks/{$webhook->slug}/logs/{$log->sqid}/read")
        ->assertRedirect();

    expect($log->fresh()->read_at)->not->toBeNull();
});

it('forbids marking log as read for non-owner', function () {
    $webhook = Webhook::factory()->for(User::factory())->create();
    $log = WebhookLog::factory()->for($webhook)->create();

    $this->actingAs($this->user)
        ->patch("/webhooks/{$webhook->slug}/logs/{$log->sqid}/read")
        ->assertNotFound();
});

// --- destroyLogs ---

it('deletes all logs for a webhook', function () {
    $webhook = Webhook::factory()->for($this->user)->create();
    WebhookLog::factory(5)->for($webhook)->create();

    $this->actingAs($this->user)
        ->delete("/webhooks/{$webhook->slug}/logs")
        ->assertRedirect();

    expect(WebhookLog::where('webhook_id', $webhook->id)->count())->toBe(0);
});

it('forbids deleting all logs for non-owner', function () {
    $webhook = Webhook::factory()->for(User::factory())->create();
    WebhookLog::factory(3)->for($webhook)->create();

    $this->actingAs($this->user)
        ->delete("/webhooks/{$webhook->slug}/logs")
        ->assertNotFound();
});
