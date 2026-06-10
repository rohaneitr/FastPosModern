@component('mail::message')
# Registration Update

Hello,

Thank you for your interest in FastPOS for **{{ $businessName }}**.

After careful review, we are currently unable to approve your workspace registration at this time.

**Reason:**
> {{ $rejectionReason }}

If you believe this was an error or would like to provide additional information, please reply to this email or contact our support team.

Best regards,
**The {{ config('app.name') }} Team**
@endcomponent
