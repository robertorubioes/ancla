# Security Audit Report - Sprint 2 Evidence Capture

> **Auditor:** Security Expert Agent  
> **Date:** 2025-12-28  
> **Scope:** Evidence Capture Components (E1-003, E1-004, E1-005, E1-010, E1-007)  
> **Status:** COMPLETED WITH FINDINGS

---

## Executive Summary

This security audit reviewed the Sprint 2 evidence capture code that handles sensitive data critical for eIDAS and GDPR compliance. The audit identified **3 HIGH**, **4 MEDIUM**, and **3 LOW** severity issues, along with several positive security implementations.

### Risk Rating Summary

| Severity | Count | Status |
|----------|-------|--------|
| CRITICAL | 0 | - |
| HIGH | 3 | Action Required |
| MEDIUM | 4 | Recommended Fix |
| LOW | 3 | Advisory |

---

## Positive Findings ✅

Before detailing vulnerabilities, the following security best practices were correctly implemented:

1. **Multi-tenancy Isolation** - `TenantScope` properly filters all evidence queries by `tenant_id`
2. **Cryptographic Hashing** - SHA-256 correctly used via `HashingService` for integrity verification
3. **Timing-Safe Comparisons** - `hash_equals()` used in [`verifySignature()`](app/Services/Evidence/EvidenceDossierService.php:291)
4. **XSS Protection in Views** - Blade templates use `{{ }}` escape syntax throughout [`dossier-pdf.blade.php`](resources/views/evidence/dossier-pdf.blade.php)
5. **TSA Integration** - Proper RFC 3161 timestamp implementation in [`TsaService`](app/Services/Evidence/TsaService.php)
6. **Audit Trail Integrity** - Blockchain-like chained hashes in [`AuditTrailService`](app/Services/Evidence/AuditTrailService.php)
7. **Environment Variables** - API keys properly externalized via `env()` in [`config/evidence.php`](config/evidence.php)

---

## Vulnerabilities Identified

### SEC-001: IP Address Spoofing via Header Trust [HIGH]

**Affected Files:**
- [`app/Services/Evidence/GeolocationService.php:114-131`](app/Services/Evidence/GeolocationService.php:114)
- [`app/Services/Evidence/IpResolutionService.php:102-116`](app/Services/Evidence/IpResolutionService.php:102)

**Description:**  
Both services blindly trust `X-Forwarded-For`, `X-Real-IP`, and `CF-Connecting-IP` headers without validation. An attacker can spoof these headers to:
1. Bypass VPN/Proxy/TOR detection
2. Inject malicious data into IP resolution records
3. Falsify geolocation evidence

**Current Code:**
```php
private function getRealIp(Request $request): string
{
    $headers = ['X-Real-IP', 'X-Forwarded-For', 'CF-Connecting-IP'];
    foreach ($headers as $header) {
        $ip = $request->header($header);
        if ($ip) {
            $ips = explode(',', $ip);
            return trim($ips[0]); // No validation!
        }
    }
    return $request->ip() ?? '127.0.0.1';
}
```

**Risk:** Compromises legal validity of evidence. VPN detection becomes useless.

**Remediation:**
1. Add IP address format validation using `filter_var()`
2. Configure trusted proxy middleware properly
3. Only trust headers from known reverse proxies

**Priority:** MUST FIX before production

---

### SEC-002: Missing Input Validation for Client Data [HIGH]

**Affected Files:**
- [`app/Services/Evidence/DeviceFingerprintService.php:21-77`](app/Services/Evidence/DeviceFingerprintService.php:21)

**Description:**  
The `capture()` method accepts `$clientData` array directly from the client without sanitization or validation. User-supplied strings are stored directly in the database:

- `timezone`, `language`, `platform` - No length limits
- `webgl_vendor`, `webgl_renderer` - Could contain malicious strings
- `raw_data` JSON - Stores entire unvalidated payload

**Risk:** 
- Storage of malicious payloads
- Potential for stored XSS if data is displayed elsewhere
- Database bloat through oversized inputs
- Fingerprint bypass through manipulation

**Remediation:**
1. Add validation rules for all client data fields
2. Implement length limits matching database column sizes
3. Sanitize string inputs
4. Validate hash formats (canvas_hash, audio_hash, fonts_hash)

