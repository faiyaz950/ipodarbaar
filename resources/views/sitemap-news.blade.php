{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
@foreach ($items as $item)
    <url>
        <loc>{{ $item['url'] }}</loc>
        <news:news>
            <news:publication>
                <news:name>IPO Darbaar</news:name>
                <news:language>en</news:language>
            </news:publication>
            <news:publication_date>{{ $item['date']->toAtomString() }}</news:publication_date>
            <news:title>{{ $item['headline'] }}</news:title>
        </news:news>
    </url>
@endforeach
</urlset>
