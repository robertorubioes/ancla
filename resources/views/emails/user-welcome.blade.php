<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $tenantName }}</title>
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
        .welcome-box {
            background: #f9fafb;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
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
            font-size: 18px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .tips-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Welcome to Firmalum!</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">You're all set to get started</p>
    </div>

    <div class="content">
        <h2>Hello {{ $userName }}!</h2>

        <div class="welcome-box">
            <h3 style="margin-top: 0; color: #10b981;">✨ Your Account is Ready!</h3>
            <p>You've successfully joined <strong>{{ $tenantName }}</strong> as a <span class="role-badge">{{ $role }}</span></p>
            
            <div style="margin-top: 20px;">
                <a href="{{ $loginUrl }}" class="cta-button">
                    Go to Dashboard →
                </a>
            </div>
        </div>

        <h3>Your Role: {{ $role }}</h3>
        
        @if($role === 'Administrator')
        <p>As an administrator, you have full control over your organization's account. You can:</p>
        <div class="features">
            <div class="feature-item">Invite and manage users</div>
            <div class="feature-item">Assign roles and permissions</div>
            <div class="feature-item">Create and manage signature processes</div>
            <div class="feature-item">Access all documents and audit trails</div>
            <div class="feature-item">Configure organization settings</div>
        </div>
        @elseif($role === 'Operator')
        <p>As an operator, you can manage documents and signature processes. You can:</p>
        <div class="features">
            <div class="feature-item">Create new signature processes</div>
            <div class="feature-item">Upload and manage documents</div>
            <div class="feature-item">Invite signers to documents</div>
            <div class="feature-item">View audit trails and reports</div>
            <div class="feature-item">Sign documents yourself</div>
        </div>
        @else
        <p>As a viewer, you can access and sign documents. You can:</p>
        <div class="features">
            <div class="feature-item">View assigned documents</div>
            <div class="feature-item">Sign documents electronically</div>
            <div class="feature-item">Download signed documents</div>
            <div class="feature-item">Access your document history</div>
        </div>
        @endif

        <div class="tips-box">
            <h4 style="margin-top: 0;">💡 Getting Started Tips</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Complete your profile information</li>
                <li>Set up two-factor authentication for extra security</li>
                <li>Explore the dashboard to familiarize yourself with the interface</li>
                <li>Check out our documentation and tutorials</li>
            </ul>
        </div>

        <h3>Need Help?</h3>
        <p>If you have any questions or need assistance getting started:</p>
        <ul>
            <li>Contact your organization administrator</li>
            <li>Visit our help center for guides and tutorials</li>
            <li>Reach out to our support team</li>
        </ul>

        <p>We're excited to have you on board!</p>

        <p>Best regards,<br><strong>The Firmalum Team</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated welcome message.</p>
        <p>&copy; {{ date('Y') }} Firmalum. All rights reserved.</p>
    </div>
</body>
</html>
