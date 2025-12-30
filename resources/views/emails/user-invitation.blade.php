<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've been invited to {{ $tenantName }}</title>
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
        .invitation-box {
            background: #f9fafb;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .invitation-box h3 {
            margin-top: 0;
            color: #1e40af;
        }
        .role-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin: 10px 0;
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
        .message-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            font-style: italic;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .detail-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .detail-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
        }
        .detail-value {
            font-size: 16px;
            color: #111827;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👋 You're Invited!</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Join {{ $tenantName }} on Firmalum</p>
    </div>

    <div class="content">
        <h2>Hello!</h2>

        <p><strong>{{ $inviterName }}</strong> has invited you to join <strong>{{ $tenantName }}</strong> on Firmalum as a <span class="role-badge">{{ $role }}</span></p>

        @if($message)
        <div class="message-box">
            <strong>Personal message from {{ $inviterName }}:</strong><br>
            "{{ $message }}"
        </div>
        @endif

        <div class="invitation-box">
            <h3>📋 What's Next?</h3>
            <p>Click the button below to accept your invitation and create your account. You'll be able to access all the features available for your role.</p>
            
            <div style="text-align: center;">
                <a href="{{ $invitationUrl }}" class="cta-button">
                    Accept Invitation →
                </a>
            </div>

            <div class="detail-item">
                <div class="detail-label">Your Role</div>
                <div class="detail-value">{{ $role }}</div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Organization</div>
                <div class="detail-value">{{ $tenantName }}</div>
            </div>
        </div>

        <div class="info-section">
            <strong>⏰ Important:</strong> This invitation will expire on <strong>{{ $expiresAt->format('F j, Y \a\t g:i A') }}</strong> ({{ $expiresAt->diffForHumans() }}). Please accept it before then.
        </div>

        <h3>About Your Role</h3>
        <p>As a <strong>{{ $role }}</strong>, you will be able to:</p>
        
        @if($role === 'Administrator')
        <ul>
            <li>Manage all users and their permissions</li>
            <li>Create and manage signature processes</li>
            <li>Access all documents and audit trails</li>
            <li>Configure organization settings</li>
        </ul>
        @elseif($role === 'Operator')
        <ul>
            <li>Create and manage signature processes</li>
            <li>Upload and manage documents</li>
            <li>View audit trails</li>
            <li>Sign documents</li>
        </ul>
        @else
        <ul>
            <li>View documents and signature processes</li>
            <li>Sign assigned documents</li>
            <li>Access your document history</li>
        </ul>
        @endif

        <p>If you have any questions, please contact {{ $inviterName }} or our support team.</p>

        <p>Best regards,<br><strong>The Firmalum Team</strong></p>
    </div>

    <div class="footer">
        <p>This invitation was sent by {{ $inviterName }} from {{ $tenantName }}.</p>
        <p>If you weren't expecting this invitation, you can safely ignore this email.</p>
        <p>&copy; {{ date('Y') }} Firmalum. All rights reserved.</p>
    </div>
</body>
</html>
