<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Signed Document is Ready</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .document-info {
            background-color: #f9fafb;
            border-left: 4px solid #7c3aed;
            padding: 20px;
            margin-bottom: 30px;
        }
        .document-info h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .document-info p {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
            font-weight: 600;
        }
        .cta-button {
            text-align: center;
            margin: 40px 0;
        }
        .cta-button a {
            display: inline-block;
            background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(124, 58, 237, 0.2);
        }
        .info-box {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .info-box-title {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .info-box-icon {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        .info-box h4 {
            margin: 0;
            font-size: 16px;
            color: #92400e;
            font-weight: 600;
        }
        .info-box p {
            margin: 0;
            font-size: 14px;
            color: #78350f;
            line-height: 1.5;
        }
        .verification-section {
            background-color: #eff6ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .verification-section h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #1e40af;
        }
        .verification-code {
            display: inline-block;
            background-color: #ffffff;
            border: 2px dashed #3b82f6;
            padding: 15px 30px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: 2px;
            margin: 10px 0;
        }
        .verification-link {
            display: block;
            margin-top: 15px;
            font-size: 14px;
        }
        .verification-link a {
            color: #2563eb;
            text-decoration: none;
        }
        .security-info {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .security-info h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #1f2937;
        }
        .security-info ul {
            margin: 0;
            padding-left: 20px;
        }
        .security-info li {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.8;
        }
        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .footer-logo {
            margin-bottom: 15px;
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        @media only screen and (max-width: 600px) {
            .header {
                padding: 30px 20px;
            }
            .header h1 {
                font-size: 24px;
            }
            .content {
                padding: 30px 20px;
            }
            .cta-button a {
                padding: 14px 30px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>✅ Document Signed Successfully</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Hello {{ $signerName }},</p>

            <p class="message">
                Great news! The document you signed has been completed by all parties and is now ready for download.
                Your signed copy is available below.
            </p>

            <!-- Document Info -->
            <div class="document-info">
                <h3>Document Name</h3>
                <p>{{ $documentName }}</p>
            </div>

            <!-- Download Button -->
            <div class="cta-button">
                <a href="{{ $downloadUrl }}" target="_blank">
                    📥 Download Signed Document
                </a>
            </div>

            <!-- Expiration Warning -->
            <div class="info-box">
                <div class="info-box-title">
                    <svg class="info-box-icon" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <h4>Download Link Expires</h4>
                </div>
                <p>
                    This download link will expire on <strong>{{ $expiresAt->format('F j, Y \a\t g:i A') }}</strong> (30 days from now).
                    Please download your document before this date.
                </p>
            </div>

            @if($verificationCode)
            <!-- Verification Section -->
            <div class="verification-section">
                <h4>🔐 Verification Code</h4>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #3b82f6;">
                    Use this code to verify the document's authenticity:
                </p>
                <div class="verification-code">{{ $verificationCode }}</div>
                @if($verificationUrl)
                <div class="verification-link">
                    <a href="{{ $verificationUrl }}" target="_blank">
                        Verify Document Online →
                    </a>
                </div>
                @endif
            </div>
            @endif

            <!-- Security Info -->
            <div class="security-info">
                <h4>📋 Document Features</h4>
                <ul>
                    <li><strong>Legally Binding:</strong> This document includes advanced electronic signatures compliant with eIDAS regulations</li>
                    <li><strong>Tamper-Proof:</strong> Any modification to the document will be detected and invalidated</li>
                    <li><strong>Audit Trail:</strong> Complete evidence package with timestamps and signer information is embedded</li>
                    <li><strong>Long-Term Validity:</strong> Qualified timestamps ensure legal validity for years to come</li>
                </ul>
            </div>

            <p class="message" style="margin-top: 30px;">
                If you have any questions or need assistance, please don't hesitate to contact support.
            </p>

            <p class="message" style="font-size: 14px; color: #9ca3af; margin-top: 30px;">
                <strong>Important:</strong> This email contains a secure download link. Do not forward this email to others.
                If you did not expect this email, please ignore it.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">Firmalum</div>
            <p>Advanced Electronic Signature Platform</p>
            <p>Process ID: {{ $processUuid }}</p>
            <p style="margin-top: 20px; font-size: 12px;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
