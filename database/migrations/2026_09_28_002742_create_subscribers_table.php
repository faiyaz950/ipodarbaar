<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('frequency', 10)->default('daily');
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['frequency', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
