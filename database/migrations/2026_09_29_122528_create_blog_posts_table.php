<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('role', 160)->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_author_id')->constrained()->restrictOnDelete();
            $table->string('category', 32)->index();
            $table->string('title', 190);
            $table->string('slug', 190)->unique();
            $table->string('excerpt', 320)->nullable();
            $table->text('takeaways')->nullable();
            $table->longText('body');
            $table->string('image_path')->nullable();
            $table->string('image_alt', 190)->nullable();
            $table->string('seo_title', 90)->nullable();
            $table->string('seo_description', 200)->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->timestamps();
        });

        // IPOs a post is about: shown as live cards on the post and as related posts on the IPO page.
        Schema::create('blog_post_ipo', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ipo_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_post_id', 'ipo_id']);
        });

        DB::table('blog_authors')->insert([
            'name' => 'IPO Darbaar Research Desk',
            'slug' => 'ipo-darbaar-research-desk',
            'role' => 'Editorial team',
            'bio' => 'The IPO Darbaar Research Desk tracks every mainboard and SME IPO in India. Our posts are based on offer documents, SEBI and stock exchange filings and the data on this site, and are reviewed before publishing. We explain; we do not give buy or sell advice.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_ipo');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_authors');
    }
};
