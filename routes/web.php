<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WebhookReceiverController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->as('app.')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::get('webhooks/{slug}/{log?}', [WebhookController::class, 'show'])->name('webhooks.show');
    Route::delete('webhooks/{slug}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::get('webhooks/{slug}/logs/{log}', [WebhookController::class, 'showLog'])->name('webhooks.logs.show');
    Route::delete('webhooks/{slug}/logs', [WebhookController::class, 'destroyLogs'])->name('webhooks.logs.destroyAll');
    Route::delete('webhooks/{slug}/logs/{log}', [WebhookController::class, 'destroyLog'])->name('webhooks.logs.destroy');
    Route::patch('webhooks/{slug}/logs/{log}/read', [WebhookController::class, 'markRead'])->name('webhooks.logs.markRead');
});

Route::any('w/{slug}/{token}', [WebhookReceiverController::class, 'receive'])->name('webhook.receive');

require __DIR__.'/settings.php';
