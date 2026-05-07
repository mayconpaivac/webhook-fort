<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->uuid('token')->nullable()->unique()->after('slug');
        });

        // Backfill token for existing rows
        DB::table('webhooks')->orderBy('id')->each(function ($webhook) {
            DB::table('webhooks')->where('id', $webhook->id)->update(['token' => Str::uuid()->toString()]);
        });
    }
};
