<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per message already sent (e.g. telegram / digest:2026-09-28), so reruns never repeat it.
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('key', 150);
            $table->timestamp('sent_at');
            $table->unique(['channel', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