**Priority:** MUST FIX before production

---

### SEC-003: SSRF Risk in External API Calls [HIGH]

**Affected Files:**
- [`app/Services/Evidence/GeolocationService.php:182-184`](app/Services/Evidence/GeolocationService.php:182)
- [`app/Services/Evidence/IpResolutionService.php:200-201`](app/Services/Evidence/IpResolutionService.php:200)

**Description:**  
IP addresses are interpolated directly into URLs without validation:

```php
$response = Http::timeout(10)
    ->get("https://ipapi.co/{$ipAddress}/json/");
```

If `$ipAddress` is not properly validated, an attacker could potentially:
1. Cause requests to internal services
2. Inject URL path traversal
3. Cause requests to arbitrary endpoints

**Remediation:**
1. Validate IP address format before use in URLs
2. Use URL encoding for path parameters
3. Add allowlist for target domains

**Priority:** HIGH - Fix immediately

---

### SEC-004: Missing Screenshot Content Validation [MEDIUM]

**Affected File:**
- [`app/Services/Evidence/ConsentCaptureService.php:186-220`](app/Services/Evidence/ConsentCaptureService.php:186)

**Description:**  
The `saveScreenshot()` method accepts base64 data without validating:
1. Actual MIME type of decoded content
2. Maximum file size
3. Image dimensions

```php
$imageData = base64_decode($base64Data);
if ($imageData === false) {
    throw new \InvalidArgumentException('Invalid base64 image data');
}
// No further validation - could be any binary data!
```

**Risk:**
- Arbitrary file storage
- Resource exhaustion via large files
- Storage of non-image content disguised as screenshots

**Remediation:**
1. Validate MIME type using `finfo_buffer()`
2. Add maximum size limit (e.g., 5MB)
3. Optionally validate image dimensions

---

### SEC-005: Missing Authorization Checks [MEDIUM]

**Affected Files:**
- All Evidence Services

**Description:**  
Evidence services create records without verifying the caller has authorization to access/modify the `$signable` model:

```php
public function capture(Request $request, Model $signable, ...): DeviceFingerprint
{
    // No check: Can caller access this $signable?
    $tenant = app('tenant');
    // Proceeds to create record...
}
```

**Risk:**
- Users could create evidence records for signables they don't own
- Cross-tenant data manipulation if tenant context is manipulated

**Remediation:**
1. Implement Policy checks for signable models
2. Verify caller's relationship to the signable
3. Add explicit tenant verification

---

### SEC-006: Potential Data Exposure in PDF [MEDIUM]

**Affected File:**
- [`resources/views/evidence/dossier-pdf.blade.php`](resources/views/evidence/dossier-pdf.blade.php)

**Description:**  
While Blade escaping is used, data from `raw_data` fields could contain sensitive information that shouldn't be displayed:

```blade
<td>{{ $entry->event_type }}</td>
<td>{{ $device->browser_info ?? '-' }}</td>
<td>{{ $ip->signer_email ?? '-' }}</td>
```

**Risk:**
- Sensitive data exposure in generated PDFs
- If data contains malicious HTML, DomPDF could be affected

**Remediation:**
1. Explicitly sanitize data before PDF generation
2. Limit displayed fields to approved list
3. Consider using `strip_tags()` as additional protection

---

### SEC-007: Missing Geolocation GPS Data Validation [MEDIUM]

**Affected File:**
- [`app/Services/Evidence/GeolocationService.php:53-56`](app/Services/Evidence/GeolocationService.php:53)

**Description:**  
GPS data from client is stored without validation:

```php
'latitude' => $gpsData['latitude'] ?? null,
'longitude' => $gpsData['longitude'] ?? null,
'accuracy_meters' => $gpsData['accuracy'] ?? null,
```

**Risk:**
- Invalid coordinates could be stored (lat > 90, lng > 180)
- Extremely large accuracy values could be injected
- Negative values where not appropriate

**Remediation:**
1. Validate latitude range: -90 to 90
2. Validate longitude range: -180 to 180
3. Validate accuracy as positive number

---

### SEC-008: API Rate Limiting Not Enforced [LOW]

**Affected File:**
- [`config/evidence.php:215-221`](config/evidence.php:215)

