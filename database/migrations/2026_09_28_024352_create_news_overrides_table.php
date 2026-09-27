<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('news_id')->unique();
            $table->string('headline', 300)->nullable();
            $table->string('headline_hinglish', 300)->nullable();
            $table->mediumText('news_detail')->nullable();
            $table->mediumText('news_detail_hinglish')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_overrides');
    }
};
