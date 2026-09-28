<?php

namespace App\Services;

use App\Models\Ipo;
use Illuminate\Support\Facades\DB;

/**
 * Fills an IPO's missing details from its source page. Only empty fields are filled,
 * so anything an editor entered in the admin always wins.
 */
class IpoPageImporter
{
    public function __construct(private IpoPageParser $parser) {}

    /** @return list<string> the fields that were filled */
    public function import(Ipo $ipo, string $html): array
    {
        $page = $this->parser->parse($html);
        $filled = [];

        DB::transaction(function () use ($ipo, $page, &$filled): void {
            foreach (['registrar', 'lot_size', 'about'] as $field) {
                if (blank($ipo->{$field}) && $page[$field] !== null) {
                    $ipo->{$field} = $page[$field];
                    $filled[] = $field;
                }
            }
            $ipo->page_synced_at = now();
            $ipo->save();

            if ($page['detail'] !== []) {
                $detail = $ipo->detail ?? $ipo->detail()->make();
                foreach ($page['detail'] as $field => $value) {
                    if (blank($detail->{$field})) {
                        $detail->{$field} = $value;
                        $filled[] = $field;
                    }
                }
                if ($detail->isDirty()) {
                    $detail->save();
                }
            }

            if ($page['financials'] !== [] && $ipo->financials()->doesntExist()) {
                $ipo->financials()->createMany($page['financials']);
                $filled[] = 'financials';
            }
        });

        return $filled;
    }
}
