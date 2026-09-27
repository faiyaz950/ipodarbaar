<?php

namespace App\Models;

use Database\Factories\NewsOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Admin edits layered on top of a news item from the market-news API.
 * A null column means "use the API value", so untouched fields keep updating.
 */
class NewsOverride extends Model
{
    /** @use HasFactory<NewsOverrideFactory> */
    use HasFactory;

    /** Columns that replace the API field of the same name. */
    public const FIELDS = ['headline', 'headline_hinglish', 'news_detail', 'news_detail_hinglish', 'category_id'];

    public const DISK = 'uploads';

    protected $fillable = ['news_id', ...self::FIELDS, 'image_path', 'is_hidden'];

    protected function casts(): array
    {
        return ['news_id' => 'integer', 'category_id' => 'integer', 'is_hidden' => 'boolean'];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('uploads/'.$this->image_path) : null;
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk(self::DISK)->delete($this->image_path);
            $this->image_path = null;
        }
    }

    /** Nothing left to override: the row can be removed. */
    public function isEmpty(): bool
    {
        return ! $this->is_hidden && ! $this->image_path
            && collect(self::FIELDS)->every(fn (string $field) => $this->{$field} === null);
    }

    /** @return array<int, string> */
    public function changedLabels(): array
    {
        return array_values(array_filter([
            $this->headline !== null || $this->headline_hinglish !== null ? 'Headline' : null,
            $this->news_detail !== null || $this->news_detail_hinglish !== null ? 'Text' : null,
            $this->category_id !== null ? 'Category' : null,
            $this->image_path ? 'Image' : null,
        ]));
    }
}
