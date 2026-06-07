@component('mail::message')
# Reset Your FastPOS Password

You are receiving this email because a password reset was requested for your account.

Click the button below to set a new password. This link expires in **60 minutes**.

@component('mail::button', ['url' => $resetUrl, 'color' => 'primary'])
Reset My Password
@endcomponent

If you did not request a password reset, no action is required — your account remains secure.

Thanks,
**{{ config('app.name') }} Team**
@endcomponent
