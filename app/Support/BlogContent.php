<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Ipo;
use Illuminate\Support\Str;

/**
 * Turns a blog post's stored HTML into what readers see: allow-listed tags only, links
 * to this site kept as normal links, headings given ids for the table of contents,
 * tagged IPOs linked on first mention, and [[ipo:slug]] replaced by a live IPO card.
 */
class BlogContent
{
    private const ALLOWED_TAGS = '<p><br><ul><ol><li><strong><b><em><i><u><h2><h3><h4><blockquote><a><table><thead><tbody><tr><th><td><figure><figcaption><img>';

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED_TAGS);

        $html = preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function (array $m) use ($host): string {
            $tag = strtolower($m[2]);
            if ($m[1] === '/') {
                return "</{$tag}>";
            }
            if ($tag === 'img') {
                return self::image($m[3], $host);
            }
            if ($tag !== 'a') {
                return $tag === 'br' ? '<br>' : "<{$tag}>";
            }
            if (! preg_match('/href\s*=\s*(["\'])([^"\']+)\1/i', $m[3], $href)) {
                return '<a>';
            }
            $url = trim(html_entity_decode($href[2]));
            // Links within the site stay followed; anything else opens in a new tab, unendorsed.
            if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
                return '<a href="'.e($url).'">';
            }
            if (preg_match('#^https?://#i', $url)) {
                return parse_url($url, PHP_URL_HOST) === $host
                    ? '<a href="'.e($url).'">'
                    : '<a href="'.e($url).'" target="_blank" rel="noopener nofollow">';
            }

            return '<a>';
        }, $html) ?? '';

        return trim(preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#i', '', $html) ?? '');
    }

    /**
     * Images are allowed only from this site's uploads folder; anything else is dropped.
     */
    private static function image(string $attributes, string $host): string
    {
        $attr = fn (string $name): ?string => preg_match('/\b'.$name.'\s*=\s*(["\'])(.*?)\1/is', $attributes, $m) ? trim(html_entity_decode($m[2])) : null;

        $src = (string) $attr('src');
        if (preg_match('#^https?://#i', $src) && parse_url($src, PHP_URL_HOST) === $host) {
            $src = (string) parse_url($src, PHP_URL_PATH);
        }
        if (! preg_match('#^/uploads/[A-Za-z0-9/_.-]+\.(webp|png|jpe?g)$#i', $src) || str_contains($src, '..')) {
            return '';
        }

        $size = '';
        foreach (['width', 'height'] as $name) {
            $value = (int) $attr($name);
            if ($value > 0 && $value <= 4000) {
                $size .= ' '.$name.'="'.$value.'"';
            }
        }

        return '<img src="'.e($src).'" alt="'.e((string) $attr('alt')).'"'.$size.' loading="lazy" decoding="async">';
    }

    /**
     * @return array{html: string, toc: list<array{id: string, text: string}>}
     */
    public static function render(BlogPost $post): array
    {
        $html = self::sanitize($post->body);

        $toc = [];
        $html = preg_replace_callback('#<h2>(.*?)</h2>#is', function (array $m) use (&$toc): string {
            $text = trim(html_entity_decode(strip_tags($m[1])));
            $id = Str::slug($text) ?: 'section';
            $taken = array_column($toc, 'id');
            for ($i = 2, $base = $id; in_array($id, $taken, true); $i++) {
                $id = $base.'-'.$i;
            }
            $toc[] = ['id' => $id, 'text' => $text];

            return '<h2 id="'.e($id).'">'.$m[1].'</h2>';
        }, $html) ?? $html;

        // [[ipo:slug]] on its own line becomes a live card with the IPO's latest details. The code is
        // parked in an HTML comment first so the IPO linker can't turn the slug into a link.
        $html = preg_replace('#(?:<p>\s*)?\[\[ipo:([a-z0-9-]{1,120})\]\](?:\s*</p>)?#i', '<!--ipo-card:$1-->', $html) ?? $html;

        if ($post->relationLoaded('ipos') ? $post->ipos->isNotEmpty() : $post->ipos()->exists()) {
            $html = IpoLinker::link($html, $post->ipos)['html'];
        }

        $html = preg_replace_callback('#<!--ipo-card:([a-z0-9-]{1,120})-->#i', function (array $m): string {
            $ipo = Ipo::query()->where('slug', strtolower($m[1]))->first();

            return $ipo ? view('blog._ipo-card', ['ipo' => $ipo])->render() : '';
        }, $html) ?? $html;

        // Wide tables scroll sideways on phones instead of stretching the page.
        $html = str_replace(['<table>', '</table>'], ['<div class="blog-table"><table>', '</table></div>'], $html);

        return ['html' => $html, 'toc' => $toc];
    }
}
