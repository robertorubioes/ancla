<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Suspended</title>
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
        .alert-box {
            background: #fef2f2;
            border: 2px solid #ef4444;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .alert-box h3 {
            margin-top: 0;
            color: #dc2626;
        }
        .info-item {
            margin: 15px 0;
            padding: 15px;
            background: #f9fafb;
            border-left: 4px solid #6b7280;
            border-radius: 4px;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            color: #111827;
        }
        .warning-section {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        .contact-box {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .contact-box h3 {
            color: #1e40af;
            margin-top: 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Organization Suspended</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Action Required</p>
    </div>

    <div class="content">
        <h2>Important Notice</h2>

        <p>This email is to inform you that your organization <strong>{{ $tenantName }}</strong> has been suspended.</p>

        <div class="alert-box">
            <h3>🚫 Access Restrictions</h3>
            <p style="margin-bottom: 0;">All users in your organization have been temporarily blocked from accessing the Firmalum platform. No new processes can be created until this suspension is resolved.</p>
        </div>

        <div class="info-item">
            <div class="info-label">Organization</div>
            <div class="info-value">{{ $tenantName }}</div>
        </div>

        <div class="info-item">
            <div class="info-label">Suspended On</div>
            <div class="info-value">{{ $suspendedAt }}</div>
        </div>

        <div class="info-item">
            <div class="info-label">Reason</div>
            <div class="info-value">{{ $reason }}</div>
        </div>

        <div class="warning-section">
            <strong>⚠️ What This Means:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>All users cannot log in to the platform</li>
                <li>No new signing processes can be created</li>
                <li>Existing processes remain paused</li>
                <li>Your data is secure and will not be deleted</li>
            </ul>
        </div>

        <div class="contact-box">
            <h3>📧 Need Help?</h3>
            <p>If you believe this suspension was made in error, or if you'd like to resolve this issue, please contact our support team immediately.</p>
            <p style="margin: 15px 0 0 0;">
                <strong>Support Email:</strong> support@ancla.app<br>
                <strong>Response Time:</strong> Within 24 hours
            </p>
        </div>

        <p>We're here to help resolve this matter as quickly as possible.</p>

        <p>Best regards,<br><strong>The Firmalum Team</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} Firmalum. All rights reserved.</p>
    </div>
</body>
</html>
