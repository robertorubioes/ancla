<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Dossier de Evidencias - Firmalum</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }

        .page {
            padding: 20mm;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }

        .logo {
            font-size: 24pt;
            font-weight: bold;
            color: #2563eb;
        }

        .title {
            font-size: 18pt;
            margin-top: 10px;
            color: #1e40af;
        }

        .subtitle {
            font-size: 10pt;
            color: #666;
            margin-top: 5px;
        }

        .verification-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .verification-code {
            font-family: monospace;
            font-size: 14pt;
            font-weight: bold;
            color: #0369a1;
            letter-spacing: 2px;
        }

        .section {
            margin: 25px 0;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .info-grid td {
            padding: 5px 10px;
            border-bottom: 1px solid #eee;
        }

        .info-grid td:first-child {
            font-weight: bold;
            color: #555;
            width: 30%;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8pt;
        }

        table.data-table th {
            background: #f1f5f9;
            padding: 8px 5px;
            text-align: left;
            border: 1px solid #e2e8f0;
            font-weight: bold;
        }

        table.data-table td {
            padding: 6px 5px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        table.data-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 8px;
            border-radius: 4px;
            font-size: 9pt;
            margin: 5px 0;
        }

        .success {
            color: #166534;
        }

        .danger {
            color: #dc2626;
        }

        .footer {
            position: fixed;
            bottom: 15mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .page-break {
            page-break-after: always;
        }

        .hash {
            font-family: monospace;
            font-size: 7pt;
            word-break: break-all;
            color: #666;
        }

        .timestamp {
            font-family: monospace;
            font-size: 9pt;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .legal-notice {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            margin-top: 30px;
            font-size: 8pt;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        // Extract evidence data for easier access
        $auditEntries = $evidence['audit_entries'] ?? collect();
        $devices = $evidence['devices'] ?? collect();
        $geolocations = $evidence['geolocations'] ?? collect();
        $ipRecords = $evidence['ip_records'] ?? collect();
        $consents = $evidence['consents'] ?? collect();
        
        // Determine what to include based on dossier type
        $includes = match($dossier_type ?? 'full_evidence') {
            'audit_trail' => [
                'audit_trail' => true,
                'device_info' => false,
                'geolocation' => false,
                'ip_info' => false,
                'consents' => false,
            ],
            'executive_summary' => [
                'audit_trail' => true,
                'device_info' => false,
                'geolocation' => false,
                'ip_info' => false,
                'consents' => true,
            ],
            default => [
                'audit_trail' => true,
                'device_info' => true,
                'geolocation' => true,
                'ip_info' => true,
                'consents' => true,
            ],
        };
    @endphp

    {{-- PAGE 1: COVER --}}
    <div class="page">
        <div class="header">
            <div class="logo">⚓ Firmalum</div>
            <div class="title">Dossier de Evidencias</div>
            <div class="subtitle">
                @switch($dossier_type ?? 'full_evidence')
                    @case('audit_trail')
                        Registro de Auditoría
                        @break
                    @case('full_evidence')
                        Evidencia Completa
                        @break
                    @case('legal_proof')
                        Prueba Legal
                        @break
                    @case('executive_summary')
                        Resumen Ejecutivo
                        @break
                @endswitch
            </div>
        </div>

        <div class="verification-box">
            <div style="font-size: 9pt; color: #666; margin-bottom: 5px;">Código de Verificación</div>
            <div class="verification-code">{{ $verification_code ?? 'XXXX-XXXX-XXXX' }}</div>
            <div style="font-size: 8pt; color: #666; margin-top: 10px;">
                Verifique la autenticidad en: {{ config('app.url') }}/verify
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información del Documento</div>
            <table class="info-grid">
                <tr>
                    <td>Fecha de Generación</td>
                    <td class="timestamp">{{ $generated_at->format('Y-m-d H:i:s T') }}</td>
                </tr>
                <tr>
                    <td>Tipo de Dossier</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $dossier_type ?? 'full_evidence')) }}</td>
                </tr>
                <tr>
                    <td>Tenant</td>
                    <td>{{ $tenant->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Documento</td>
                    <td>{{ class_basename($signable) }} #{{ $signable->getKey() }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Resumen de Evidencias</div>
            <table class="info-grid">
                <tr>
                    <td>Entradas de Auditoría</td>
                    <td>{{ $auditEntries->count() }}</td>
                </tr>
                <tr>
                    <td>Dispositivos Registrados</td>
                    <td>{{ $devices->count() }}</td>
                </tr>
                <tr>
                    <td>Registros de Geolocalización</td>
                    <td>{{ $geolocations->count() }}</td>
                </tr>
                <tr>
                    <td>Registros de IP</td>
                    <td>{{ $ipRecords->count() }}</td>
                </tr>
                <tr>
                    <td>Consentimientos</td>
                    <td>{{ $consents->count() }}</td>
                </tr>
            </table>
        </div>

        <div class="legal-notice">
            <strong>Aviso Legal:</strong> Este documento constituye un registro probatorio generado automáticamente 
            por el sistema Firmalum. Las evidencias contenidas cumplen con los requisitos del Reglamento eIDAS 
            (UE) 910/2014 para firmas electrónicas. La integridad del documento puede verificarse mediante 
            el código de verificación indicado.
        </div>
    </div>

    @if($includes['audit_trail'] && $auditEntries->count() > 0)
    <div class="page-break"></div>
    {{-- PAGE 2+: AUDIT TRAIL --}}
    <div class="page">
        <div class="section">
            <div class="section-title">Registro de Auditoría (Audit Trail)</div>
            <p style="font-size: 9pt; color: #666; margin-bottom: 15px;">
                Cadena de eventos verificable con hash encadenado para detectar manipulaciones.
            </p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 20%;">Timestamp</th>
                        <th style="width: 25%;">Evento</th>
                        <th style="width: 15%;">Actor</th>
                        <th style="width: 15%;">IP</th>
                        <th style="width: 20%;">Hash</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($auditEntries as $entry)
                    <tr>
                        <td>{{ $entry->sequence_number }}</td>
                        <td class="timestamp">{{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $entry->event_type }}</td>
                        <td>{{ $entry->actor_type }}{{ $entry->actor_id ? " #{$entry->actor_id}" : '' }}</td>
                        <td>{{ $entry->ip_address ?? '-' }}</td>
                        <td class="hash">{{ substr($entry->entry_hash, 0, 16) }}...</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($includes['device_info'] && $devices->count() > 0)
    <div class="page-break"></div>
    {{-- DEVICES PAGE --}}
    <div class="page">
        <div class="section">
            <div class="section-title">Dispositivos Identificados</div>
            <p style="font-size: 9pt; color: #666; margin-bottom: 15px;">
                Huellas digitales de los dispositivos utilizados durante el proceso de firma.
            </p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Firmante</th>
                        <th>Tipo</th>
                        <th>Navegador</th>
                        <th>SO</th>
                        <th>Pantalla</th>
                        <th>Fingerprint</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                    <tr>
                        <td>{{ $device->signer_email ?? '-' }}</td>
                        <td><span class="badge badge-success">{{ $device->device_type }}</span></td>
                        <td>{{ $device->browser_info ?? '-' }}</td>
                        <td>{{ $device->os_info ?? '-' }}</td>
                        <td>{{ $device->screen_resolution ?? '-' }}</td>
                        <td class="hash">{{ substr($device->fingerprint_hash, 0, 16) }}...</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($includes['geolocation'] && $geolocations->count() > 0)
    <div class="page-break"></div>
    {{-- GEOLOCATION PAGE --}}
    <div class="page">
        <div class="section">
            <div class="section-title">Registros de Geolocalización</div>
            <p style="font-size: 9pt; color: #666; margin-bottom: 15px;">
                Ubicación geográfica capturada durante el proceso de firma.
            </p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Firmante</th>
                        <th>Método</th>
                        <th>Latitud</th>
                        <th>Longitud</th>
                        <th>Precisión</th>
                        <th>Ubicación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($geolocations as $geo)
                    <tr>
                        <td>{{ $geo->signer_email ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $geo->isGps() ? 'badge-success' : 'badge-warning' }}">
                                {{ strtoupper($geo->capture_method) }}
                            </span>
                        </td>
                        <td>{{ $geo->effective_latitude ?? '-' }}</td>
                        <td>{{ $geo->effective_longitude ?? '-' }}</td>
                        <td>{{ $geo->accuracy_meters ? "{$geo->accuracy_meters}m" : ($geo->isIpFallback() ? '~50km' : '-') }}</td>
                        <td>{{ $geo->location ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($includes['ip_info'] && $ipRecords->count() > 0)
    <div class="page-break"></div>
    {{-- IP INFO PAGE --}}
    <div class="page">
        <div class="section">
            <div class="section-title">Información de Red</div>
            <p style="font-size: 9pt; color: #666; margin-bottom: 15px;">
                Direcciones IP y detección de servicios de anonimización.
            </p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Firmante</th>
                        <th>IP</th>
                        <th>DNS Inverso</th>
                        <th>ISP</th>
                        <th>VPN/Proxy</th>
                        <th>Riesgo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ipRecords as $ip)
                    <tr>
                        <td>{{ $ip->signer_email ?? '-' }}</td>
                        <td class="hash">{{ $ip->ip_address }}</td>
                        <td>{{ $ip->reverse_dns ?? '-' }}</td>
                        <td>{{ $ip->network_info ?? '-' }}</td>
                        <td>
                            @if($ip->isSuspicious())
                                <span class="badge badge-warning">
                                    @if($ip->is_vpn) VPN @endif
                                    @if($ip->is_proxy) Proxy @endif
                                    @if($ip->is_tor) TOR @endif
                                </span>
                            @else
                                <span class="badge badge-success">Directo</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $ip->isSuspicious() ? 'badge-danger' : 'badge-success' }}">
                                {{ ucfirst($ip->risk_level) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @foreach($ipRecords->filter(fn($r) => $r->isSuspicious()) as $ip)
            <div class="warning">
                <strong>⚠️ Advertencia:</strong> Se detectó conexión anonimizada para {{ $ip->signer_email }}.
                @foreach($ip->active_warnings as $warning)
                    {{ $warning }}.
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($includes['consents'] && $consents->count() > 0)
    <div class="page-break"></div>
    {{-- CONSENTS PAGE --}}
    <div class="page">
        <div class="section">
            <div class="section-title">Registros de Consentimiento</div>
            <p style="font-size: 9pt; color: #666; margin-bottom: 15px;">
                Aceptación explícita de términos legales con timestamp cualificado.
            </p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Firmante</th>
                        <th>Tipo</th>
                        <th>Acción</th>
                        <th>Timestamp</th>
                        <th>Hash Texto</th>
                        <th>TSA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($consents as $consent)
                    <tr>
                        <td>{{ $consent->signer_email }}</td>
                        <td>{{ ucfirst($consent->consent_type) }}</td>
                        <td>
                            <span class="badge {{ $consent->isAccepted() ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($consent->action) }}
                            </span>
                        </td>
                        <td class="timestamp">{{ $consent->action_timestamp->format('Y-m-d H:i:s') }}</td>
                        <td class="hash">{{ substr($consent->legal_text_hash, 0, 16) }}...</td>
                        <td>
                            @if($consent->hasTsa())
                                <span class="badge badge-success">✓</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- FINAL PAGE: CERTIFICATION --}}
    <div class="page-break"></div>
    <div class="page">
        <div class="section">
            <div class="section-title">Certificación</div>
            <p style="margin-bottom: 20px;">
                Este dossier ha sido generado automáticamente por el sistema Firmalum y está firmado 
                digitalmente por la plataforma.
            </p>

            <table class="info-grid">
                <tr>
                    <td>Algoritmo de Firma</td>
                    <td>HMAC-SHA256</td>
                </tr>
                <tr>
                    <td>Fecha de Firma</td>
                    <td class="timestamp">{{ $generated_at->format('Y-m-d H:i:s T') }}</td>
                </tr>
                <tr>
                    <td>Hash del Documento</td>
                    <td class="hash">Se calculará al generar el PDF</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Instrucciones de Verificación</div>
            <ol style="padding-left: 20px; font-size: 9pt;">
                <li style="margin-bottom: 8px;">
                    <strong>Verificación Online:</strong> Visite {{ config('app.url') }}/verify e introduzca 
                    el código de verificación mostrado en la portada.
                </li>
                <li style="margin-bottom: 8px;">
                    <strong>Verificación por QR:</strong> Escanee el código QR incluido en la portada del 
                    documento para acceder directamente a la página de verificación.
                </li>
                <li style="margin-bottom: 8px;">
                    <strong>Verificación Manual:</strong> Calcule el hash SHA-256 de este PDF y compárelo 
                    con el hash registrado en el sistema.
                </li>
            </ol>
        </div>

        <div class="legal-notice">
            <strong>Aviso de Integridad:</strong> Este documento electrónico tiene validez legal según el 
            Reglamento eIDAS (UE) 910/2014. Cualquier modificación del contenido invalidará las firmas y 
            sellos de tiempo asociados. La verificación de integridad puede realizarse en cualquier momento 
            mediante los métodos descritos anteriormente.
            <br><br>
            <strong>Protección de Datos:</strong> La información contenida en este documento está protegida 
            bajo el Reglamento General de Protección de Datos (RGPD). Su uso está limitado a los fines 
            establecidos en el proceso de firma correspondiente.
        </div>
    </div>

    <div class="footer">
        Generado por Firmalum - Sistema de Firma Electrónica | {{ $generated_at->format('d/m/Y H:i') }} | Página <span class="pagenum"></span>
    </div>
</body>
</html>
