<?php

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Http\UploadedFile;

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

it('allows duplicate slugs with unique tokens', function () {
    Webhook::factory()->for($this->user)->create(['slug' => 'test']);

    $this->actingAs($this->user)
        ->post('/webhooks', ['name' => 'Test'])
        ->assertRedirect('/webhooks');

    expect(Webhook::where('slug', 'test')->count())->toBe(2);
    expect(Webhook::where('slug', 'test')->pluck('token')->unique()->count())->toBe(2);
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

    $this->get("/w/{$webhook->slug}/{$webhook->token}")
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('webhook_logs', [
        'webhook_id' => $webhook->id,
        'method' => 'GET',
    ]);
});

it('receives POST with JSON payload and saves log', function () {
    $webhook = Webhook::factory()->create();

    $this->postJson("/w/{$webhook->slug}/{$webhook->token}", ['event' => 'order.created'])
        ->assertOk();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->method)->toBe('POST')
        ->and($log->payload)->toContain('order.created');
});

it('receives POST with form-urlencoded and saves as JSON payload', function () {
    $webhook = Webhook::factory()->create();

    $this->post(
        "/w/{$webhook->slug}/{$webhook->token}",
        ['name' => 'John', 'email' => 'john@example.com'],
        ['Content-Type' => 'application/x-www-form-urlencoded'],
    )->assertOk();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    $decoded = json_decode($log->payload, true);

    expect($decoded)->toMatchArray(['name' => 'John', 'email' => 'john@example.com']);
});

it('receives multipart form with file and saves file metadata without content', function () {
    $webhook = Webhook::factory()->create();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->post(
        "/w/{$webhook->slug}/{$webhook->token}",
        ['title' => 'My Doc', 'attachment' => $file],
    )->assertOk();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    $decoded = json_decode($log->payload, true);

    expect($decoded['title'])->toBe('My Doc')
        ->and($decoded['attachment']['_file'])->toBeTrue()
        ->and($decoded['attachment']['name'])->toBe('document.pdf')
        ->and($decoded['attachment']['mime'])->toBe('application/pdf')
        ->and($decoded['attachment'])->not->toHaveKey('content');
});

it('returns 404 for unknown slug', function () {
    $this->post('/w/nonexistent/invalid-token')->assertNotFound();
});

it('cascades delete logs when webhook deleted', function () {
    $webhook = Webhook::factory()->create();
    WebhookLog::factory(3)->for($webhook)->create();

    $webhook->delete();

    expect(WebhookLog::where('webhook_id', $webhook->id)->count())->toBe(0);
});

// --- read_at ---

it('returns full log detail as json', function () {
    $webhook = Webhook::factory()->for($this->user)->create();
    $log = WebhookLog::factory()->for($webhook)->create();

    $this->actingAs($this->user)
        ->getJson("/webhooks/{$webhook->slug}/logs/{$log->sqid}")
        ->assertOk()
        ->assertJsonStructure(['sqid', 'method', 'headers', 'payload', 'user_agent']);
});

it('forbids fetching log detail for non-owner', function () {
    $webhook = Webhook::factory()->for(User::factory())->create();
    $log = WebhookLog::factory()->for($webhook)->create();

    $this->actingAs($this->user)
        ->getJson("/webhooks/{$webhook->slug}/logs/{$log->sqid}")
        ->assertNotFound();
});

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
