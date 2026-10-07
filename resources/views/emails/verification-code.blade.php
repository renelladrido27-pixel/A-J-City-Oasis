<x-mail::message>
# Verify Your Email

Hi {{ $user->first_name ?: $user->name }},

Enter this code to verify your A & J OASIS account:

<x-mail::panel>
<div style="font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center;">{{ $code }}</div>
</x-mail::panel>

The code expires in {{ $expiresMinutes }} minutes. If you didn't create an account, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
