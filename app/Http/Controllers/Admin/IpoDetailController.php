<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ipo;
use App\Models\IpoFinancial;
use App\Services\IpoStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin → IPO → Company & financials (offer-document details the API doesn't provide).
 */
class IpoDetailController extends Controller
{
    public const FINANCIAL_ROWS = 4;

    public function edit(Ipo $ipo): View
    {
        $financials = $ipo->financials()->get()->map(fn (IpoFinancial $row): array => [
            'period' => $row->period,
            'period_end' => $row->period_end->toDateString(),
        ] + $row->only(array_keys(IpoFinancial::METRICS)))->all();

        return view('admin.ipos.details', [
            'ipo' => $ipo,
            'detail' => $ipo->detail ?? $ipo->detail()->make(),
            'financials' => array_pad($financials, max(self::FINANCIAL_ROWS, count($financials)), []),
        ]);
    }

    public function update(Request $request, Ipo $ipo, IpoStatsService $stats): RedirectResponse
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999'];
        $percent = ['nullable', 'numeric', 'min:0', 'max:100'];
        $ratio = ['nullable', 'numeric', 'min:-9999', 'max:9999'];

        $data = $request->validate([
            'fresh_issue_cr' => $money,
            'ofs_cr' => $money,
            'face_value' => $money,
            'market_cap_cr' => $money,
            'retail_quota' => $percent,
            'nii_quota' => $percent,
            'qib_quota' => $percent,
            'promoter_holding_pre' => $percent,
            'promoter_holding_post' => $percent,
            'promoters' => ['nullable', 'string', 'max:1000'],
            'lead_managers' => ['nullable', 'string', 'max:1000'],
            'market_maker' => ['nullable', 'string', 'max:120'],
            'objects' => ['nullable', 'string', 'max:3000'],
            'pe_pre' => $ratio,
            'pe_post' => $ratio,
            'eps' => $ratio,
            'roe' => $ratio,
            'roce' => $ratio,
            'ronw' => $ratio,
            'debt_equity' => $ratio,
            'anchor_amount_cr' => $money,
            'anchor_date' => ['nullable', 'date'],
            'rhp_url' => ['nullable', 'url:https', 'max:500'],
            'drhp_url' => ['nullable', 'url:https', 'max:500'],
            'website_url' => ['nullable', 'url:http,https', 'max:500'],
            'financials' => ['array', 'max:8'],
            'financials.*.period' => ['nullable', 'string', 'max:20', 'distinct', 'required_with:financials.*.period_end,financials.*.revenue,financials.*.pat'],
            'financials.*.period_end' => ['nullable', 'date', 'required_with:financials.*.period'],
            'financials.*.revenue' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'financials.*.pat' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'financials.*.net_worth' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'financials.*.total_assets' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'financials.*.borrowings' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'financials.*.period.required_with' => 'Give each financial row a period, e.g. FY26.',
            'financials.*.period_end.required_with' => 'Add the period end date (e.g. 31 Mar 2026).',
            'financials.*.period.distinct' => 'Each period can appear only once.',
        ]);

        $rows = collect($data['financials'] ?? [])
            ->filter(fn (array $row): bool => filled($row['period'] ?? null))
            ->map(function (array $row): array {
                $values = ['period' => trim($row['period']), 'period_end' => $row['period_end']];
                foreach (array_keys(IpoFinancial::METRICS) as $metric) {
                    $values[$metric] = isset($row[$metric]) ? (float) $row[$metric] : null;
                }

                return $values;
            });

        DB::transaction(function () use ($ipo, $data, $rows): void {
            $ipo->detail()->updateOrCreate([], collect($data)->except('financials')->all());
            $ipo->financials()->delete();
            $ipo->financials()->createMany($rows->values()->all());
        });

        $stats->flush();

        return redirect()->route('admin.ipos.details.edit', $ipo)->with('status', 'Company details saved.');
    }
}
