<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Company and issue details entered by admins (the IPO API doesn't provide them).
        Schema::create('ipo_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipo_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('fresh_issue_cr', 12, 2)->nullable();
            $table->decimal('ofs_cr', 12, 2)->nullable();
            $table->decimal('face_value', 8, 2)->nullable();
            $table->decimal('market_cap_cr', 14, 2)->nullable();
            $table->decimal('retail_quota', 5, 2)->nullable();
            $table->decimal('nii_quota', 5, 2)->nullable();
            $table->decimal('qib_quota', 5, 2)->nullable();
            $table->decimal('promoter_holding_pre', 5, 2)->nullable();
            $table->decimal('promoter_holding_post', 5, 2)->nullable();
            $table->text('promoters')->nullable();
            $table->text('lead_managers')->nullable();
            $table->string('market_maker', 120)->nullable();
            $table->text('objects')->nullable();
            $table->decimal('pe_pre', 8, 2)->nullable();
            $table->decimal('pe_post', 8, 2)->nullable();
            $table->decimal('eps', 10, 2)->nullable();
            $table->decimal('roe', 8, 2)->nullable();
            $table->decimal('roce', 8, 2)->nullable();
            $table->decimal('ronw', 8, 2)->nullable();
            $table->decimal('debt_equity', 8, 2)->nullable();
            $table->decimal('anchor_amount_cr', 12, 2)->nullable();
            $table->date('anchor_date')->nullable();
            $table->string('rhp_url', 500)->nullable();
            $table->string('drhp_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipo_details');
    }
};
