<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use App\Services\IpoStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class IpoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'active');

        $query = Ipo::query()->search($q);
        $query = match ($filter) {
            'all' => $query->latest('source_created_at')->latest('api_id'),
            'listed' => $query->listed()->latest('source_created_at')->latest('api_id'),
            'edited' => $query->where(fn ($w) => $w->whereNotNull('locked_fields')->orWhereNotNull('subscription_total')->orWhereNotNull('listing_price')->orWhereNotNull('about'))
                ->orderByDesc('updated_at'),
            default => $query->active()->latest('source_created_at')->latest('api_id'),
        };

        return view('admin.ipos.index', [
            'ipos' => $query->paginate(30)->withQueryString(),
            'q' => $q,
            'filter' => $filter,
        ]);
    }

    public function edit(Ipo $ipo)
    {
        return view('admin.ipos.edit', [
            'ipo' => $ipo,
            'registrars' => config('ipodarbar.registrars'),
            'history' => $ipo->gmpHistory()->latest('date')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, Ipo $ipo, IpoStatsService $stats)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['mainboard', 'sme'])],
            'exchange' => ['nullable', 'string', 'max:40'],
            'registrar' => ['nullable', 'string', 'max:60'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'lot_size' => ['nullable', 'integer', 'min:1'],
            'issue_size' => ['nullable', 'numeric', 'min:0'],
            'gmp' => ['nullable', 'numeric'],
            'open_date' => ['nullable', 'date'],
            'close_date' => ['nullable', 'date', 'after_or_equal:open_date'],
            'listing_date' => ['nullable', 'date', 'after_or_equal:close_date'],
            'subscription_retail' => ['nullable', 'numeric', 'min:0'],
            'subscription_nii' => ['nullable', 'numeric', 'min:0'],
            'subscription_qib' => ['nullable', 'numeric', 'min:0'],
            'subscription_total' => ['nullable', 'numeric', 'min:0'],
            'listing_price' => ['nullable', 'numeric', 'min:0'],
            'about' => ['nullable', 'string', 'max:10000'],
        ]);

        $before = $ipo->only(Ipo::LOCKABLE);
        $subsBefore = $ipo->only(['subscription_retail', 'subscription_nii', 'subscription_qib', 'subscription_total']);

        $ipo->fill($data);

        // Any API-backed field the admin changed is locked so the next sync keeps it.
        $locked = $ipo->locked_fields ?? [];
        foreach (Ipo::LOCKABLE as $field) {
            if ($ipo->isDirty($field) && ! $this->sameValue($before[$field], $ipo->{$field})) {
                $locked[] = $field;
            }
        }
        $ipo->locked_fields = array_values(array_unique($locked)) ?: null;

        if ($ipo->only(array_keys($subsBefore)) != $subsBefore) {
            $ipo->subscription_updated_at = now();
        }

        $ipo->save();

        if ($ipo->wasChanged('gmp')) {
            IpoGmpHistory::record([$ipo]);
        }
        Cache::forget('layout:ticker');
        $stats->flush();

        return redirect()->route('admin.ipos.edit', $ipo)->with('status', 'Saved. Changed API fields are now locked.');
    }

    public function unlock(Request $request, Ipo $ipo)
    {
        $field = $request->input('field');
        abort_unless(in_array($field, Ipo::LOCKABLE, true), 422);

        $ipo->locked_fields = array_values(array_diff($ipo->locked_fields ?? [], [$field])) ?: null;
        $ipo->save();

        return back()->with('status', "“{$field}” unlocked. The next sync will restore the API value.");
    }

    private function sameValue(mixed $a, mixed $b): bool
    {
        if ($a instanceof \DateTimeInterface || $b instanceof \DateTimeInterface) {
            return optional($a)->format('Y-m-d') === optional($b)->format('Y-m-d');
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.0001;
        }

        return (string) $a === (string) $b;
    }
}
