<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Password Reset Verification Code') }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 520px; background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); overflow: hidden;" cellspacing="0" cellpadding="0">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 32px 32px 24px; text-align: center; background: linear-gradient(135deg, #5b3df5 0%, #7c5cff 100%);">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">
                                {{ config('app.name', 'ERP Portal') }}
                            </h1>
                            <p style="margin: 8px 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.85);">
                                {{ __('Password Reset Request') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 32px 28px;">
                            <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #334155;">
                                {{ __('Hello') }} {{ $userName ? $userName . ',' : '' }}
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #334155;">
                                {{ __('We received a request to reset your password. Use the verification code below to verify your identity and complete the process.') }}
                            </p>

                            <!-- OTP Box -->
                            <div style="background-color: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; margin: 28px 0;">
                                <span style="display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin-bottom: 8px;">
                                    {{ __('Your One-Time Password (OTP)') }}
                                </span>
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 800; letter-spacing: 10px; color: #5b3df5;">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p style="margin: 0 0 12px; font-size: 13.5px; line-height: 1.5; color: #64748b;">
                                ⏱️ {{ __('This code will expire in') }} <strong>{{ $expiresInMinutes ?? 10 }} {{ __('minutes') }}</strong>.
                            </p>
                            <p style="margin: 0 0 20px; font-size: 13.5px; line-height: 1.5; color: #64748b;">
                                🔒 {{ __('For your security, never share this code with anyone. Our support team will never ask for your code.') }}
                            </p>

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

                            <p style="margin: 0; font-size: 12.5px; line-height: 1.5; color: #94a3b8;">
                                {{ __('If you did not request a password reset, no further action is required. Your account remains secure.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'ERP Portal') }}. {{ __('All rights reserved.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

