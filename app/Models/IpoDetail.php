<?php

namespace App\Models;

use Database\Factories\IpoDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Admin-entered company and issue details for an IPO (never touched by the API sync).
 */
class IpoDetail extends Model
{
    /** @use HasFactory<IpoDetailFactory> */
    use HasFactory;

    /** Anchor shares unlock in two halves: 30 and 90 days after allotment (SEBI ICDR). */
    public const ANCHOR_LOCKIN_DAYS = [30, 90];

    protected $fillable = [
        'fresh_issue_cr', 'ofs_cr', 'face_value', 'market_cap_cr',
        'retail_quota', 'nii_quota', 'qib_quota',
        'promoter_holding_pre', 'promoter_holding_post', 'promoters', 'lead_managers', 'market_maker', 'objects',
        'pe_pre', 'pe_post', 'eps', 'roe', 'roce', 'ronw', 'debt_equity',
        'anchor_amount_cr', 'anchor_date', 'rhp_url', 'drhp_url', 'website_url',
    ];

    protected function casts(): array
    {
        $decimals = [
            'fresh_issue_cr', 'ofs_cr', 'face_value', 'market_cap_cr', 'retail_quota', 'nii_quota', 'qib_quota',
            'promoter_holding_pre', 'promoter_holding_post', 'pe_pre', 'pe_post', 'eps', 'roe', 'roce', 'ronw',
            'debt_equity', 'anchor_amount_cr',
        ];

        return array_fill_keys($decimals, 'float') + ['anchor_date' => 'date'];
    }

    public function ipo(): BelongsTo
    {
        return $this->belongsTo(Ipo::class);
    }

    /**
     * @return array<int, string>
     */
    public function objectsList(): array
    {
        return $this->lines($this->objects);
    }

    /**
     * @return array<int, string>
     */
    public function leadManagersList(): array
    {
        return $this->lines($this->lead_managers);
    }

    public function hasIssueStructure(): bool
    {
        return $this->fresh_issue_cr !== null || $this->ofs_cr !== null || $this->face_value !== null
            || $this->market_cap_cr !== null || $this->retail_quota !== null || filled($this->lead_managers)
            || filled($this->promoters) || $this->promoter_holding_pre !== null;
    }

    public function hasKpis(): bool
    {
        return collect(['pe_pre', 'pe_post', 'eps', 'roe', 'roce', 'ronw', 'debt_equity'])
            ->contains(fn (string $field): bool => $this->{$field} !== null);
    }

    public function hasDocuments(): bool
    {
        return filled($this->rhp_url) || filled($this->drhp_url) || filled($this->website_url);
    }

    /**
     * Anchor lock-in expiry dates, counted from the allotment date.
     *
     * @return array<int, Carbon>
     */
    public function anchorLockinDates(?Carbon $allotment): array
    {
        if (! $allotment) {
            return [];
        }

        return array_map(fn (int $days): Carbon => $allotment->copy()->addDays($days), self::ANCHOR_LOCKIN_DAYS);
    }

    /**
     * @return array<int, string>
     */
    private function lines(?string $text): array
    {
        return array_values(array_filter(array_map(
            fn (string $line): string => trim(ltrim(trim($line), '-•*')),
            preg_split('/\r\n|\r|\n/', (string) $text) ?: []
        ), fn (string $line): bool => $line !== ''));
    }
}
