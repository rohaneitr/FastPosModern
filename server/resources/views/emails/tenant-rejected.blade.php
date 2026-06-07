<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Update — FastPOS</title>
</head>
<body style="margin:0;padding:0;background:#0f0f14;font-family:'Segoe UI',Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f14;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Logo -->
        <tr>
          <td align="center" style="padding:0 0 32px 0;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:16px;padding:14px 20px;">
                  <span style="font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.5px;">⚡ FastPOS</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Card -->
        <tr>
          <td style="background:#1a1a2e;border-radius:20px;border:1px solid rgba(239,68,68,0.2);overflow:hidden;">

            <!-- Header -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:linear-gradient(135deg,#1c1c2e,#2d1b1b);padding:32px;text-align:center;border-bottom:1px solid rgba(239,68,68,0.15);">
                  <div style="font-size:42px;margin-bottom:12px;">📋</div>
                  <h1 style="margin:0;color:#f1f5f9;font-size:24px;font-weight:800;">Application Status Update</h1>
                  <p style="margin:8px 0 0;color:#94a3b8;font-size:14px;">Regarding your FastPOS registration</p>
                </td>
              </tr>
            </table>

            <!-- Body -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:36px 40px;">

                  <p style="margin:0 0 20px;color:#a1a1aa;font-size:15px;line-height:1.7;">
                    Thank you for your interest in FastPOS, <strong style="color:#e5e7eb;">{{ $businessName }}</strong>.
                    After careful review, we are unable to approve your application at this time.
                  </p>

                  <!-- Reason box -->
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:#1f1120;border:1px solid rgba(239,68,68,0.25);border-radius:12px;margin-bottom:28px;overflow:hidden;">
                    <tr>
                      <td style="padding:14px 20px;border-bottom:1px solid rgba(239,68,68,0.15);background:rgba(239,68,68,0.06);">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#f87171;font-weight:700;">❌ Reason for Rejection</p>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:20px;">
                        <p style="margin:0;color:#e5e7eb;font-size:14px;line-height:1.8;">{{ $rejectionReason }}</p>
                      </td>
                    </tr>
                  </table>

                  <!-- What next -->
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.2);border-radius:10px;margin-bottom:32px;">
                    <tr>
                      <td style="padding:20px;">
                        <p style="margin:0 0 8px;color:#a5b4fc;font-size:13px;font-weight:700;">💡 What can you do next?</p>
                        <ul style="margin:0;padding-left:18px;color:#9ca3af;font-size:13px;line-height:1.9;">
                          <li>Review and address the reason listed above.</li>
                          <li>Ensure all required KYC documents are valid and up-to-date.</li>
                          <li>Re-submit your application once the issues are resolved.</li>
                        </ul>
                      </td>
                    </tr>
                  </table>

                  <!-- Support CTA -->
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td align="center">
                        <a href="{{ $supportUrl }}"
                           style="display:inline-block;background:#1e1e2e;border:1px solid rgba(124,58,237,0.4);color:#a78bfa;text-decoration:none;font-weight:600;font-size:14px;padding:14px 40px;border-radius:10px;">
                          Contact Support
                        </a>
                      </td>
                    </tr>
                  </table>

                  <p style="margin:28px 0 0;color:#6b7280;font-size:13px;line-height:1.7;text-align:center;">
                    We appreciate your interest and hope to work with you in the future.
                  </p>

                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:28px 0;text-align:center;">
            <p style="margin:0;color:#4b5563;font-size:12px;">© {{ date('Y') }} FastPOS. All rights reserved.</p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>
