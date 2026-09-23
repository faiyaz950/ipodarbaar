<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipos', function (Blueprint $table) {
            // Filled in by admins; the API sync never touches these.
            $table->string('registrar', 60)->nullable()->after('exchange');
            $table->decimal('subscription_retail', 10, 2)->nullable();
            $table->decimal('subscription_nii', 10, 2)->nullable();
            $table->decimal('subscription_qib', 10, 2)->nullable();
            $table->decimal('subscription_total', 10, 2)->nullable();
            $table->timestamp('subscription_updated_at')->nullable();
            $table->decimal('listing_price', 12, 2)->nullable();
            $table->text('about')->nullable();
            // API-backed columns an admin has overridden; sync keeps their current value.
            $table->json('locked_fields')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('ipos', function (Blueprint $table) {
            $table->dropColumn([
                'registrar', 'subscription_retail', 'subscription_nii', 'subscription_qib',
                'subscription_total', 'subscription_updated_at', 'listing_price', 'about', 'locked_fields',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
