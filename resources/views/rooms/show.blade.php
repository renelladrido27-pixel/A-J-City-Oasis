@extends('layouts.app')

@section('title', 'Room ' . $room->room_number)
@section('meta_description', 'Room ' . $room->room_number . ' at ' . $room->property->name . ' - ' . $room->type . ' room, ₱' . number_format($room->monthly_rate, 2) . ' per month.')

@section('content')
<style>
    /* Gallery lightbox: darken the backdrop fully so nothing behind (e.g. the sticky price card) shows through on wide screens */
    .modal-backdrop.show { opacity: .92; }
    .gallery-nav-btn {
        top: 50%;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: .85;
        transform: translateY(-50%);
        transition: opacity .15s ease, left .15s ease, right .15s ease;
    }
    .gallery-nav-btn:hover { opacity: 1; }
    .gallery-nav-btn i { font-size: 1.25rem; }
</style>
@php
    $images = $room->images;
    $total = $images->count();

    $iconFor = function (string $inclusion): string {
        $key = strtolower($inclusion);

        return match (true) {
            str_contains($key, 'wifi') || str_contains($key, 'wi-fi') => 'bi-wifi',
            str_contains($key, 'aircon') || str_contains($key, 'ac ') || str_contains($key, 'air con') => 'bi-snow',
            str_contains($key, 'bed') => 'bi-house-door',
            str_contains($key, 'cr') || str_contains($key, 'sink') || str_contains($key, 'toilet') || str_contains($key, 'bath') => 'bi-droplet',
            str_contains($key, 'kitchen') || str_contains($key, 'cook') => 'bi-egg-fried',
            str_contains($key, 'parking') => 'bi-p-square',
            str_contains($key, 'tv') || str_contains($key, 'cable') => 'bi-tv',
            str_contains($key, 'balcony') || str_contains($key, 'window') => 'bi-brightness-high',
            default => 'bi-check-circle',
        };
    };
@endphp

<nav class="mb-3 small">
    <a href="{{ route('rooms.browse') }}" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to all rooms</a>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Room {{ $room->room_number }}</h1>
        <p class="text-muted mb-0">
            <i class="bi bi-geo-alt me-1"></i>{{ $room->property->name }} &mdash; {{ $room->property->address }}
            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($room->property->address) }}" target="_blank" rel="noopener" class="ms-1">Show on map</a>
        </p>
    </div>
    <x-status-badge :status="$room->status" />
</div>

