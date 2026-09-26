<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>A &amp; J OASIS — Rental Apartments in Koronadal City</title>
    <meta name="description" content="Book a room online at A & J OASIS. {{ $stats['rooms'] }} rooms across {{ $stats['properties'] }} properties in Koronadal City, transparent pricing, and secure online payments.">
    <link href="{{ asset('favicon.ico') }}" rel="icon" sizes="any">
    <link href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏠</text></svg>" rel="icon" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --oasis-green: #1b4332;
            --oasis-green-dark: #10291f;
            --oasis-gold: #c9963e;
            --oasis-sand: #faf6ee;
        }
        html { -webkit-text-size-adjust: 100%; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1f2d27; background: #fff; }
        img { max-width: 100%; height: auto; }
        a, button, .btn, .nav-link { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        .navbar-oasis.sticky-top { padding-top: calc(.5rem + env(safe-area-inset-top)); }
        footer.py-5 { padding-bottom: calc(3rem + env(safe-area-inset-bottom)); }
        .btn-oasis { background: var(--oasis-gold); border-color: var(--oasis-gold); color: #1f2d27; font-weight: 600; }
        .btn-oasis:hover { background: #b8852f; border-color: #b8852f; color: #fff; }
        .text-oasis { color: var(--oasis-green); }
        .bg-oasis { background: var(--oasis-green); }
        .bg-oasis-dark { background: var(--oasis-green-dark); }
        .bg-sand { background: var(--oasis-sand); }
        .navbar-oasis { background: rgba(255,255,255,.97); }
        .navbar-oasis .nav-link { color: #1f2d27; font-weight: 500; }
        .hero { background: radial-gradient(circle at 80% 20%, #2d6a4f 0%, var(--oasis-green) 55%, var(--oasis-green-dark) 100%); color: #fff; }
        .hero-visual { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); border-radius: 1rem; backdrop-filter: blur(4px); }
        .stat-strip .num { font-size: 2.25rem; font-weight: 700; color: var(--oasis-green); }
        .feature-icon { width: 3.25rem; height: 3.25rem; border-radius: .9rem; display: flex; align-items: center; justify-content: center; background: #e9f2ec; color: var(--oasis-green); font-size: 1.4rem; }
        .step-num { width: 2.5rem; height: 2.5rem; border-radius: 50%; background: var(--oasis-gold); color: #1f2d27; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .room-card { border: none; border-radius: 1rem; overflow: hidden; transition: transform .15s ease, box-shadow .15s ease; }
        .room-card:hover { transform: translateY(-4px); box-shadow: 0 1rem 2rem rgba(27,67,50,.15); }
        .room-card-media { width: 100%; height: 140px; background: linear-gradient(135deg, #2d6a4f, var(--oasis-green)); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.85); font-size: 2.5rem; }
        .accordion-button:not(.collapsed) { background: #e9f2ec; color: var(--oasis-green); }
        .accordion-button:focus { box-shadow: none; border-color: rgba(27,67,50,.25); }
        footer a { color: rgba(255,255,255,.75); text-decoration: none; }
        footer a:hover { color: #fff; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-oasis sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-oasis" href="{{ url('/') }}"><i class="bi bi-house-door-fill me-1"></i>A &amp; J OASIS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="landingNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('rooms.browse') }}">Rooms</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="{{ route('login') }}" class="btn btn-outline-success">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-oasis">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="hero py-5">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="badge bg-white text-oasis mb-3 px-3 py-2">Koronadal City, South Cotabato</span>
                    <h1 class="display-5 fw-bold mb-3">Find your next room. Book it in minutes.</h1>
                    <p class="fs-5 mb-4" style="color: rgba(255,255,255,.85);">
                        A & J OASIS manages {{ $stats['rooms'] }} rooms across {{ $stats['properties'] }} properties, with transparent pricing, a fully online
                        booking flow, and secure payments &mdash; no walk-in required.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('rooms.browse') }}" class="btn btn-oasis btn-lg"><i class="bi bi-search me-1"></i>Browse Rooms</a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg">How It Works</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    @php($heroImage = $featuredRooms->first()?->images->first())
                    <div class="hero-visual p-2 p-lg-3">
                        @if ($heroImage)
                            <img src="{{ $heroImage->url() }}" alt="A room at A & J OASIS, Koronadal City" class="w-100 rounded-3 d-block" style="aspect-ratio: 4 / 3; object-fit: cover;">
                        @else
                            <img src="{{ asset('images/hero-placeholder.svg') }}" alt="A & J OASIS property photo &mdash; coming soon" class="w-100 rounded-3 d-block" style="aspect-ratio: 4 / 3; object-fit: cover;">
                        @endif
                    </div>
                    <div class="row g-2 text-center mt-1">
                        <div class="col-4">
                            <i class="bi bi-door-open fs-4"></i>
                            <p class="small mt-1 mb-0" style="color: rgba(255,255,255,.8);">Choose a Room</p>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-credit-card fs-4"></i>
                            <p class="small mt-1 mb-0" style="color: rgba(255,255,255,.8);">Pay Upfront</p>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-key fs-4"></i>
                            <p class="small mt-1 mb-0" style="color: rgba(255,255,255,.8);">Move In</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-sand py-5">
        <div class="container">
            <div class="row g-4 text-center stat-strip">
                <div class="col-md-4">
                    <div class="num">{{ $stats['rooms'] }}</div>
                    <p class="text-muted mb-0">Rooms across our properties</p>
                </div>
                <div class="col-md-4">
                    <div class="num">{{ $stats['properties'] }}</div>
                    <p class="text-muted mb-0">Properties in Koronadal City</p>
                </div>
                <div class="col-md-4">
                    <div class="num">100%</div>
                    <p class="text-muted mb-0">Online management &mdash; booking to billing</p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-oasis">Why book with us</h2>
                <p class="text-muted">Everything is handled online, from booking to billing.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-icon mb-3"><i class="bi bi-calendar-check"></i></div>
                    <h3 class="h6 fw-bold">Easy Online Booking</h3>
                    <p class="text-muted small mb-0">Browse vacant rooms and reserve one in a few clicks &mdash; no need to visit in person first.</p>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-icon mb-3"><i class="bi bi-shield-check"></i></div>
                    <h3 class="h6 fw-bold">Secure Online Payments</h3>
                    <p class="text-muted small mb-0">Upfront and monthly payments are processed securely through Xendit.</p>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-icon mb-3"><i class="bi bi-tools"></i></div>
                    <h3 class="h6 fw-bold">Fast Maintenance Support</h3>
                    <p class="text-muted small mb-0">Report an issue from your dashboard and track it through to completion.</p>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-icon mb-3"><i class="bi bi-receipt"></i></div>
                    <h3 class="h6 fw-bold">Transparent Billing</h3>
                    <p class="text-muted small mb-0">Clear due dates, a one-month grace period, and a full payment history on record.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-4 border-top border-bottom">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-geo-alt-fill text-oasis fs-4"></i>
                        <h2 class="h5 fw-bold text-oasis mb-0">Find us in Koronadal City</h2>
                    </div>
                    <p class="text-muted mb-0">
                        Both A & J OASIS properties are based in Koronadal City, South Cotabato &mdash; walk-throughs
                        can be arranged, but every booking, payment, and lease can be handled fully online without
                        ever needing to visit in person first.
                    </p>
                </div>
                <div class="col-lg-5">
                    <div class="ratio ratio-16x9 rounded-3 overflow-hidden shadow-sm">
                        <iframe
                            src="https://www.google.com/maps?q=Koronadal+City,+South+Cotabato&output=embed"
                            title="Map of Koronadal City, South Cotabato"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            style="border:0;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($featuredRooms->isNotEmpty())
        <section class="bg-sand py-5">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h2 class="fw-bold text-oasis mb-1">Available Rooms</h2>
                        <p class="text-muted mb-0">A few of our vacant rooms right now.</p>
                    </div>
                    <a href="{{ route('rooms.browse') }}" class="btn btn-outline-success d-none d-sm-inline-block">Browse Rooms</a>
                </div>
                <div class="row g-4">
                    @foreach ($featuredRooms as $room)
                        <div class="col-md-4">
                            <a href="{{ route('rooms.show', $room) }}" class="text-decoration-none text-reset">
                                <div class="card room-card shadow-sm h-100">
                                    @if ($room->images->isNotEmpty())
                                        <img src="{{ $room->images->first()->url() }}" class="room-card-media" style="object-fit: cover;" alt="Room {{ $room->room_number }}">
                                    @else
                                        <div class="room-card-media"><i class="bi bi-door-open"></i></div>
                                    @endif
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h3 class="h6 fw-bold mb-0">Room {{ $room->room_number }}</h3>
                                            <span class="badge text-bg-success">Vacant</span>
                                        </div>
                                        <p class="text-muted small mb-2">{{ $room->property->name }} &middot; Floor {{ $room->floor }}</p>
                                        <p class="fw-semibold text-oasis mb-1">₱{{ number_format($room->monthly_rate, 2) }} <span class="fw-normal text-muted small">/ month</span></p>
                                        <p class="text-muted mb-0" style="font-size: .75rem;"><i class="bi bi-info-circle me-1"></i>3-month upfront payment required</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-4 d-sm-none">
                    <a href="{{ route('rooms.browse') }}" class="btn btn-outline-success">Browse Rooms</a>
                </div>
            </div>
        </section>
    @endif

    <section id="how-it-works" class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-oasis">How it works</h2>
                <p class="text-muted">From browsing to move-in, in four steps.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3 d-flex gap-3">
                    <div class="step-num">1</div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">Browse &amp; Choose</h3>
                        <p class="text-muted small mb-0">Look through vacant rooms and pick the one that fits.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 d-flex gap-3">
                    <div class="step-num">2</div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">Book &amp; Pay Upfront</h3>
                        <p class="text-muted small mb-0">Pay the advance, deposit, and security (3 months) via Xendit.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 d-flex gap-3">
                    <div class="step-num">3</div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">Confirm Move-in Date</h3>
                        <p class="text-muted small mb-0">Choose your move-in date within 7 days of booking.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 d-flex gap-3">
                    <div class="step-num">4</div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">Move In</h3>
                        <p class="text-muted small mb-0">Your lease starts on your move-in date &mdash; manage everything online from there.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-oasis py-5">
        <div class="container py-3 text-center text-white">
            <h2 class="fw-bold mb-3">Ready to move in?</h2>
            <p class="mb-4" style="color: rgba(255,255,255,.85);">Create an account and book your room today.</p>
            <a href="{{ route('register') }}" class="btn btn-oasis btn-lg"><i class="bi bi-person-plus me-1"></i>Sign Up</a>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-oasis">Frequently Asked Questions</h2>
            </div>
            <div class="accordion mx-auto" id="faqAccordion" style="max-width: 760px;">
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How much do I need to pay when booking?
                        </button>
                    </h3>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            A full three-month upfront payment covering the advance, deposit, and security &mdash; paid securely via Xendit at the time of booking.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            When do I need to choose my move-in date?
                        </button>
                    </h3>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Within 7 days of booking. Billing starts on your scheduled move-in date, regardless of when you physically arrive.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            What happens if I'm late on a payment?
                        </button>
                    </h3>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            You get a one-month grace period on rent and utility payments before it's marked overdue.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Can I cancel my booking?
                        </button>
                    </h3>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Yes &mdash; cancellations made before your move-in date get a full refund.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            Can I transfer to a different room later?
                        </button>
                    </h3>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Yes. Request a transfer from your dashboard; you'll pay the deposit difference for an upgrade, while downgrade credits are handled directly by the admin.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer id="contact" class="bg-oasis-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h3 class="h5 fw-bold mb-3"><i class="bi bi-house-door-fill me-1"></i>A &amp; J OASIS</h3>
                    <p class="small" style="color: rgba(255,255,255,.7);">Comfortable, secure rentals in Koronadal City &mdash; fully bookable online.</p>
                </div>
                <div class="col-md-4">
                    <h4 class="h6 fw-bold mb-3">Quick Links</h4>
                    <ul class="list-unstyled small d-flex flex-column gap-2">
                        <li><a href="{{ route('rooms.browse') }}">Browse Rooms</a></li>
                        <li><a href="{{ route('login') }}">Log in</a></li>
                        <li><a href="{{ route('register') }}">Sign Up</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h4 class="h6 fw-bold mb-3">Contact</h4>
                    <ul class="list-unstyled small d-flex flex-column gap-2" style="color: rgba(255,255,255,.7);">
                        <li><i class="bi bi-geo-alt me-2"></i>Koronadal City, South Cotabato</li>
                        <li><i class="bi bi-envelope me-2"></i><a href="mailto:hello@ajoasis.test">hello@ajoasis.test</a></li>
                        <li><i class="bi bi-telephone me-2"></i><a href="tel:+639123456789">+63 912 345 6789</a></li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <p class="small text-center mb-0" style="color: rgba(255,255,255,.6);">
                &copy; {{ now()->year }} A &amp; J OASIS. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('#landingNav .nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                const nav = document.getElementById('landingNav');
                if (nav.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(nav).hide();
                }
            });
        });
    </script>
</body>
</html>
