<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * An original article on the blog. It is live once published and its publish time has passed,
 * so posts can be scheduled.
 */
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    /** Kinds of posts; each has its own list page at /blog/{key}. */
    public const CATEGORIES = [
        'ipo-reviews' => [
            'label' => 'IPO Reviews',
            'title' => 'IPO Reviews: In-Depth Analysis of Upcoming IPOs',
            'description' => 'In-depth IPO reviews: the business, financials, valuation, strengths and risks of upcoming mainboard and SME IPOs, from the IPO Darbaar Research Desk.',
        ],
        'weekly-wrap' => [
            'label' => 'Weekly IPO Wrap',
            'title' => 'Weekly IPO Wrap: This Week in the IPO Market',
            'description' => 'A weekly round-up of the Indian IPO market: IPOs opening and closing, subscription, GMP moves and listings, every week.',
        ],
        'listing-recap' => [
            'label' => 'Listing Day Recap',
            'title' => 'IPO Listing Day Recap: GMP vs Actual Listing',
            'description' => 'How IPOs actually listed compared with their grey market premium, with listing gains and losses explained.',
        ],
        'trends' => [
            'label' => 'Trends & Data',
            'title' => 'IPO Trends & Data: Numbers Behind the IPO Market',
            'description' => 'Data-led posts on the Indian IPO market: funds raised, SME vs mainboard, month-wise trends, buybacks, rights issues and NCDs.',
        ],
        'explainers' => [
            'label' => 'Explainers',
            'title' => 'IPO Explainers: Rules, Tax and How Things Work',
            'description' => 'Clear explainers on IPO rules, SEBI changes, tax and how the primary market works for Indian investors.',
        ],
    ];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    /** Words per minute used for the reading-time estimate. */
    private const WORDS_PER_MINUTE = 200;

    protected $fillable = [
        'blog_author_id', 'category', 'title', 'slug', 'excerpt', 'takeaways', 'body', 'image_path', 'image_alt',
        'seo_title', 'seo_description', 'status', 'published_at', 'reading_minutes',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'reading_minutes' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(BlogAuthor::class, 'blog_author_id');
    }

    public function ipos(): BelongsToMany
    {
        return $this->belongsToMany(Ipo::class, 'blog_post_ipo');
    }

    /** Published and past its publish time. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function statusLabel(): string
    {
        if ($this->status !== 'published') {
            return 'Draft';
        }

        return $this->isLive() ? 'Published' : 'Scheduled';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? 'Blog';
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('uploads/'.$this->image_path) : null;
    }

    public function metaTitle(): string
    {
        return (string) ($this->seo_title ?: $this->title);
    }

    public function metaDescription(): string
    {
        return $this->seo_description ?: (string) $this->excerpt;
    }

    /** @return list<string> */
    public function takeawayList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->takeaways) ?: [])));
    }

    public static function readingMinutes(string $html): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($html)) / self::WORDS_PER_MINUTE));
    }

    /**
     * Problems to fix before publishing, shown in the admin editor.
     *
     * @return list<string>
     */
    public function seoWarnings(): array
    {
        $words = str_word_count(strip_tags((string) $this->body));
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $internal = preg_match('#href="(/|https?://'.preg_quote($host, '#').')#i', (string) $this->body)
            || str_contains((string) $this->body, '[[ipo:');

        return array_values(array_filter([
            mb_strlen($this->metaTitle()) > 65 ? 'Title is '.mb_strlen($this->metaTitle()).' characters; Google shows about 60. Add a shorter SEO title.' : null,
            $this->metaDescription() === '' ? 'Add a summary or SEO description (up to 160 characters).' : null,
            mb_strlen($this->metaDescription()) > 160 ? 'Description is '.mb_strlen($this->metaDescription()).' characters; keep it under 160.' : null,
            $this->image_path === null ? 'Add a featured image; posts with images get more clicks when shared.' : null,
            $words < 600 ? "The post has {$words} words; aim for at least 600." : null,
            ! str_contains((string) $this->body, '<h2') ? 'Add section headings (H2) so readers and Google can follow the structure.' : null,
            ! $internal ? 'Link to at least one IPO page, guide or calculator on the site.' : null,
            $this->takeawayList() === [] ? 'Add 3–4 key takeaways.' : null,
        ]));
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'post';
        if (array_key_exists($base, self::CATEGORIES) || in_array($base, ['author', 'feed'], true)) {
            $base .= '-post';
        }
        $slug = $base;
        for ($i = 2; self::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
