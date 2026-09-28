<?php

namespace App\Http\Controllers;

use App\Models\CorporateAction;
use Illuminate\View\View;

/**
 * Public lists and pages for buybacks, rights issues and NCD issues.
 */
class CorporateActionController extends Controller
{
    /** Closed offers stay listed for this long. */
    private const CLOSED_DAYS = 120;

    public function index(string $type): View
    {
        $actions = CorporateAction::query()->published()->ofType($type)
            ->where(fn ($q) => $q->whereNull('close_date')->orWhereDate('close_date', '>=', today()->subDays(self::CLOSED_DAYS)->toDateString()))
            ->orderByRaw('open_date is null')->orderBy('open_date')
            ->get()
            ->groupBy(fn (CorporateAction $action): string => $action->status());

        return view('actions.index', [
            'type' => $type,
            'meta' => CorporateAction::TYPES[$type],
            'open' => $actions->get('open', collect()),
            'upcoming' => $actions->get('upcoming', collect()),
            'closed' => $actions->get('closed', collect())->sortByDesc('close_date')->values(),
        ]);
    }

    /**
     * Route parameters are passed in order: the {offer} slug first, then the "type" default.
     */
    public function show(CorporateAction $offer, string $type): View
    {
        $action = $offer;
        abort_unless($action->type === $type && $action->is_published, 404);

        return view('actions.show', [
            'action' => $action,
            'meta' => CorporateAction::TYPES[$type],
            'more' => CorporateAction::query()->published()->ofType($type)->whereKeyNot($action->id)
                ->orderByDesc('open_date')->limit(5)->get(),
        ]);
    }
}
