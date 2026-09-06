<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1f2937;">
    {{-- $greeting/$body may embed values sourced from user-entered content
    (request titles, offer messages, etc.), so they must always be rendered
    via {{ }} (auto-escaped), never {!! !!}. --}}
    <p>{{ $greeting }}</p>
    <p>{{ $body }}</p>
</body>
</html>
