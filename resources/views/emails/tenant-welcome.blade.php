<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Firmalum</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .credentials-box {
            background: #f9fafb;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials-box h3 {
            margin-top: 0;
            color: #1e40af;
        }
        .credential-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .credential-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
        }
        .credential-value {
            font-size: 16px;
            color: #111827;
            font-family: 'Courier New', monospace;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
        }
        .info-section {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .features {
            margin: 20px 0;
        }
        .feature-item {
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }
        .feature-item:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Welcome to Firmalum</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Your organization is ready to start</p>
    </div>

    <div class="content">
        <h2>Hello {{ $adminName }}!</h2>

        <p>We're excited to welcome <strong>{{ $tenantName }}</strong> to Firmalum. Your organization has been successfully created and is ready to use.</p>

        <div class="credentials-box">
            <h3>🔐 Your Admin Credentials</h3>
            <p style="margin-bottom: 15px; color: #6b7280;">Use these credentials to access your organization's dashboard:</p>
            
            <div class="credential-item">
                <div class="credential-label">Organization URL</div>
                <div class="credential-value">{{ $tenantUrl }}</div>
            </div>

            <div class="credential-item">
                <div class="credential-label">Email</div>
                <div class="credential-value">{{ $adminEmail }}</div>
            </div>

            <div class="credential-item">
                <div class="credential-label">Temporary Password</div>
                <div class="credential-value">{{ $temporaryPassword }}</div>
            </div>
        </div>

        <div class="info-section">
            <strong>⚠️ Important:</strong> Please change your password immediately after your first login for security reasons.
        </div>

        <div style="text-align: center;">
            <a href="{{ $tenantUrl }}" class="cta-button">
                Access Your Dashboard →
            </a>
        </div>

        <h3>Your Plan: {{ $plan }}</h3>
        
        <div class="features">
            <div class="feature-item">Secure electronic signatures</div>
            <div class="feature-item">eIDAS compliant evidence capture</div>
            <div class="feature-item">Complete audit trail</div>
            <div class="feature-item">Public verification system</div>
            <div class="feature-item">Long-term document archiving</div>
        </div>

        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>

        <p>Best regards,<br><strong>The Firmalum Team</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} Firmalum. All rights reserved.</p>
    </div>
</body>
</html>
