{{--
    What a tenant pays to book, made hard to miss (panel feedback): the full
    3-month upfront total and its parts, then the ongoing monthly rent.
    @include('partials.upfront-breakdown', ['room' => $room])
--}}
@php
    $monthly = (float) $room->monthly_rate;
    $upfront = $monthly * 3;
@endphp

<div class="upfront-breakdown mb-3">
    <div class="upfront-total">
        <div class="small text-uppercase fw-semibold" style="letter-spacing: .04em;">Pay today to book</div>
        <div class="upfront-amount">₱{{ number_format($upfront, 2) }}</div>
        <div class="small">3 months upfront &mdash; paid once, when you book</div>
    </div>
    <ul class="list-unstyled mb-0 upfront-lines">
        <li>
            <span><strong>1 month advance</strong><br><span class="text-muted small">Your first month's rent</span></span>
            <span class="fw-semibold">₱{{ number_format($monthly, 2) }}</span>
        </li>
        <li>
            <span><strong>1 month deposit</strong><br><span class="text-muted small">Applied as rent &middot; not refundable</span></span>
            <span class="fw-semibold">₱{{ number_format($monthly, 2) }}</span>
        </li>
        <li>
            <span><strong>Security deposit</strong><br><span class="text-muted small">Refundable at move-out, less any damages</span></span>
            <span class="fw-semibold">₱{{ number_format($monthly, 2) }}</span>
        </li>
    </ul>
    <div class="upfront-monthly">
        <span><i class="bi bi-calendar-month me-1"></i>Then, monthly rent</span>
        <span class="fw-bold">₱{{ number_format($monthly, 2) }} <span class="fw-normal small">/ month</span></span>
    </div>
</div>

@once
    <style>
        .upfront-breakdown { border: 2px solid var(--oasis-gold, #c9963e); border-radius: .75rem; overflow: hidden; background: #fff; }
        .upfront-total { background: var(--oasis-green, #1b4332); color: #fff; padding: .9rem 1rem; text-align: center; }
        .upfront-amount { font-size: 2rem; font-weight: 800; line-height: 1.15; color: var(--oasis-gold, #c9963e); }
        .upfront-lines li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .6rem 1rem; border-bottom: 1px solid #eef0ee; line-height: 1.25; }
        .upfront-monthly { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .7rem 1rem; background: var(--oasis-sand, #faf6ee); }
    </style>
@endonce
