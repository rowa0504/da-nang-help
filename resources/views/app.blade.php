<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <meta property="og:title" content="{{ config('app.name') }}">
    <meta property="og:description" content="Connecting Da Nang residents with trusted local service providers.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ config('app.name') }}">
    <meta name="twitter:description" content="Connecting Da Nang residents with trusted local service providers.">
    <meta name="twitter:image" content="{{ asset('og-image.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
