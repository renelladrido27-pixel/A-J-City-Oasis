@props(['label', 'value', 'icon' => 'bi-bar-chart', 'color' => 'primary'])

<div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-{{ $color }}-subtle text-{{ $color }}" style="width: 3rem; height: 3rem; flex-shrink: 0;">
                <i class="bi {{ $icon }} fs-4"></i>
            </div>
            <div>
                <p class="text-muted mb-0 small">{{ $label }}</p>
                <p class="fs-3 fw-semibold mb-0">{{ $value }}</p>
            </div>
        </div>
    </div>
</div>
