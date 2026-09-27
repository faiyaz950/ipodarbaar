<x-mail::message>
# Email is working

This test message was sent from **Admin → System** on {{ $sentAt->format('j M Y, g:i a') }}.

If it landed in spam, check the SPF and DKIM records for your domain (cPanel → Email Deliverability).

<x-mail::button :url="route('admin.system')">
Open System page
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
