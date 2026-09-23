<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per IPO per day: the latest GMP seen that day (builds the GMP trend).
        Schema::create('ipo_gmp_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipo_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('gmp', 12, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['ipo_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipo_gmp_histories');
    }
};
