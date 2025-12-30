<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing Request Cancelled</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 20px;
            margin-bottom: 30px;
        }
        .document-info h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #991b1b;
            text-transform: uppercase;
        }
        .document-info p {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
            font-weight: 600;
        }
        .reason-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .reason-box h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #1f2937;
        }
        .reason-box p {
            margin: 0;
            font-size: 15px;
            color: #4b5563;
            line-height: 1.6;
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>❌ Signing Request Cancelled</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Hello {{ $signerName }},</p>

            <p class="message">
                The signing request for the following document has been cancelled by the document owner.
                No action is required from you.
            </p>

            <!-- Document Info -->
            <div class="document-info">
                <h3>Document Name</h3>
                <p>{{ $documentName }}</p>
            </div>

            @if($cancellationReason)
            <!-- Cancellation Reason -->
            <div class="reason-box">
                <h4>Cancellation Reason</h4>
                <p>{{ $cancellationReason }}</p>
            </div>
            @endif

            <p class="message">
                The signing link you received is no longer valid. If you believe this was cancelled in error,
                please contact the document owner directly.
            </p>

            <p class="message" style="font-size: 14px; color: #9ca3af; margin-top: 30px;">
                <strong>Cancelled on:</strong> {{ $cancelledAt->format('F j, Y \a\t g:i A') }}
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
