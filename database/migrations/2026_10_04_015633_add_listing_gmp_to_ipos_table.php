<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The last grey market premium before listing, frozen when the listing price is recorded,
        // so later changes to the GMP feed can't rewrite how accurate it was.
        Schema::table('ipos', function (Blueprint $table) {
            $table->decimal('listing_gmp', 10, 2)->nullable()->after('listing_price');
        });

        // Already-listed IPOs: the last daily GMP recorded before listing day, else the GMP on file.
        DB::table('ipos')->whereNotNull('listing_price')->whereNotNull('listing_date')->orderBy('id')
            ->select(['id', 'gmp', 'listing_date'])
            ->chunkById(200, function ($ipos): void {
                foreach ($ipos as $ipo) {
                    $before = DB::table('ipo_gmp_histories')->where('ipo_id', $ipo->id)->whereDate('date', '<', $ipo->listing_date)
                        ->whereNotNull('gmp')->orderByDesc('date')->value('gmp');
                    $gmp = $before ?? $ipo->gmp;
                    if ($gmp !== null) {
                        DB::table('ipos')->where('id', $ipo->id)->update(['listing_gmp' => $gmp]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('ipos', function (Blueprint $table) {
            $table->dropColumn('listing_gmp');
        });
    }
};
