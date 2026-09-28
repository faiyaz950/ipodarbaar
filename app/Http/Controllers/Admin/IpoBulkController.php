<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ipo;
use App\Services\IpoStatsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fill in the details the IPO feed doesn't provide (lot size, listing price, registrar,
 * subscription) for many IPOs at once, in a grid or by uploading a CSV.
 */
class IpoBulkController extends Controller
{
    /** Editable columns and their validation rules. */
    public const FIELDS = [
        'lot_size' => ['integer', 'min:1', 'max:1000000'],
        'listing_price' => ['numeric', 'min:0.01', 'max:1000000'],
        'registrar' => ['string', 'max:60'],
        'subscription_total' => ['numeric', 'min:0', 'max:100000'],
        'subscription_retail' => ['numeric', 'min:0', 'max:100000'],
        'subscription_nii' => ['numeric', 'min:0', 'max:100000'],
        'subscription_qib' => ['numeric', 'min:0', 'max:100000'],
    ];

    private const SUBSCRIPTION = ['subscription_total', 'subscription_retail', 'subscription_nii', 'subscription_qib'];

    private const FILTERS = ['active' => 'Not listed', 'recent' => 'Listed in last 60 days', 'all' => 'All'];

    private const MAX_ROWS = 2000;

    public function index(Request $request): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'active';
        $q = trim((string) $request->query('q', ''));

