@props(['status'])

@php
    $color = match ($status) {
        'vacant', 'active', 'confirmed', 'paid', 'completed', 'approved', 'disbursed_manually', 'balance_paid' => 'success',
        'pending', 'pending_payment', 'in_progress', 'reserved', 'unpaid', 'calculated', 'awaiting_inspection' => 'warning',
        'grace_period', 'settled' => 'info',
        'overdue', 'cancelled', 'rejected', 'expired', 'deactivated', 'balance_due' => 'danger',
        'occupied', 'ended', 'transferred' => 'primary',
        default => 'secondary',
    };

    $label = ucfirst(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => "badge text-bg-{$color}"]) }}>{{ $label }}</span>
