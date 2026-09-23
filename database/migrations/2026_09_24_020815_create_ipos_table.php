<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_id')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 20)->index(); // mainboard | sme
            $table->string('exchange', 40)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_min', 12, 2)->nullable();
            $table->decimal('gmp', 12, 2)->nullable();
            $table->decimal('issue_size', 14, 2)->nullable(); // in ₹ crore
            $table->unsignedInteger('lot_size')->nullable();
            $table->date('open_date')->nullable()->index();
            $table->date('close_date')->nullable()->index();
            $table->date('listing_date')->nullable()->index();
            $table->boolean('is_published')->default(false);
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipos');
    }
};
