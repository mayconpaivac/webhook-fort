<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WebhookReceiverController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::get('webhooks/{slug}/{sqid?}', [WebhookController::class, 'show'])->name('webhooks.show');
    Route::delete('webhooks/{slug}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::delete('webhooks/{slug}/logs', [WebhookController::class, 'destroyLogs'])->name('webhooks.logs.destroyAll');
    Route::delete('webhooks/{slug}/logs/{log}', [WebhookController::class, 'destroyLog'])->name('webhooks.logs.destroy');
    Route::patch('webhooks/{slug}/logs/{log}/read', [WebhookController::class, 'markRead'])->name('webhooks.logs.markRead');
});

Route::any('w/{slug}', [WebhookReceiverController::class, 'receive'])
    ->name('webhook.receive');

require __DIR__.'/settings.php';
