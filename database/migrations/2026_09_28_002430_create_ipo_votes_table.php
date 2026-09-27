<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipo_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipo_id')->constrained()->cascadeOnDelete();
            $table->string('choice', 10);
            $table->char('voter_hash', 64);
            $table->char('ip_hash', 64);
            $table->timestamps();
            $table->unique(['ipo_id', 'voter_hash']);
            $table->index(['ipo_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipo_votes');
    }
};
