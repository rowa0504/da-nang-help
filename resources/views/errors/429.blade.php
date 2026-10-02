<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.rate_limited_title') }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            color: #1f2937;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 1.5rem;
        }
        .card {
            max-width: 28rem;
            width: 100%;
            text-align: center;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0F766E;
            margin: 0 0 0.75rem;
        }
        p {
            margin: 0;
            color: #4b5563;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('messages.rate_limited_title') }}</h1>
        <p>
            @php
                $retryAfter = max(1, (int) ($exception?->getHeaders()['Retry-After'] ?? 60));
            @endphp
            {{ __('messages.rate_limited_body', ['seconds' => $retryAfter]) }}
        </p>
    </div>
</body>
</html>
