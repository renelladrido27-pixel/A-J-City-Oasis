@props(['status'])

@php
    $color = match ($status) {
        'vacant', 'active', 'confirmed', 'paid', 'completed', 'approved', 'disbursed_manually' => 'success',
        'pending', 'pending_payment', 'in_progress', 'reserved', 'unpaid', 'calculated' => 'warning',
        'grace_period' => 'info',
        'overdue', 'cancelled', 'rejected', 'expired', 'deactivated' => 'danger',
        'occupied', 'ended', 'transferred' => 'primary',
        default => 'secondary',
    };

    $label = ucfirst(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => "badge text-bg-{$color}"]) }}>{{ $label }}</span>