**Description:**  
Configuration includes rate limits but they are not enforced:

```php
'ipapi' => [
    'url' => 'https://ipapi.co/{ip}/json/',
    'rate_limit' => 1000, // Not enforced!
],
```

**Risk:**
- API quota exhaustion
- Unexpected billing from API providers
- Service denial if quota exceeded

**Remediation:**
1. Implement rate limiting using Laravel's RateLimiter
2. Cache API responses more aggressively
3. Add fallback mechanism when rate limited

---

### SEC-009: Raw Data Storage GDPR Concerns [LOW]

**Affected Files:**
- All Evidence Models with `raw_data` column

**Description:**  
Models store complete `raw_data` JSON which may contain:
- Excessive personal information
- Data not necessary for the stated purpose
- Information that should have retention limits

**Risk:**
- GDPR data minimization principle violation
- Subject Access Requests become complex
- Data retention compliance issues

**Remediation:**
1. Review and minimize stored data fields
2. Implement automatic data anonymization after retention period
3. Document data processing purposes
4. Add data export capability for SAR compliance

---

### SEC-010: Missing Request Source Validation [LOW]

**Affected File:**
- [`app/Services/Evidence/DeviceFingerprintService.php:202-346`](app/Services/Evidence/DeviceFingerprintService.php:202)

**Description:**  
The `getCollectorScript()` method generates JavaScript that collects fingerprint data. There's no mechanism to verify the script's integrity or that data came from legitimate sources.

**Risk:**
- Script could be modified by MITM attacks
- Fingerprint data could be fabricated by custom scripts

**Remediation:**
1. Use Subresource Integrity (SRI) for script loading
2. Add nonce-based validation
3. Consider server-side fingerprinting augmentation

---

## Compliance Assessment

### eIDAS Compliance

| Requirement | Status | Notes |
|-------------|--------|-------|
| Qualified Timestamps | ✅ | TSA integration with RFC 3161 |
| Document Integrity | ✅ | SHA-256 hashing implemented |
| Audit Trail | ✅ | Chained hashes for tamper detection |
| Evidence Integrity | ⚠️ | IP spoofing could compromise |
| Non-repudiation | ⚠️ | Client data manipulation possible |

### GDPR Compliance

| Requirement | Status | Notes |
|-------------|--------|-------|
| Data Minimization | ⚠️ | `raw_data` may store excessive info |
| Consent Recording | ✅ | ConsentRecord with TSA |
| Purpose Limitation | ✅ | Clear purpose in consent texts |
| Data Retention | ❌ | No automatic deletion mechanism |
| Right to Erasure | ⚠️ | Evidence retention conflicts |

---

## Recommended Actions

### Immediate (Before Production)

1. **SEC-001**: Implement IP validation and trusted proxy configuration
2. **SEC-002**: Add input validation for all client-supplied data
3. **SEC-003**: Validate IP format before external API calls

### Short Term (Sprint 3)

4. **SEC-004**: Add screenshot content validation
5. **SEC-005**: Implement authorization Policies for signables
6. **SEC-006**: Sanitize data in PDF generation

### Medium Term

7. **SEC-007**: Add GPS coordinate validation
8. **SEC-008**: Implement API rate limiting
9. **SEC-009**: Review and minimize stored data
10. **SEC-010**: Add request integrity validation

---

## Security Patches Applied

The following patches have been created to address HIGH severity issues:

1. ✅ `IpResolutionService` - Added `validateIpAddress()` method
2. ✅ `GeolocationService` - Added `validateIpAddress()` method  
3. ✅ `DeviceFingerprintService` - Added `validateClientData()` method
4. ✅ `ConsentCaptureService` - Added screenshot validation

---

## Conclusion

The Sprint 2 evidence capture implementation demonstrates solid security foundations with proper hashing, multi-tenancy, and TSA integration. However, **3 HIGH severity issues** related to input validation must be addressed before production deployment to ensure evidence integrity for legal compliance.

The identified vulnerabilities do not currently expose user data but could allow manipulation of evidence records, which is critical for the legal validity of the system.

**Recommendation:** Address HIGH severity items in hotfix release before Sprint 3 continues.

---

*Report generated by Security Expert Agent*  
*Next audit scheduled: After Sprint 3 completion*
