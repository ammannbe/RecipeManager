{{--
    Link previews (Open Graph, X/Twitter) with fixed German texts; crawlers come without a user.
    Image: public/og-image.png (1200×630).
--}}
@php($description = 'Rezepte sammeln, teilen und planen: Kochbücher mit Familie und Freunden teilen und saisonal kochen.')
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:locale" content="de_CH">
<meta property="og:image" content="{{ url('/og-image.png') }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
