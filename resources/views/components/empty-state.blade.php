@props(['icon' => 'bi-inbox', 'message' => 'Nothing here yet.'])

<div class="text-center text-muted py-5">
    <i class="bi {{ $icon }} d-block mb-2" style="font-size: 2rem;"></i>
    <p class="mb-0">{{ $message }}</p>
</div>
