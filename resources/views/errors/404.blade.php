<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Page Not Found — A&amp;J CITY OASIS</title>
    <meta name="robots" content="noindex">
    <link href="{{ asset('favicon.ico') }}" rel="icon" sizes="any">
    <link href="{{ asset('images/logo-mark.svg') }}" rel="icon" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    @include('partials.brand-theme')
    @include('partials.animations')
    <style>
        html { -webkit-text-size-adjust: 100%; }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #faf6ee;
            color: #1f2d27;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        main { flex: 1; }
        a, button, .btn { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
    </style>
</head>
<body>
    <main class="d-flex align-items-center justify-content-center text-center p-4">
        <div>
            <i class="bi bi-signpost-split text-oasis" style="font-size: 4rem;"></i>
            <h1 class="display-4 fw-bold mt-3 mb-2 text-oasis">404</h1>
            <p class="fs-5 text-muted mb-4">We couldn't find the page you're looking for.</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a href="{{ url('/') }}" class="btn btn-oasis"><i class="bi bi-house-door me-1"></i>Back to Home</a>
                <a href="{{ route('rooms.browse') }}" class="btn btn-outline-success"><i class="bi bi-search me-1"></i>Browse Rooms</a>
            </div>
        </div>
    </main>
</body>
</html>
