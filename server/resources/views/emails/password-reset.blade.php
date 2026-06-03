@component('mail::message')
# Reset Your Password

You are receiving this email because we received a password reset request for your account.

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

If you did not request a password reset, no further action is required.

**Your reset token:** `{{ $resetToken }}`

This token will expire in 1 hour.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
