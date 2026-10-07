@extends('layouts.app')

@section('title', 'Available Rooms')
@section('meta_description', 'Browse vacant rental rooms at A&amp;J CITY OASIS across two properties in Koronadal City, with transparent monthly pricing.')

@section('content')
<style>
    .room-browse-card {
        border-radius: .75rem;
        overflow: hidden;
        transition: transform .2s ease, box-shadow .2s ease;
        opacity: 0;
        animation: roomCardIn .45s ease forwards;
    }
    .room-browse-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 1rem 2rem rgba(0,0,0,.12) !important;
    }
    .room-browse-media { overflow: hidden; }
    .room-browse-media img { transition: transform .4s ease; }
    .room-browse-card:hover .room-browse-media img { transform: scale(1.07); }
    .room-browse-price { transition: color .15s ease; }
    .room-browse-card:hover .room-browse-price { color: var(--bs-primary); }
    @keyframes roomCardIn {
        from { opacity: 0; transform: translateY(14px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .room-browse-card { animation: none; opacity: 1; }
        .room-browse-card, .room-browse-card:hover, .room-browse-media img, .room-browse-card:hover .room-browse-media img { transition: none; transform: none; }
    }
</style>

<x-page-header title="Available Rooms" subtitle="Browse vacant rooms across our properties" />

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('rooms.browse') }}"
       class="btn btn-sm {{ request()->filled('property_id') ? 'btn-outline-secondary' : 'btn-primary' }}">
        All Properties
    </a>
    @foreach ($properties as $property)
        <a href="{{ route('rooms.browse', ['property_id' => $property->id]) }}"
           class="btn btn-sm {{ request()->integer('property_id') === $property->id ? 'btn-primary' : 'btn-outline-secondary' }}">
            <i class="bi bi-geo-alt me-1"></i>{{ $property->name }}
            @if ($property->address)
                <span class="d-none d-sm-inline" style="opacity: .75;">&middot; {{ $property->address }}</span>
            @endif
        </a>
    @endforeach
</div>

<div class="row g-3">
    @forelse ($rooms as $room)
        @php $images = $room->images; @endphp
        <div class="col-sm-6 col-lg-4">
            <div class="card room-browse-card h-100 border-0 shadow-sm" style="animation-delay: {{ min($loop->index * 0.05, 0.4) }}s;">
                <a href="{{ route('rooms.show', $room) }}" class="text-decoration-none text-reset">
                    @if ($images->isNotEmpty())
                        @if ($images->count() > 1)
                            <div id="roomCarousel{{ $room->id }}" class="carousel slide room-browse-media" data-bs-ride="false">
                                <div class="carousel-inner">
                                    @foreach ($images as $image)
                                        <div class="carousel-item @if ($loop->first) active @endif">
                                            <img src="{{ $image->url() }}" class="d-block w-100" style="height: 160px; object-fit: cover;" alt="Room {{ $room->room_number }} photo {{ $loop->iteration }}">
                                        </div>
                                    @endforeach
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#roomCarousel{{ $room->id }}" data-bs-slide="prev" onclick="event.preventDefault(); event.stopPropagation();">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#roomCarousel{{ $room->id }}" data-bs-slide="next" onclick="event.preventDefault(); event.stopPropagation();">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                                <div class="carousel-indicators" style="margin-bottom: 0.25rem;">
                                    @foreach ($images as $image)
                                        <button type="button" data-bs-target="#roomCarousel{{ $room->id }}" data-bs-slide-to="{{ $loop->index }}" @if ($loop->first) class="active" @endif onclick="event.stopPropagation();"></button>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="room-browse-media">
                                <img src="{{ $images->first()->url() }}" class="card-img-top" style="height: 160px; object-fit: cover;" alt="Room {{ $room->room_number }}">
                            </div>
                        @endif
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 160px;">
                            <i class="bi bi-image fs-1"></i>
                        </div>
                    @endif
                </a>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0"><a href="{{ route('rooms.show', $room) }}" class="text-reset text-decoration-none">Room {{ $room->room_number }}</a></h5>
                        <x-status-badge status="vacant" />
                    </div>
                    <p class="card-text text-muted mb-1 small"><i class="bi bi-geo-alt me-1"></i>{{ $room->property->name }}</p>
                    <p class="card-text mb-2 small">
                        {{ $room->floorLabel() }} &middot; {{ ucfirst($room->type) }}
                        @if ($room->size_sqm) &middot; {{ $room->size_sqm }} sqm @endif
                    </p>
                    @if (! empty($room->inclusions))
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach (array_slice($room->inclusions, 0, 3) as $inclusion)
                                <span class="badge text-bg-light border">{{ $inclusion }}</span>
                            @endforeach
                            @if (count($room->inclusions) > 3)
                                <span class="badge text-bg-light border">+{{ count($room->inclusions) - 3 }} more</span>
                            @endif
                        </div>
                    @endif
                    <p class="card-text fs-5 fw-semibold room-browse-price mb-1">₱{{ number_format($room->monthly_rate, 2) }} <span class="fs-6 fw-normal text-muted">/ month</span></p>
                    <p class="mb-3"><span class="badge rounded-pill" style="background: var(--oasis-gold, #c9963e); color: var(--oasis-ink, #1f2d27); font-size: .75rem;">₱{{ number_format($room->monthly_rate * 3, 2) }} to book</span> <span class="text-muted" style="font-size: .75rem;">3 months upfront</span></p>
                    <div class="mt-auto">
                        <a href="{{ route('rooms.show', $room) }}" class="btn btn-outline-primary btn-sm w-100">View Details</a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <x-empty-state icon="bi-door-closed" :message="request()->filled('property_id') ? 'No vacant rooms at this property right now.' : 'No vacant rooms right now.'" />
        </div>
    @endforelse
</div>
@endsection
