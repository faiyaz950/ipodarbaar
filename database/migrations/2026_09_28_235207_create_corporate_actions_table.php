<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buybacks, rights issues and NCD issues, entered by editors in the admin panel.
        Schema::create('corporate_actions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index();
            $table->string('company', 160);
            $table->string('slug', 190)->unique();
            $table->date('open_date')->nullable()->index();
            $table->date('close_date')->nullable();
            $table->date('record_date')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('size_cr', 12, 2)->nullable();
            $table->string('method', 40)->nullable();
            $table->string('ratio', 20)->nullable();
            $table->string('coupon', 120)->nullable();
            $table->string('tenure', 80)->nullable();
            $table->string('rating', 80)->nullable();
            $table->string('exchange', 40)->nullable();
            $table->text('details')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_actions');
    }
};
