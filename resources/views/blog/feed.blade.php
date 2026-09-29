{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>IPO Darbaar Blog</title>
    <link>{{ route('blog.index') }}</link>
    <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
    <description>IPO reviews, the weekly IPO wrap, listing day recaps and IPO market data from the IPO Darbaar Research Desk.</description>
    <language>en-in</language>
    @if ($posts->isNotEmpty())
    <lastBuildDate>{{ $posts->first()->published_at->toRssString() }}</lastBuildDate>
    @endif
    @foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ $post->url() }}</link>
        <guid isPermaLink="true">{{ $post->url() }}</guid>
        <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
        <dc:creator>{{ $post->author->name }}</dc:creator>
        <category>{{ $post->categoryLabel() }}</category>
        <description>{{ $post->metaDescription() }}</description>
    </item>
    @endforeach
</channel>
</rss>
