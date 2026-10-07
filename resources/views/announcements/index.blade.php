@extends('layouts.app')

@section('title', 'Announcements')

@section('content')
<x-page-header title="Announcements" subtitle="Updates from A & J CITY OASIS management" />

<div class="list-group shadow-sm">
    @forelse ($announcements as $announcement)
        <div class="list-group-item py-3">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="mb-1 h6"><i class="bi bi-megaphone me-1 text-primary"></i>{{ $announcement->title }}</h2>
                <small class="text-muted">{{ $announcement->created_at->diffForHumans() }}</small>
            </div>
            <p class="mb-1">{{ $announcement->body }}</p>
            <p class="text-muted small mb-0">— {{ $announcement->author->name }}</p>
        </div>
    @empty
        <div class="list-group-item">
            <x-empty-state icon="bi-megaphone" message="No announcements right now." />
        </div>
    @endforelse
</div>

<div class="mt-3">{{ $announcements->links() }}</div>
@endsection
