<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per page view, sent by the site's script. Visitors are a daily hash (no IP, no cookie).
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255);
            $table->string('route', 80)->nullable();
            $table->string('section', 24);
            $table->string('source', 40);
            $table->string('referrer_host', 120)->nullable();
            $table->boolean('is_entry')->default(false);
            $table->char('visitor', 16);
            $table->string('device', 10);
            $table->string('browser', 24);
            $table->string('os', 16);
            $table->string('utm_source', 60)->nullable();
            $table->string('utm_medium', 60)->nullable();
            $table->string('utm_campaign', 60)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['path', 'created_at']);
        });

        // Clicks and actions (share, watchlist, subscribe, poll vote…).
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('label', 120)->nullable();
            $table->string('path', 255);
            $table->char('visitor', 16);
            $table->timestamp('created_at')->index();
            $table->index(['name', 'created_at']);
        });

        // Latest title and section of every page seen, for showing names next to paths.
        Schema::create('analytics_pages', function (Blueprint $table) {
            $table->string('path', 255)->primary();
            $table->string('title', 190)->nullable();
            $table->string('section', 24);
            $table->timestamp('last_seen_at');
        });

        // Daily totals per dimension (page, section, source, device…), kept after raw rows expire.
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('dimension', 16);
            $table->string('value', 255);
            $table->unsignedInteger('views');
            $table->unsignedInteger('visitors');
            $table->unique(['date', 'dimension', 'value']);
            $table->index(['dimension', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('analytics_pages');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('page_views');
    }
};
