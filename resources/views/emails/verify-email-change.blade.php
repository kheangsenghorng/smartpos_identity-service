<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Confirm Your New Email Address - SmartPOS</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 40px 10px;">
        <tr>
            <td align="center">
                <!-- Email Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 520px; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5); overflow: hidden;">
                    
                    <!-- Header Bar with Gradient Accent -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #06b6d4 50%, #3b82f6 100%); height: 6px; font-size: 6px; line-height: 6px;">&nbsp;</td>
                    </tr>

                    <!-- Main Content Padding -->
                    <tr>
                        <td style="padding: 40px 36px 36px 36px;">
                            
                            <!-- Brand Header -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="left">
                                        <div style="display: inline-flex; align-items: center;">
                                            <span style="font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">Smart<span style="color: #10b981;">POS</span></span>
                                        </div>
                                    </td>
                                    <td align="right">
                                        <span style="background-color: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px;">Email Change</span>
                                    </td>
                                </tr>
                            </table>

                            <div style="height: 32px; line-height: 32px;">&nbsp;</div>

                            <!-- Title Section -->
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #f8fafc; line-height: 1.3;">
                                Confirm Your New Email Address
                            </h1>

                            <p style="margin: 12px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                Hello <strong style="color: #f1f5f9;">{{ $user->name ?? 'there' }}</strong>,
                            </p>

                            <p style="margin: 10px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                You recently requested to change the primary email address for your <strong style="color: #cbd5e1;">SmartPOS</strong> account to <strong style="color: #38bdf8;">{{ $pendingEmail }}</strong>.
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                Please confirm this update by clicking the confirmation button below:
                            </p>

                            <div style="height: 28px; line-height: 28px;">&nbsp;</div>

                            <!-- Verification Button Box -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="text-align: center;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $verificationUrl }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 36px; border-radius: 10px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); letter-spacing: 0.3px;">
                                            Confirm Email Address
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="height: 28px; line-height: 28px;">&nbsp;</div>

                            <!-- Expiration Alert Box -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border-radius: 10px; border: 1px solid #334155; padding: 14px 16px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                                            ⏱️ <strong style="color: #fbbf24;">Security Notice:</strong> This confirmation link will expire in <strong style="color: #f8fafc;">{{ $expiresInMinutes }} minutes</strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <div style="height: 20px; line-height: 20px;">&nbsp;</div>

                            <!-- Fallback Link Section -->
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.6;">
                                If you're having trouble clicking the button, copy and paste the following URL into your web browser:
                            </p>
                            <p style="margin: 8px 0 0 0; font-size: 11px; word-break: break-all; color: #38bdf8; background: #0f172a; padding: 10px 12px; border-radius: 8px; border: 1px solid #334155;">
                                <a href="{{ $verificationUrl }}" style="color: #38bdf8; text-decoration: underline;">{{ $verificationUrl }}</a>
                            </p>

                            <div style="height: 28px; line-height: 28px;">&nbsp;</div>

                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                                If you did not initiate this email change request, please sign in to your SmartPOS account immediately and change your password.
                            </p>

                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td style="padding: 24px 36px; background-color: #0b1120; border-top: 1px solid #1e293b; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #475569;">
                                &copy; {{ date('Y') }} SmartPOS Ecosystem. All rights reserved.
                            </p>
                            <p style="margin: 6px 0 0 0; font-size: 11px; color: #334155;">
                                This is an automated security notification.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
