<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin CRUD for buybacks, rights issues and NCD issues.
 */
class CorporateActionController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), CorporateAction::TYPES) ? $request->query('type') : null;

        return view('admin.actions.index', [
            'actions' => CorporateAction::query()
                ->when($type, fn ($q) => $q->ofType($type))
                ->orderByRaw('open_date is null desc')->orderByDesc('open_date')
                ->paginate(40)->withQueryString(),
            'type' => $type,
        ]);
    }

    public function create(Request $request): View
    {
        $type = array_key_exists($request->query('type'), CorporateAction::TYPES) ? $request->query('type') : 'buyback';

        return view('admin.actions.form', ['action' => new CorporateAction(['type' => $type, 'is_published' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data);

        $action = CorporateAction::create($data);

        return redirect()->route('admin.actions.edit', $action)->with('status', 'Saved. It is live at '.$action->url());
    }

    public function edit(CorporateAction $offer): View
    {
        return view('admin.actions.form', ['action' => $offer]);
    }

    public function update(Request $request, CorporateAction $offer): RedirectResponse
    {
        $offer->update($this->validated($request));

        return redirect()->route('admin.actions.edit', $offer)->with('status', 'Saved.');
    }

    public function destroy(CorporateAction $offer): RedirectResponse
    {
        $offer->delete();

        return redirect()->route('admin.actions.index', ['type' => $offer->type])->with('status', '“'.$offer->company.'” deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CorporateAction::TYPES))],
            'company' => ['required', 'string', 'max:160'],
            'open_date' => ['nullable', 'date'],
            'close_date' => ['nullable', 'date', 'after_or_equal:open_date'],
            'record_date' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'size_cr' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'method' => ['nullable', 'string', 'max:40'],
            'ratio' => ['nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?\s*:\s*\d+(\.\d+)?$/'],
            'coupon' => ['nullable', 'string', 'max:120'],
            'tenure' => ['nullable', 'string', 'max:80'],
            'rating' => ['nullable', 'string', 'max:80'],
            'exchange' => ['nullable', 'string', 'max:40'],
            'details' => ['nullable', 'string', 'max:10000'],
            'source_url' => ['nullable', 'url:https', 'max:500'],
        ], ['ratio.regex' => 'Write the ratio like 1:5 (new shares : shares held).']);

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    /**
     * "Tata Motors", buyback, 2026 → "tata-motors-buyback-2026" (with a suffix if taken).
     *
     * @param  array<string, mixed>  $data
     */
    private function uniqueSlug(array $data): string
    {
        $year = $data['open_date'] ? substr((string) $data['open_date'], 0, 4) : now()->year;
        $base = Str::slug($data['company'].' '.CorporateAction::TYPES[$data['type']]['path'].' '.$year);
        $slug = $base;
        for ($i = 2; CorporateAction::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
