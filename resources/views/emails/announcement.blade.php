<x-mail::message>
# {{ $announcement->title }}

Hi {{ $tenant->name }},

{{ $announcement->body }}

<x-mail::button :url="route('tenant.announcements.index')">
View Announcements
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
