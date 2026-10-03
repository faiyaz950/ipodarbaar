<?php

namespace App\Services\Blog;

use App\Models\BlogAuthor;
use App\Models\BlogPost;
use App\Support\BlogContent;
use App\Support\PageCache;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Saves the automatic daily and weekly posts. Used by the scheduler (blog:auto) and by the
 * "create now" buttons in the admin, so both follow the same on/off and publish settings.
 */
class AutoBlogPublisher
{
    public const KINDS = ['daily' => 'Daily IPO update', 'weekly' => 'Weekly IPO calendar', 'listing' => 'Listing day recap'];

    public function __construct(private AutoBlogWriter $writer, private Settings $settings) {}

    public function enabled(string $kind): bool
    {
        return (bool) $this->settings->get('blog.auto.'.$kind, true);
    }

    public function publishes(): bool
    {
        return (bool) $this->settings->get('blog.auto.publish', true);
    }

    /**
     * The Monday the weekly post covers: this week on a weekday, next week at the weekend.
     */
    public static function weekStart(Carbon $day): Carbon
    {
        return $day->isWeekend() ? $day->copy()->next(Carbon::MONDAY) : $day->copy()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * @return array{status: 'created'|'updated'|'exists'|'empty'|'off', message: string, post: BlogPost|null}
     */
    public function run(string $kind, ?Carbon $day = null, bool $force = false, ?bool $publish = null): array
    {
        $day ??= now();
        if (! $force && ! $this->enabled($kind)) {
            return ['status' => 'off', 'message' => self::KINDS[$kind].' posts are switched off.', 'post' => null];
        }

        $slug = match ($kind) {
            'daily' => $this->writer->dailySlug($day),
            'weekly' => $this->writer->weeklySlug(self::weekStart($day)),
            'listing' => $this->writer->listingSlug($day),
        };
        $existing = BlogPost::query()->where('slug', $slug)->first();
        if ($existing && ! $force) {
            return ['status' => 'exists', 'message' => 'Already written: '.$existing->url(), 'post' => $existing];
        }

        $draft = match ($kind) {
            'daily' => $this->writer->daily($day),
            'weekly' => $this->writer->weekly(self::weekStart($day)),
            'listing' => $this->writer->listingRecap($day),
        };
        if (! $draft) {
            return ['status' => 'empty', 'message' => ($kind === 'listing' ? 'No listings with prices recorded' : 'No IPO events to write about').' for '.$day->format('j M Y').'.', 'post' => null];
        }

        $post = $existing ?? new BlogPost;
        $body = BlogContent::sanitize($draft['body']);
        $post->fill([
            'blog_author_id' => $post->blog_author_id ?? $this->author()->id,
            'category' => $draft['category'],
            'title' => $draft['title'],
            'excerpt' => $draft['excerpt'],
            'takeaways' => $draft['takeaways'],
            'body' => $body,
            'image_path' => $draft['image']['path'] ?? null,
            'image_alt' => $draft['image']['alt'] ?? null,
            'seo_title' => $draft['seo_title'],
            'seo_description' => $draft['seo_description'],
            'reading_minutes' => BlogPost::readingMinutes($body),
        ]);
        $post->slug = $draft['slug'];
        if (! $existing) {
            $post->status = ($publish ?? $this->publishes()) ? 'published' : 'draft';
            $post->published_at = $post->status === 'published' ? now() : null;
        }
        $post->save();
        $post->ipos()->sync($draft['ipo_ids']);

        PageCache::flush();

        return [
            'status' => $existing ? 'updated' : 'created',
            'message' => ($existing ? 'Rebuilt' : ($post->status === 'published' ? 'Published' : 'Saved as a draft')).': '.$post->url(),
            'post' => $post,
        ];
    }

    private function author(): BlogAuthor
    {
        return BlogAuthor::query()->where('slug', 'ipo-darbaar-research-desk')->first()
            ?? BlogAuthor::query()->orderBy('id')->firstOrFail();
    }
}
