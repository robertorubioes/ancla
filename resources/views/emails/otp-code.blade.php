<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 100%); padding: 40px 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                🔐 Verification Code
                            </h1>
                        </td>
                    </tr>

                    {{-- Content --}}
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                Hello <strong>{{ $signer->name }}</strong>,
                            </p>

                            <p style="margin: 0 0 30px; color: #374151; font-size: 16px; line-height: 1.6;">
                                You requested a verification code to sign a document. Use the code below to continue:
                            </p>

                            {{-- OTP Code Box --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td style="background-color: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; padding: 30px; text-align: center;">
                                        <div style="font-size: 48px; font-weight: 800; color: #1f2937; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                                            {{ $code }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Expiration Notice --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 4px;">
                                        <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.5;">
                                            ⏱️ <strong>This code expires in {{ $expiresMinutes }} minutes</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                If you didn't request this code, please ignore this email.
                            </p>
                        </td>
                    </tr>

                    {{-- Security Warning --}}
                    <tr>
                        <td style="background-color: #fef2f2; padding: 20px 40px; border-top: 1px solid #fee2e2;">
                            <p style="margin: 0; color: #991b1b; font-size: 13px; line-height: 1.5; text-align: center;">
                                <strong>⚠️ Security Warning:</strong> Never share this code with anyone. Our team will never ask for your verification code.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px; color: #6b7280; font-size: 13px;">
                                This is an automated message from <strong>Firmalum</strong>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Secure Digital Signature Platform
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
