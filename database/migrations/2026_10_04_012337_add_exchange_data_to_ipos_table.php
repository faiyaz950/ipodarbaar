<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filled from NSE and BSE: subscription during bidding, listing prices from the exchanges.
        Schema::table('ipos', function (Blueprint $table) {
            $table->string('nse_symbol', 24)->nullable()->index();
            $table->string('isin', 12)->nullable();
            $table->string('bse_code', 12)->nullable();
            $table->decimal('subscription_bnii', 10, 2)->nullable();
            $table->decimal('subscription_snii', 10, 2)->nullable();
            $table->decimal('subscription_employee', 10, 2)->nullable();
            $table->decimal('listing_close', 12, 2)->nullable();
            $table->string('listing_exchange', 8)->nullable();
        });

        // The latest subscription figures for each bidding day.
        Schema::create('ipo_subscription_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipo_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('qib', 10, 2)->nullable();
            $table->decimal('nii', 10, 2)->nullable();
            $table->decimal('bnii', 10, 2)->nullable();
            $table->decimal('snii', 10, 2)->nullable();
            $table->decimal('retail', 10, 2)->nullable();
            $table->decimal('employee', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->timestamp('as_of')->nullable();
            $table->string('source', 12)->default('nse');
            $table->timestamps();
            $table->unique(['ipo_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipo_subscription_days');
        Schema::table('ipos', function (Blueprint $table) {
            $table->dropIndex(['nse_symbol']);
            $table->dropColumn([
                'nse_symbol', 'isin', 'bse_code', 'subscription_bnii', 'subscription_snii', 'subscription_employee',
                'listing_close', 'listing_exchange',
            ]);
        });
    }
};
