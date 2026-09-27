<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Restated financials from the offer document, in ₹ crore, one row per period.
        Schema::create('ipo_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipo_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->date('period_end');
            $table->decimal('revenue', 14, 2)->nullable();
            $table->decimal('pat', 14, 2)->nullable();
            $table->decimal('net_worth', 14, 2)->nullable();
            $table->decimal('total_assets', 14, 2)->nullable();
            $table->decimal('borrowings', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['ipo_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipo_financials');
    }
};
