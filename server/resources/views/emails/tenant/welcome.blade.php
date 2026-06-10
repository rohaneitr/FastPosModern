@component('mail::message')
# Welcome to FastPOS, {{ $businessName }}!

Your enterprise workspace has been successfully provisioned and is ready for use.

### Your Secure Login Credentials
**Administrator Email:** {{ $ownerEmail }}
**Temporary Password:** `{{ $temporaryPassword }}`

**Subscribed Plan:** {{ $planName }}

@if($licenseKey)
**Device License Key (for Desktop/Native POS):** 
`{{ $licenseKey }}`
*(Use this key to authorize your local registers and offline nodes)*
@endif

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Access Your Dashboard
@endcomponent

> **Security Notice:** You are required to change your temporary password immediately upon your first login.

Thank you for choosing FastPOS,
**The {{ config('app.name') }} Infrastructure Team**
@endcomponent