{{-- Photo gallery --}}
@if ($total > 0)
    <div class="row g-2 mb-4" style="height: 420px;">
        <div class="col-md-8 h-100">
            <a href="#" class="d-block h-100" data-bs-toggle="modal" data-bs-target="#galleryModal" data-gallery-index="0">
                <img src="{{ $images[0]->url() }}" class="w-100 h-100 rounded" style="object-fit: cover;" alt="Room {{ $room->room_number }} main photo">
            </a>
        </div>
        <div class="col-md-4 h-100 d-none d-md-block">
            <div class="h-100" style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: .5rem;">
                @for ($i = 1; $i <= 4; $i++)
                    <div>
                        @if (isset($images[$i]))
                            <a href="#" class="d-block h-100 position-relative" data-bs-toggle="modal" data-bs-target="#galleryModal" data-gallery-index="{{ $i }}">
                                <img src="{{ $images[$i]->url() }}" class="w-100 h-100 rounded" style="object-fit: cover;" alt="Room {{ $room->room_number }} photo {{ $i + 1 }}">
                                @if ($i === 4 && $total > 5)
                                    <div class="position-absolute top-0 start-0 w-100 h-100 rounded bg-dark bg-opacity-50 d-flex align-items-center justify-content-center text-white fw-semibold">
                                        +{{ $total - 5 }} photos
                                    </div>
                                @endif
                            </a>
                        @else
                            <div class="bg-light h-100 rounded"></div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
    </div>

    @if ($total > 1)
        <div class="text-end mb-4 mt-n3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#galleryModal" data-gallery-index="0">
                <i class="bi bi-images me-1"></i>View all {{ $total }} photos
            </button>
        </div>
    @endif

    <div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen m-0">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0 position-absolute top-0 end-0 z-3">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 d-flex align-items-center justify-content-center overflow-hidden position-relative">
                    <div id="galleryCarousel" class="carousel slide w-100 h-100">
                        <div class="carousel-inner h-100">
                            @foreach ($images as $image)
                                <div class="carousel-item h-100 @if ($loop->first) active @endif">
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <img src="{{ $image->url() }}" class="gallery-photo" style="max-width: 100%; max-height: 100%; object-fit: contain;" alt="Room {{ $room->room_number }} photo {{ $loop->iteration }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($total > 1)
                        <button type="button" id="galleryPrevBtn" class="btn btn-dark rounded-circle gallery-nav-btn position-absolute" data-bs-target="#galleryCarousel" data-bs-slide="prev" aria-label="Previous photo">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" id="galleryNextBtn" class="btn btn-dark rounded-circle gallery-nav-btn position-absolute" data-bs-target="#galleryCarousel" data-bs-slide="next" aria-label="Next photo">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    <div class="d-flex align-items-center justify-content-center bg-light text-muted rounded mb-4" style="height: 420px;">
        <i class="bi bi-image fs-1"></i>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Quick facts --}}
        <div class="d-flex flex-wrap gap-4 py-3 mb-4 border-bottom">
            <div class="text-center">
                <i class="bi bi-layers fs-4 text-oasis d-block mb-1"></i>
                <span class="small text-muted">{{ $room->floorLabel() }}</span>
            </div>
            <div class="text-center">
                <i class="bi bi-door-closed fs-4 text-oasis d-block mb-1"></i>
                <span class="small text-muted">{{ ucfirst($room->type) }}</span>
            </div>
            @if ($room->size_sqm)
                <div class="text-center">
                    <i class="bi bi-arrows-angle-expand fs-4 text-oasis d-block mb-1"></i>
                    <span class="small text-muted">{{ $room->size_sqm }} sqm</span>
                </div>
            @endif
            <div class="text-center">
                <i class="bi bi-buildings fs-4 text-oasis d-block mb-1"></i>
                <span class="small text-muted">{{ $room->property->name }}</span>
            </div>
        </div>

        @if ($room->description)
            <section class="mb-4">
                <h2 class="h5 mb-2">About this room</h2>
                <p class="text-muted mb-0">{{ $room->description }}</p>
            </section>
        @endif

        @if (! empty($room->inclusions))
            <section class="mb-4">
                <h2 class="h5 mb-3">What this room offers</h2>
                <div class="row row-cols-2 row-cols-md-3 g-3">
                    @foreach ($room->inclusions as $inclusion)
                        <div class="col d-flex align-items-center gap-2">
                            <i class="bi {{ $iconFor($inclusion) }} text-oasis"></i>
                            <span>{{ $inclusion }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2 class="h5 mb-2">Property</h2>
            <p class="text-muted mb-0"><i class="bi bi-geo-alt me-1"></i>{{ $room->property->name }}, {{ $room->property->address }}</p>
        </section>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 1rem;">
            <div class="card-body p-4">
                @include('partials.upfront-breakdown', ['room' => $room])

                @if ($room->isVacant())
                    @auth
                        @if (auth()->user()->isTenant())
                            <a href="{{ route('tenant.bookings.create', $room) }}" class="btn btn-primary w-100"><i class="bi bi-calendar-check me-1"></i>Book This Room</a>
                        @else
                            <button class="btn btn-secondary w-100" disabled>Tenant accounts only</button>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary w-100">Login to Book</a>
                        <a href="{{ route('register') }}" class="btn btn-outline-secondary w-100 mt-2">Sign up to Book</a>
                    @endauth
                @else
                    <button class="btn btn-secondary w-100" disabled>Currently {{ $room->status }}</button>
                @endif

                @if ($similarAvailable > 0)
                    <p class="text-muted small text-center mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>{{ $similarAvailable }} similar room{{ $similarAvailable === 1 ? '' : 's' }} also available
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    const galleryModalEl = document.getElementById('galleryModal');
    const galleryBody = galleryModalEl?.querySelector('.modal-body');
    const galleryPrevBtn = document.getElementById('galleryPrevBtn');
    const galleryNextBtn = document.getElementById('galleryNextBtn');

    function positionGalleryNavButtons() {
        const activeImg = galleryModalEl?.querySelector('.carousel-item.active .gallery-photo');
        if (!activeImg || !galleryBody || !galleryPrevBtn || !galleryNextBtn) return;

        const imgRect = activeImg.getBoundingClientRect();
        const bodyRect = galleryBody.getBoundingClientRect();
        const inset = 16;

        galleryPrevBtn.style.left = Math.max(8, imgRect.left - bodyRect.left + inset) + 'px';
        galleryPrevBtn.style.right = 'auto';
        galleryNextBtn.style.right = Math.max(8, bodyRect.right - imgRect.right + inset) + 'px';
        galleryNextBtn.style.left = 'auto';
    }

    galleryModalEl?.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const index = trigger ? parseInt(trigger.dataset.galleryIndex ?? '0', 10) : 0;
        const carousel = bootstrap.Carousel.getOrCreateInstance(document.getElementById('galleryCarousel'));
        carousel.to(index);
    });

    galleryModalEl?.addEventListener('shown.bs.modal', positionGalleryNavButtons);
    galleryModalEl?.addEventListener('slid.bs.carousel', positionGalleryNavButtons);
    window.addEventListener('resize', positionGalleryNavButtons);

    if (window.ResizeObserver && galleryBody) {
        new ResizeObserver(positionGalleryNavButtons).observe(galleryBody);
    }
</script>
@endsection