        return view('admin.ipos.bulk', [
            'ipos' => $this->query($filter, $q)->paginate(50)->withQueryString(),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'q' => $q,
            'registrars' => array_column(config('ipodarbar.registrars'), 'name'),
        ]);
    }

    public function update(Request $request, IpoStatsService $stats): RedirectResponse
    {
        $rows = collect($request->input('ipos', []))->filter(fn ($row): bool => is_array($row))->take(100);
        $rules = [];
        foreach (self::FIELDS as $field => $fieldRules) {
            $rules['ipos.*.'.$field] = ['nullable', ...$fieldRules];
        }
        Validator::make(['ipos' => $rows->all()], $rules, [], $this->attributeNames($rows->keys()->all()))->validate();

        $changed = 0;
        foreach (Ipo::query()->whereKey($rows->keys())->get() as $ipo) {
            $values = collect(self::FIELDS)->keys()
                ->filter(fn (string $field): bool => array_key_exists($field, $rows[$ipo->id]))
                ->mapWithKeys(fn (string $field): array => [$field => $this->clean($field, $rows[$ipo->id][$field])])
                ->all();
            $changed += (int) $this->apply($ipo, $values);
        }

        $this->afterChanges($stats);

        return back()->with('status', $changed ? "Saved {$changed} ".($changed === 1 ? 'IPO' : 'IPOs').'.' : 'Nothing changed.');
    }

    /**
     * CSV of the IPOs in the current filter with their current values, ready to edit and upload.
     */
    public function csv(Request $request): StreamedResponse
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'active';
        $ipos = $this->query($filter, '')->limit(self::MAX_ROWS)->get();

        return response()->streamDownload(function () use ($ipos) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['slug', 'name', 'status', ...array_keys(self::FIELDS)]);
            foreach ($ipos as $ipo) {
                fputcsv($out, [$ipo->slug, $ipo->name, $ipo->statusLabel(), ...array_map(fn (string $field) => $ipo->{$field}, array_keys(self::FIELDS))]);
            }
            fclose($out);
        }, 'ipo-darbaar-ipos-'.$filter.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Apply an uploaded CSV. Rows are matched by slug; empty cells leave the value unchanged.
     */
    public function import(Request $request, IpoStatsService $stats): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h): string => strtolower(trim((string) $h, " \t\n\r\0\x0B\u{FEFF}")), fgetcsv($handle) ?: []);
        if (! in_array('slug', $header, true)) {
            fclose($handle);

            return back()->withErrors(['file' => 'The CSV needs a "slug" column. Download the template to see the format.']);
        }
        $columns = array_values(array_intersect($header, array_keys(self::FIELDS)));

        $updated = 0;
        $errors = [];
        $line = 1;
        while (($cells = fgetcsv($handle)) !== false && $line <= self::MAX_ROWS) {
            $line++;
            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            if (trim((string) $row['slug']) === '') {
                continue;
            }
            $ipo = Ipo::query()->where('slug', trim($row['slug']))->first();
            if ($ipo === null) {
                $errors[] = "Line {$line}: no IPO with slug \"".trim($row['slug']).'".';

                continue;
            }

            $values = collect($columns)
                ->filter(fn (string $field): bool => trim((string) $row[$field]) !== '')
                ->mapWithKeys(fn (string $field): array => [$field => trim((string) $row[$field])])
                ->all();
            $validator = Validator::make($values, collect(self::FIELDS)->only(array_keys($values))->all());
            if ($validator->fails()) {
                $errors[] = "Line {$line} ({$ipo->name}): ".$validator->errors()->first();

                continue;
            }

            $updated += (int) $this->apply($ipo, collect($values)->map(fn ($value, string $field) => $this->clean($field, $value))->all());
        }
        fclose($handle);

        $this->afterChanges($stats);

        return back()
            ->with('status', "Imported: {$updated} ".($updated === 1 ? 'IPO' : 'IPOs').' updated'.($errors ? ', '.count($errors).' '.(count($errors) === 1 ? 'row' : 'rows').' skipped.' : '.'))
            ->with('import_errors', array_slice($errors, 0, 25));
    }

    private function query(string $filter, string $q): Builder
    {
        $query = match ($filter) {
            'recent' => Ipo::query()->listed()->whereDate('listing_date', '>=', now()->subDays(60)->toDateString())->orderByDesc('listing_date'),
            'all' => Ipo::query()->orderByRaw('open_date is null')->orderByDesc('open_date'),
            default => Ipo::query()->active()->orderByRaw('open_date is null')->orderBy('open_date'),
        };

        return $query->when($q !== '', fn (Builder $b) => $b->search($q))->orderBy('name');
    }

    /**
     * Save changed values. A changed lot size is locked so the next sync keeps it.
     *
     * @param  array<string, mixed>  $values
     */
    private function apply(Ipo $ipo, array $values): bool
    {
        $before = $ipo->only(array_keys($values));
        $ipo->fill($values);

        $changed = collect($values)->keys()->filter(fn (string $field): bool => $this->differs($before[$field], $ipo->{$field}));
        if ($changed->isEmpty()) {
            return false;
        }

        if ($changed->contains('lot_size')) {
            $ipo->locked_fields = array_values(array_unique([...($ipo->locked_fields ?? []), 'lot_size']));
        }
        if ($changed->intersect(self::SUBSCRIPTION)->isNotEmpty()) {
            $ipo->subscription_updated_at = now();
        }

        return $ipo->save();
    }

    private function clean(string $field, mixed $value): mixed
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            return null;
        }

        return match ($field) {
            'lot_size' => (int) $value,
            'registrar' => (string) $value,
            default => (float) $value,
        };
    }

    private function differs(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) > 0.00001;
        }

        return $a !== $b && ! ($a === null && $b === '') && ! ($a === '' && $b === null);
    }

    private function afterChanges(IpoStatsService $stats): void
    {
        Cache::forget('layout:ticker');
        $stats->flush();
    }

    /**
     * "ipos.12.lot_size" → "Lot size for Alpha Tech" in validation messages.
     *
     * @param  array<int, int|string>  $ids
     * @return array<string, string>
     */
    private function attributeNames(array $ids): array
    {
        $names = Ipo::query()->whereKey($ids)->pluck('name', 'id');
        $attributes = [];
        foreach ($ids as $id) {
            foreach (array_keys(self::FIELDS) as $field) {
                $attributes["ipos.{$id}.{$field}"] = str_replace('_', ' ', ucfirst($field)).' for '.($names[$id] ?? 'IPO');
            }
        }

        return $attributes;
    }
}
