<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('headers');
            $table->json('query_params')->nullable();
            $table->longText('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
