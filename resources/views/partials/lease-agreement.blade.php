{{--
    The Terms and Conditions (Rental Agreement) text — see App\Support\RentalAgreement,
    which the app's terms screen also reads. Pass:
    - $room (required): the Room being booked/leased
    - $tenantName (optional): defaults to the current user's name
    - $startLabel (optional): how to describe the start date (e.g. "your selected move-in date")
--}}
@php
    $sections = \App\Support\RentalAgreement::sections(
        $room,
        $tenantName ?? auth()->user()?->name ?? 'the Tenant',
        $startLabel ?? 'the move-in date you select',
    );
@endphp

<div class="lease-agreement-text">
    <p class="text-muted small mb-3">A&amp;J CITY OASIS &mdash; {{ \App\Support\RentalAgreement::TITLE }} (Rental Agreement)</p>

    @foreach ($sections as $i => $section)
        <p><strong>{{ $i + 1 }}. {{ $section['heading'] }}.</strong> {{ $section['body'] }}</p>
    @endforeach
</div>
