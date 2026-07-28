<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP - {{ $appName }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #EAE6DA; color: #111110;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #EAE6DA; padding: 40px 15px;">
        <tr>
            <td align="center">
                <!-- Main Container Card -->
                <table role="presentation" width="100%" style="max-width: 540px; background-color: #ffffff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.08); overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06);">
                    
                    <!-- Header with Landing Page Dark Theme -->
                    <tr>
                        <td style="background-color: #111110; padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                {{ $appName }}
                            </h1>
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #6B6B65; font-weight: 500;">
                                Warehouse Management Platform
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 32px; background-color: #ffffff;">
                            <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #111110;">
                                Password Reset Request
                            </h2>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #4a4a45;">
                                Hello <strong>{{ $userName }}</strong>,<br>
                                We received a request to reset the password for your account. Use the 6-digit verification code below to complete your password reset:
                            </p>

                            <!-- OTP Badge Box (Landing Page Yellow Accent) -->
                            <div style="background-color: #FFF345; border-radius: 12px; padding: 22px 16px; text-align: center; margin: 24px 0; border: 1px solid rgba(0,0,0,0.1);">
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 36px; font-weight: 900; letter-spacing: 8px; color: #111110; display: inline-block;">
                                    {{ $otpCode }}
                                </span>
                            </div>

                            <p style="margin: 0 0 20px 0; font-size: 13px; line-height: 1.5; color: #6B6B65; text-align: center;">
                                ⏱️ This verification code will expire in <strong>{{ $expiresInMinutes }} minutes</strong>.
                            </p>

                            <div style="background-color: #F7F5F0; border-radius: 10px; padding: 16px; margin-top: 24px; border-left: 4px solid #111110;">
                                <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #6B6B65;">
                                    <strong>Security Notice:</strong> If you did not request a password reset, please ignore this email or contact your administrator immediately. Do not share this code with anyone.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #F7F5F0; padding: 20px 32px; text-align: center; border-top: 1px solid rgba(0,0,0,0.06);">
                            <p style="margin: 0; font-size: 12px; color: #6B6B65;">
                                © {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
