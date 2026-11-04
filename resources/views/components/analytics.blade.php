@php($umami = App\Support\Analytics::umami())
@if ($umami)
    <script defer src="{{ $umami['script'] }}" data-website-id="{{ $umami['website_id'] }}"></script>
@endif
