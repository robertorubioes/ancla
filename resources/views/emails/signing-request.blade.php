<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Firma requerida: {{ $documentName }}</title>
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }
        
        /* General styles */
        body {
            background-color: #f4f4f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #f4f4f7;
            padding: 20px 0;
        }
        
        .email-content {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 2px;
            margin: 0;
        }
        
        .email-body {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #333333;
            margin: 0 0 20px 0;
        }
        
        .message {
            font-size: 16px;
            line-height: 1.6;
            color: #555555;
            margin: 0 0 25px 0;
        }
        
        .custom-message {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 25px 0;
            font-style: italic;
            color: #333333;
        }
        
        .info-box {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .info-item {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            color: #333333;
        }
        
        .info-value {
            color: #555555;
        }
        
        .deadline-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 30px 0;
            text-align: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .cta-button:hover {
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6);
        }
        
        .button-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        
        .link-fallback {
            font-size: 12px;
            color: #888888;
            margin-top: 15px;
            word-break: break-all;
        }
        
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer-text {
            font-size: 12px;
            color: #888888;
            margin: 5px 0;
        }
        
        .no-reply {
            font-size: 13px;
            color: #dc3545;
            font-weight: 600;
            margin-top: 15px;
        }
        
        .security-note {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
            color: #004085;
        }
        
        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-content {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            
            .email-header, .email-body, .email-footer {
                padding: 30px 20px !important;
            }
            
            .greeting {
                font-size: 20px !important;
            }
            
            .cta-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td align="center">
                    <div class="email-content">
                        <!-- Header -->
                        <div class="email-header">
                            <h1 class="logo">ANCLA</h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px;">
                                Firma Digital Segura
                            </p>
                        </div>
                        
                        <!-- Body -->
                        <div class="email-body">
                            <h2 class="greeting">Hola {{ $signer->name }},</h2>
                            
                            <p class="message">
                                <strong>{{ $promoterName }}</strong> te ha solicitado firmar el siguiente documento:
                            </p>
                            
                            <div class="info-box">
                                <div class="info-item">
                                    <span class="info-label">📄 Documento:</span>
                                    <span class="info-value">{{ $documentName }}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">👤 Solicitado por:</span>
                                    <span class="info-value">{{ $promoterName }}</span>
                                </div>
                                @if($hasDeadline)
                                <div class="info-item">
                                    <span class="info-label">⏰ Fecha límite:</span>
                                    <span class="info-value">{{ $deadline->format('d/m/Y H:i') }}</span>
                                </div>
                                @endif
                            </div>
                            
                            @if($customMessage)
                            <div class="custom-message">
                                <strong>Mensaje del promotor:</strong><br>
                                {{ $customMessage }}
                            </div>
                            @endif
                            
                            @if($hasDeadline)
                            <div class="deadline-warning">
                                <strong>⚠️ Atención:</strong> Este documento debe ser firmado antes del 
                                <strong>{{ $deadline->format('d/m/Y') }}</strong> a las 
                                <strong>{{ $deadline->format('H:i') }}</strong>.
                            </div>
                            @endif
                            
                            <div class="button-wrapper">
                                <a href="{{ $signingUrl }}" class="cta-button">
                                    🔒 Firmar Documento
                                </a>
                            </div>
                            
                            <div class="security-note">
                                <strong>🔐 Enlace seguro y único</strong><br>
                                Este enlace es único y personal. No lo compartas con nadie. El proceso de firma 
                                está protegido con evidencia legal de validez probatoria.
                            </div>
                            
                            <p class="link-fallback">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $signingUrl }}" style="color: #667eea; word-break: break-all;">{{ $signingUrl }}</a>
                            </p>
                        </div>
                        
                        <!-- Footer -->
                        <div class="email-footer">
                            <p class="footer-text">
                                Este correo ha sido enviado por <strong>ANCLA</strong><br>
                                Plataforma de Firma Digital con Valor Legal
                            </p>
                            <p class="no-reply">
                                ⚠️ No respondas a este correo electrónico
                            </p>
                            <p class="footer-text" style="margin-top: 20px;">
                                © {{ date('Y') }} ANCLA. Todos los derechos reservados.
                            </p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
