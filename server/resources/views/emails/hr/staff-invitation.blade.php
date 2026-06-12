<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #10b981; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
        .footer { font-size: 12px; color: #6b7280; margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome to FastPOS</h2>
        <p>You have been invited to join <strong>{{ $businessName }}</strong> as a <strong>{{ $role }}</strong>.</p>
        
        <p>To accept this invitation and set up your account, please click the button below:</p>
        
        <a href="{{ $registrationUrl }}" class="btn">Accept Invitation</a>
        
        <p>If you're having trouble clicking the button, copy and paste this URL into your web browser:</p>
        <p style="word-break: break-all; color: #3b82f6;">{{ $registrationUrl }}</p>
        
        <p>This invitation will expire in 7 days.</p>
        
        <div class="footer">
            <p>This is an automated message from FastPOS. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
