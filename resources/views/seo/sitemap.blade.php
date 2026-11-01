@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
    @foreach ([route('welcome'), route('observatory'), route('time-machine'), ...$photos->map(fn ($photo) => route('astrophoto.show', $photo))] as $pageUrl)
        @foreach (App\Support\Locales::codes() as $pageLocale)
            <url>
                <loc>{{ App\Support\Locales::url($pageLocale, $pageUrl) }}</loc>
                @foreach (App\Support\Locales::codes() as $alternateLocale)
                    <xhtml:link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ App\Support\Locales::url($alternateLocale, $pageUrl) }}"/>
                @endforeach
            </url>
        @endforeach
    @endforeach
</urlset>
