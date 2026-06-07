<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to FastPOS</title>
</head>
<body style="margin:0;padding:0;background:#0f0f14;font-family:'Segoe UI',Arial,sans-serif;">

  <!-- Wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f14;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Logo header -->
        <tr>
          <td align="center" style="padding:0 0 32px 0;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:16px;padding:14px 20px;text-align:center;">
                  <span style="font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.5px;">⚡ FastPOS</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Main card -->
        <tr>
          <td style="background:#1a1a2e;border-radius:20px;border:1px solid rgba(124,58,237,0.2);overflow:hidden;">

            <!-- Green success banner -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:linear-gradient(135deg,#059669,#10b981);padding:32px;text-align:center;">
                  <div style="font-size:42px;margin-bottom:12px;">🎉</div>
                  <h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:800;letter-spacing:-0.5px;">
                    Your Account is Approved!
                  </h1>
                  <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:15px;">
                    Welcome aboard, {{ $businessName }}
                  </p>
                </td>
              </tr>
            </table>

            <!-- Body -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:36px 40px;">

                  <p style="margin:0 0 24px;color:#a1a1aa;font-size:15px;line-height:1.7;">
                    Congratulations! Your FastPOS account has been reviewed and approved by our team.
                    You are now on the <strong style="color:#a78bfa;">{{ $planName }}</strong> plan.
                    Your portal is ready — here are your credentials to get started.
                  </p>

                  <!-- Credentials box -->
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:#111827;border:1px solid rgba(124,58,237,0.25);border-radius:12px;margin-bottom:28px;overflow:hidden;">
                    <tr>
                      <td style="padding:14px 20px;border-bottom:1px solid rgba(124,58,237,0.15);">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;font-weight:700;">Login Credentials</p>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:20px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                          <tr>
                            <td style="padding:6px 0;">
                              <span style="color:#6b7280;font-size:13px;display:inline-block;width:110px;">Email</span>
                              <span style="color:#e5e7eb;font-size:13px;font-weight:600;">{{ $ownerEmail }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td style="padding:6px 0;">
                              <span style="color:#6b7280;font-size:13px;display:inline-block;width:110px;">Password</span>
                              <code style="color:#a78bfa;font-size:14px;font-weight:700;background:rgba(124,58,237,0.1);padding:3px 8px;border-radius:6px;letter-spacing:0.5px;">{{ $temporaryPassword }}</code>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>

                  <!-- License key box (only for hybrid/mobile) -->
                  @if($licenseKey)
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;border:1px solid rgba(16,185,129,0.3);border-radius:12px;margin-bottom:28px;overflow:hidden;">
                    <tr>
                      <td style="padding:14px 20px;border-bottom:1px solid rgba(16,185,129,0.15);background:rgba(16,185,129,0.05);">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#10b981;font-weight:700;">🔐 Cryptographic License Key</p>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:20px;">
                        <p style="margin:0 0 12px;color:#9ca3af;font-size:12px;line-height:1.6;">
                          This key authenticates your device installations. Store it securely — it is required for Hybrid/Mobile app activation.
                        </p>
                        <div style="background:#0f0f14;border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:14px;word-break:break-all;">
                          <code style="color:#34d399;font-size:11px;font-family:'Courier New',monospace;line-height:1.6;">{{ $licenseKey }}</code>
                        </div>
                      </td>
                    </tr>
                  </table>
                  @endif

                  <!-- Security notice -->
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:10px;margin-bottom:32px;">
                    <tr>
                      <td style="padding:16px 20px;">
                        <p style="margin:0;color:#fbbf24;font-size:13px;line-height:1.6;">
                          ⚠️ <strong>Security Notice:</strong> Please change your password immediately after your first login. Do not share your credentials with anyone.
                        </p>
                      </td>
                    </tr>
                  </table>

                  <!-- CTA Button -->
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td align="center">
                        <a href="{{ $loginUrl }}"
                           style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:16px 48px;border-radius:12px;letter-spacing:0.3px;box-shadow:0 8px 24px rgba(124,58,237,0.35);">
                          🚀 Sign In to FastPOS
                        </a>
                      </td>
                    </tr>
                  </table>

                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:28px 0;text-align:center;">
            <p style="margin:0 0 6px;color:#4b5563;font-size:12px;">
              © {{ date('Y') }} FastPOS. All rights reserved.
            </p>
            <p style="margin:0;color:#374151;font-size:11px;">
              If you did not apply for a FastPOS account, please ignore this email.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>
