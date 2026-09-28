<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the source IPO page was last read for details the API doesn't provide.
        Schema::table('ipos', function (Blueprint $table) {
            $table->timestamp('page_synced_at')->nullable();
        });

        Schema::table('ipo_details', function (Blueprint $table) {
            $table->text('strengths')->nullable()->after('objects');
            $table->text('weaknesses')->nullable()->after('strengths');
        });
    }

    public function down(): void
    {
        Schema::table('ipo_details', function (Blueprint $table) {
            $table->dropColumn(['strengths', 'weaknesses']);
        });

        Schema::table('ipos', function (Blueprint $table) {
            $table->dropColumn('page_synced_at');
        });
    }
};
