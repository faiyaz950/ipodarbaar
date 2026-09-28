<?php

namespace App\Models;

use Database\Factories\CorporateActionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A buyback, rights issue or NCD issue, entered by editors in the admin panel.
 */
class CorporateAction extends Model
{
    /** @use HasFactory<CorporateActionFactory> */
    use HasFactory;

    /** Kinds of offers, with their public list URL and labels. */
    public const TYPES = [
        'buyback' => ['label' => 'Buyback', 'plural' => 'Share Buybacks', 'path' => 'buyback', 'price' => 'Buyback price'],
        'rights' => ['label' => 'Rights Issue', 'plural' => 'Rights Issues', 'path' => 'rights-issue', 'price' => 'Rights issue price'],
        'ncd' => ['label' => 'NCD', 'plural' => 'NCD Issues', 'path' => 'ncd', 'price' => 'Face value'],
    ];

    public const STATUSES = ['open' => 'Open now', 'upcoming' => 'Upcoming', 'closed' => 'Closed'];

    protected $fillable = [
        'type', 'company', 'slug', 'open_date', 'close_date', 'record_date', 'price', 'size_cr',
        'method', 'ratio', 'coupon', 'tenure', 'rating', 'exchange', 'details', 'source_url', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'open_date' => 'date',
            'close_date' => 'date',
            'record_date' => 'date',
            'price' => 'float',
            'size_cr' => 'float',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Open while today is within the offer window; upcoming until it opens (or while dates are awaited). */
    public function status(): string
    {
        $today = today();
        if ($this->close_date !== null && $this->close_date->lt($today)) {
            return 'closed';
        }
        if ($this->open_date !== null && $this->open_date->lte($today)) {
            return 'open';
        }

        return 'upcoming';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status()];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? 'Offer';
    }

    public function url(): string
    {
        return route('actions.'.$this->type.'.show', $this->slug);
    }

    /** One-line summary of the offer for lists and descriptions. */
    public function summary(): string
    {
        $parts = array_filter([
            $this->price ? self::TYPES[$this->type]['price'].' ₹'.Ipo::num($this->price) : null,
            $this->size_cr ? 'size ₹'.Ipo::num($this->size_cr).' crore' : null,
            $this->type === 'rights' && $this->ratio ? 'ratio '.$this->ratio : null,
            $this->type === 'ncd' && $this->coupon ? 'coupon '.$this->coupon : null,
            $this->type === 'buyback' && $this->method ? strtolower($this->method) : null,
            $this->record_date ? 'record date '.$this->record_date->format('j M Y') : null,
        ]);

        return ucfirst(implode(', ', $parts));
    }
}
