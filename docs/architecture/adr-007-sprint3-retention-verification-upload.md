# ADR-007: Sprint 3 - Long-term Retention, Public Verification & Document Upload

- **Estado**: Aceptado
- **Fecha**: 2025-12-28
- **Backlog Items**: E1-008, E1-009, E2-001
- **Autor**: Arquitecto de Software
- **Prioridad**: CRÍTICA (E1-008, E1-009), ALTA (E2-001)
- **Dependencias**: ADR-005 (Sistema de Evidencias Core), ADR-006 (Captura Avanzada)

## Contexto

El Sprint 3 aborda tres funcionalidades fundamentales para la plataforma ANCLA:

1. **E1-008 - Conservación de Evidencias 5+ Años**: La normativa eIDAS y las regulaciones de firma electrónica avanzada requieren conservar las evidencias durante un mínimo de 5 años. Necesitamos una estrategia de preservación a largo plazo que garantice la integridad criptográfica en el tiempo.

2. **E1-009 - Verificación Pública de Integridad**: Los firmantes y terceros deben poder verificar la autenticidad de documentos firmados sin necesidad de autenticación, mediante un código único o QR.

3. **E2-001 - Subida de Documentos PDF**: Primera funcionalidad de usuario final para subir documentos PDF para firma, con validación, almacenamiento seguro y multi-tenancy.

## Requisitos Legales

| Requisito | Normativa | Solución Técnica |
|-----------|-----------|------------------|
| Conservación mínima 5 años | eIDAS Art. 34, Ley 59/2003 Art. 6 | Archivo con re-sellado TSA periódico |
| Integridad verificable | eIDAS Art. 26 | Hash chains + TSA cualificado |
| Acceso a verificación | Reglamento eIDAS Art. 24 | API pública sin autenticación |
| Formato preservable | ETSI EN 319 122-1 | PDF/A para archivo largo plazo |
| Aislamiento de datos | RGPD Art. 32 | Multi-tenant con cifrado |

---

## E1-008: Conservación de Evidencias 5+ Años

### Problemática

Los timestamps TSA tienen validez limitada por:
1. **Expiración de certificados**: Los certificados TSA típicamente expiran en 1-3 años
2. **Debilitamiento criptográfico**: Algoritmos pueden volverse vulnerables con el tiempo
3. **Obsolescencia de formatos**: Los formatos de almacenamiento pueden quedar obsoletos

### Estrategia de Preservación a Largo Plazo (LTA - Long Term Archival)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                    ESTRATEGIA DE PRESERVACIÓN A LARGO PLAZO                          │
└─────────────────────────────────────────────────────────────────────────────────────┘

     EVIDENCIA ORIGINAL                    RE-SELLADO PERIÓDICO
     ─────────────────                     ────────────────────
     
     ┌─────────────────┐      Año 1       ┌─────────────────┐
     │   Documento     │─────────────────▶│   Documento     │
     │   Hash: ABC123  │                  │   Hash: ABC123  │
     │   TSA: 2025     │                  │   TSA: 2025     │
     │                 │                  │   TSA: 2026     │──┐
     └─────────────────┘                  └─────────────────┘  │
                                                               │
                                           Año 2               │
                                          ┌─────────────────┐  │
                                          │   Documento     │  │
                                          │   Hash: ABC123  │  │
                                          │   TSA: 2025     │◀─┘
                                          │   TSA: 2026     │
                                          │   TSA: 2027     │──┐
                                          └─────────────────┘  │
                                                               │
                                           ...continúa...      │
                                          ┌─────────────────┐  │
                                          │   Año 5+        │◀─┘
                                          │   Cadena TSA    │
                                          │   completa      │
                                          └─────────────────┘

     CADENA DE CUSTODIA:
     ┌──────┐   ┌──────┐   ┌──────┐   ┌──────┐   ┌──────┐
     │ TSA₀ │──▶│ TSA₁ │──▶│ TSA₂ │──▶│ TSA₃ │──▶│ TSA₄ │──▶ ...
     │ 2025 │   │ 2026 │   │ 2027 │   │ 2028 │   │ 2029 │
     └──────┘   └──────┘   └──────┘   └──────┘   └──────┘
         │                                           │
         └──────── Hash incluye TSA anterior ────────┘
```

### Proceso de Re-Sellado TSA

El re-sellado TSA crea una cadena de timestamps donde cada nuevo sello incluye:
1. El hash original del documento
2. Los sellos TSA anteriores
3. Un nuevo timestamp cualificado

```
Re-sello N = TSA(Hash(Documento + TSA₀ + TSA₁ + ... + TSAₙ₋₁))
```

### Diagrama de Arquitectura - Archivo a Largo Plazo

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         SISTEMA DE ARCHIVO A LARGO PLAZO                             │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              CAPA DE SERVICIOS                                        │
├─────────────────┬─────────────────┬─────────────────┬───────────────────────────────┤
│ LongTermArchive │ TsaResealService│ FormatMigration │ RetentionPolicyService        │
│ Service         │                 │ Service         │                               │
│                 │                 │                 │                               │
│ - archive()     │ - reseal()      │ - migrate()     │ - shouldArchive()             │
│ - retrieve()    │ - scheduleAll() │ - checkFormat() │ - getRetentionPeriod()        │
│ - verify()      │ - verifyChain() │ - convertPDF/A()│ - scheduleArchival()          │
└────────┬────────┴────────┬────────┴────────┬────────┴──────────────┬────────────────┘
         │                 │                 │                       │
         └─────────────────┴─────────────────┴───────────────────────┘
                                    │
                                    ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              CAPA DE ALMACENAMIENTO                                   │
├────────────────────────┬──────────────────────────────────────────────────────────────┤
│   HOT STORAGE          │              COLD STORAGE (Archivo)                          │
│   (< 1 año)            │              (1-10+ años)                                    │
├────────────────────────┼──────────────────────────────────────────────────────────────┤
│  ┌──────────────────┐  │  ┌──────────────────┐  ┌──────────────────┐                 │
│  │ documents        │  │  │ archived_        │  │ tsa_chains       │                 │
│  │ ├─ file          │  │  │ documents        │  │ ├─ original_tsa  │                 │
│  │ ├─ hash          │  │  │ ├─ uuid          │  │  ├─ reseal_tsa[] │                 │
│  │ └─ tsa_token     │  │  │ ├─ original_doc  │  │  └─ next_reseal  │                 │
│  └──────────────────┘  │  │ ├─ pdf_a_version │  └──────────────────┘                 │
│                        │  │ └─ metadata      │                                       │
│  Local SSD / NVMe      │  └──────────────────┘                                       │
│                        │                                                             │
│                        │  Object Storage (S3/GCS/Azure Blob) con:                    │
│                        │  - Encryption at rest (AES-256)                             │
│                        │  - Versioning habilitado                                     │
│                        │  - Cross-region replication                                  │
│                        │  - Lifecycle policies                                        │
└────────────────────────┴──────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                         SCHEDULER (CRON JOBS)                                         │
├───────────────────────────────────────────────────────────────────────────────────────┤
│  ┌────────────────────┐  ┌────────────────────┐  ┌────────────────────┐              │
│  │ Daily              │  │ Monthly            │  │ Yearly             │              │
│  │ ────────────────   │  │ ────────────────   │  │ ────────────────   │              │
│  │ - Verify integrity │  │ - TSA re-seal      │  │ - Format migration │              │
│  │ - Check expirations│  │ - Move to cold     │  │ - Algorithm review │              │
│  │ - Backup verify    │  │ - Generate reports │  │ - Compliance audit │              │
│  └────────────────────┘  └────────────────────┘  └────────────────────┘              │
└───────────────────────────────────────────────────────────────────────────────────────┘
```

### Estructura de Base de Datos - Archivo

#### Tabla `archived_documents`

```sql
CREATE TABLE archived_documents (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    
    -- Referencia al documento original
    original_document_id    BIGINT UNSIGNED NOT NULL,
    original_document_uuid  CHAR(36) NOT NULL,
    
    -- Archivo físico
    storage_driver          VARCHAR(50) NOT NULL,                -- 'local', 's3', 'gcs', 'azure'
    storage_path            VARCHAR(500) NOT NULL,
    storage_bucket          VARCHAR(100) NULL,
    
    -- Formato y versiones
    current_format          VARCHAR(20) NOT NULL DEFAULT 'PDF',   -- 'PDF', 'PDF/A-3', etc.
    pdfa_version            VARCHAR(20) NULL,                     -- 'PDF/A-3b', 'PDF/A-3u'
    format_migrated_at      TIMESTAMP NULL,
    
    -- Integridad
    content_hash            CHAR(64) NOT NULL,
    hash_algorithm          VARCHAR(20) DEFAULT 'SHA-256',
    archive_hash            CHAR(64) NOT NULL,                    -- Hash del paquete completo
    
    -- Metadata del documento original
    original_filename       VARCHAR(255) NOT NULL,
    original_size_bytes     BIGINT UNSIGNED NOT NULL,
    original_created_at     TIMESTAMP NOT NULL,
    mime_type               VARCHAR(100) NOT NULL,
    
    -- Metadatos adicionales (JSON)
    metadata                JSON NULL,
    
    -- Estado del archivo
    archive_status          ENUM('pending', 'active', 'migrating', 'expired', 'deleted') DEFAULT 'pending',
    
    -- Fechas clave
    archived_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retention_until         TIMESTAMP NOT NULL,                   -- Fecha hasta la que debe conservarse
    last_verified_at        TIMESTAMP NULL,
    last_accessed_at        TIMESTAMP NULL,
    
    -- TSA chain
    initial_tsa_token_id    BIGINT UNSIGNED NULL,
    current_tsa_chain_id    BIGINT UNSIGNED NULL,
    next_reseal_due         TIMESTAMP NULL,
    reseal_count            INT UNSIGNED DEFAULT 0,
    
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_archived_tenant (tenant_id),
    INDEX idx_archived_original (original_document_id),
    INDEX idx_archived_status (archive_status),
    INDEX idx_archived_retention (retention_until),
    INDEX idx_archived_reseal (next_reseal_due),
    INDEX idx_archived_hash (content_hash),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### Tabla `tsa_chains` (Cadenas de Re-sellado)

```sql
CREATE TABLE tsa_chains (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    
    -- Referencia al documento archivado
    archived_document_id    BIGINT UNSIGNED NOT NULL,
    
    -- Tipo de cadena
    chain_type              ENUM('document', 'audit_trail', 'evidence_package') NOT NULL,
    
    -- Hash que se está preservando
    preserved_hash          CHAR(64) NOT NULL,
    hash_algorithm          VARCHAR(20) DEFAULT 'SHA-256',
    
    -- Primer TSA (original)
    initial_tsa_token_id    BIGINT UNSIGNED NOT NULL,
    initial_timestamp       TIMESTAMP NOT NULL,
    
    -- Estado de la cadena
    chain_status            ENUM('active', 'broken', 'expired', 'migrated') DEFAULT 'active',
    
    -- Último re-sello
    last_reseal_at          TIMESTAMP NULL,
    last_reseal_tsa_id      BIGINT UNSIGNED NULL,
    reseal_count            INT UNSIGNED DEFAULT 0,
    
    -- Próximo re-sello programado
    next_reseal_due         TIMESTAMP NOT NULL,
    reseal_interval_days    INT UNSIGNED DEFAULT 365,             -- Re-sellar cada año
    
    -- Verificación
    last_verified_at        TIMESTAMP NULL,
    verification_status     ENUM('pending', 'valid', 'invalid') DEFAULT 'pending',
    
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_chain_tenant (tenant_id),
    INDEX idx_chain_archived (archived_document_id),
    INDEX idx_chain_status (chain_status),
    INDEX idx_chain_next_reseal (next_reseal_due),
    INDEX idx_chain_hash (preserved_hash),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (archived_document_id) REFERENCES archived_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (initial_tsa_token_id) REFERENCES tsa_tokens(id),
    FOREIGN KEY (last_reseal_tsa_id) REFERENCES tsa_tokens(id)
);
```

#### Tabla `tsa_chain_entries` (Entradas individuales de re-sellado)

```sql
CREATE TABLE tsa_chain_entries (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    
    -- Referencia a la cadena
    tsa_chain_id            BIGINT UNSIGNED NOT NULL,
    
    -- Posición en la cadena (0 = original, 1+ = re-sellos)
    sequence_number         INT UNSIGNED NOT NULL,
    
    -- TSA token de esta entrada
    tsa_token_id            BIGINT UNSIGNED NOT NULL,
    
    -- Hash sellado (incluye hash anterior)
    sealed_hash             CHAR(64) NOT NULL,
    previous_entry_id       BIGINT UNSIGNED NULL,                 -- NULL para sequence 0
    
    -- Razón del re-sello
    reseal_reason           ENUM('scheduled', 'algorithm_upgrade', 'certificate_expiry', 'manual') NOT NULL,
    
    -- Metadatos
    tsa_provider            VARCHAR(100) NOT NULL,
    algorithm_used          VARCHAR(50) NOT NULL,
    timestamp_value         TIMESTAMP(6) NOT NULL,
    
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_entry_chain (tsa_chain_id),
    INDEX idx_entry_sequence (tsa_chain_id, sequence_number),
    INDEX idx_entry_timestamp (timestamp_value),
    
    FOREIGN KEY (tsa_chain_id) REFERENCES tsa_chains(id) ON DELETE CASCADE,
    FOREIGN KEY (tsa_token_id) REFERENCES tsa_tokens(id),
    FOREIGN KEY (previous_entry_id) REFERENCES tsa_chain_entries(id),
    
    UNIQUE KEY uk_chain_sequence (tsa_chain_id, sequence_number)
);
```

#### Tabla `retention_policies`

```sql
CREATE TABLE retention_policies (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    tenant_id               BIGINT UNSIGNED NULL,                 -- NULL = política global
    
    -- Identificación
    name                    VARCHAR(100) NOT NULL,
    description             TEXT NULL,
    
    -- Tipo de documento al que aplica
    document_type           VARCHAR(50) NULL,                     -- NULL = todos
    
    -- Periodos de retención
    retention_years         INT UNSIGNED NOT NULL DEFAULT 5,
    retention_days          INT UNSIGNED NOT NULL DEFAULT 0,      -- Para ajustes finos
    
    -- Re-sellado TSA
    reseal_interval_days    INT UNSIGNED NOT NULL DEFAULT 365,    -- Cada año
    reseal_before_expiry_days INT UNSIGNED NOT NULL DEFAULT 90,   -- Re-sellar 90 días antes de expirar
    
    -- Acciones al expirar
    on_expiry_action        ENUM('archive', 'delete', 'notify', 'extend') DEFAULT 'notify',
    
    -- Migración de formato
    require_pdfa_conversion BOOLEAN DEFAULT TRUE,
    target_pdfa_version     VARCHAR(20) DEFAULT 'PDF/A-3b',
    
    -- Estado
    is_active               BOOLEAN DEFAULT TRUE,
    priority                INT UNSIGNED DEFAULT 100,             -- Menor = mayor prioridad
    
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_policy_tenant (tenant_id),
    INDEX idx_policy_type (document_type),
    INDEX idx_policy_active (is_active),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### Servicios de Archivo a Largo Plazo

#### LongTermArchiveService

```php
<?php
// app/Services/Archive/LongTermArchiveService.php

namespace App\Services\Archive;

use App\Models\Document;
use App\Models\ArchivedDocument;
use App\Models\TsaChain;
use App\Models\RetentionPolicy;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LongTermArchiveService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService,
        private readonly TsaResealService $resealService,
        private readonly FormatMigrationService $formatService,
        private readonly RetentionPolicyService $policyService
    ) {}

    /**
     * Archive a document for long-term preservation.
     */
    public function archive(Document $document): ArchivedDocument
    {
        // 1. Get applicable retention policy
        $policy = $this->policyService->getPolicyForDocument($document);
        
        // 2. Convert to PDF/A if required
        $archivePath = $document->stored_path;
        $pdfaVersion = null;
        
        if ($policy->require_pdfa_conversion) {
            $result = $this->formatService->convertToPdfA(
                $document,
                $policy->target_pdfa_version
            );
            $archivePath = $result['path'];
            $pdfaVersion = $result['version'];
        }
        
        // 3. Calculate archive hash
        $archiveHash = $this->hashingService->hashDocument($archivePath);
        
        // 4. Move to cold storage
        $coldStoragePath = $this->moveToColdStorage($archivePath, $document);
        
        // 5. Create archived document record
        $archived = ArchivedDocument::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $document->tenant_id,
            'original_document_id' => $document->id,
            'original_document_uuid' => $document->uuid,
            'storage_driver' => config('archive.cold_storage.driver'),
            'storage_path' => $coldStoragePath,
            'storage_bucket' => config('archive.cold_storage.bucket'),
            'current_format' => $pdfaVersion ? 'PDF/A' : 'PDF',
            'pdfa_version' => $pdfaVersion,
            'format_migrated_at' => $pdfaVersion ? now() : null,
            'content_hash' => $document->content_hash,
            'archive_hash' => $archiveHash,
            'original_filename' => $document->original_name,
            'original_size_bytes' => $document->file_size,
            'original_created_at' => $document->created_at,
            'mime_type' => $document->mime_type,
            'archive_status' => 'active',
            'archived_at' => now(),
            'retention_until' => now()->addYears($policy->retention_years)
                                      ->addDays($policy->retention_days),
            'next_reseal_due' => now()->addDays($policy->reseal_interval_days),
        ]);
        
        // 6. Create TSA chain
        $chain = $this->resealService->initializeChain($archived);
        
        $archived->update([
            'initial_tsa_token_id' => $chain->initial_tsa_token_id,
            'current_tsa_chain_id' => $chain->id,
        ]);
        
        return $archived;
    }

    /**
     * Retrieve an archived document.
     */
    public function retrieve(string $uuid): array
    {
        $archived = ArchivedDocument::where('uuid', $uuid)
            ->with(['tsaChain.entries.tsaToken'])
            ->firstOrFail();
        
        // Update last accessed
        $archived->update(['last_accessed_at' => now()]);
        
        // Get file from cold storage
        $content = Storage::disk($archived->storage_driver)
            ->get($archived->storage_path);
        
        return [
            'document' => $archived,
            'content' => $content,
            'tsa_chain' => $archived->tsaChain,
            'verification' => $this->verify($archived),
        ];
    }

    /**
     * Verify archived document integrity.
     */
    public function verify(ArchivedDocument $archived): array
    {
        $errors = [];
        
        // 1. Verify document hash
        $currentHash = $this->hashingService->hashDocument(
            $archived->storage_path,
            $archived->storage_driver
        );
        
        $hashValid = hash_equals($archived->archive_hash, $currentHash);
        if (!$hashValid) {
            $errors[] = 'Document hash mismatch - file may have been altered';
        }
        
        // 2. Verify TSA chain
        $chainValid = $this->resealService->verifyChain($archived->tsaChain);
        if (!$chainValid->isValid) {
            $errors = array_merge($errors, $chainValid->errors);
        }
        
        // 3. Update verification timestamp
        $archived->update([
            'last_verified_at' => now(),
        ]);
        
        return [
            'is_valid' => empty($errors),
            'hash_valid' => $hashValid,
            'chain_valid' => $chainValid->isValid,
            'errors' => $errors,
            'verified_at' => now(),
            'retention_until' => $archived->retention_until,
            'next_reseal_due' => $archived->next_reseal_due,
        ];
    }

    /**
     * Move document to cold storage.
     */
    private function moveToColdStorage(string $sourcePath, Document $document): string
    {
        $coldDriver = config('archive.cold_storage.driver', 's3');
        $prefix = config('archive.cold_storage.prefix', 'archive');
        
        $targetPath = sprintf(
            '%s/%s/%s/%s_%s',
            $prefix,
            $document->tenant_id,
            now()->format('Y/m'),
            $document->uuid,
            basename($sourcePath)
        );
        
        // Copy to cold storage with encryption
        Storage::disk($coldDriver)->put(
            $targetPath,
            Storage::disk('local')->get($sourcePath),
            [
                'ServerSideEncryption' => 'AES256',
                'StorageClass' => 'GLACIER_IR', // Intelligent tiering for archive
            ]
        );
        
        return $targetPath;
    }
}
```

#### TsaResealService

```php
<?php
// app/Services/Archive/TsaResealService.php

namespace App\Services\Archive;

use App\Models\ArchivedDocument;
use App\Models\TsaChain;
use App\Models\TsaChainEntry;
use App\Models\TsaToken;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TsaResealService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService
    ) {}

    /**
     * Initialize a new TSA chain for an archived document.
     */
    public function initializeChain(ArchivedDocument $archived): TsaChain
    {
        return DB::transaction(function () use ($archived) {
            // Get initial TSA token for the archive hash
            $initialToken = $this->tsaService->requestTimestamp($archived->archive_hash);
            
            // Create chain
            $chain = TsaChain::create([
                'uuid' => Str::uuid(),
                'tenant_id' => $archived->tenant_id,
                'archived_document_id' => $archived->id,
                'chain_type' => 'document',
                'preserved_hash' => $archived->archive_hash,
                'initial_tsa_token_id' => $initialToken->id,
                'initial_timestamp' => $initialToken->issued_at,
                'chain_status' => 'active',
                'next_reseal_due' => now()->addDays(
                    config('archive.reseal_interval_days', 365)
                ),
                'reseal_interval_days' => config('archive.reseal_interval_days', 365),
            ]);
            
            // Create first entry (sequence 0)
            TsaChainEntry::create([
                'uuid' => Str::uuid(),
                'tsa_chain_id' => $chain->id,
                'sequence_number' => 0,
                'tsa_token_id' => $initialToken->id,
                'sealed_hash' => $archived->archive_hash,
                'previous_entry_id' => null,
                'reseal_reason' => 'scheduled',
                'tsa_provider' => $initialToken->provider,
                'algorithm_used' => 'SHA-256',
                'timestamp_value' => $initialToken->issued_at,
            ]);
            
            return $chain;
        });
    }

    /**
     * Re-seal a TSA chain with a new timestamp.
     */
    public function reseal(TsaChain $chain, string $reason = 'scheduled'): TsaChainEntry
    {
        return DB::transaction(function () use ($chain, $reason) {
            // Get previous entry
            $previousEntry = TsaChainEntry::where('tsa_chain_id', $chain->id)
                ->orderBy('sequence_number', 'desc')
                ->first();
            
            // Calculate hash to reseal (includes previous hash + timestamp)
            $dataToSeal = implode('|', [
                $chain->preserved_hash,
                $previousEntry->sealed_hash,
                $previousEntry->tsa_token_id,
                $previousEntry->timestamp_value->toIso8601String(),
                $previousEntry->sequence_number,
            ]);
            
            $newHash = $this->hashingService->hashString($dataToSeal);
            
            // Get new TSA timestamp
            $newToken = $this->tsaService->requestTimestamp($newHash);
            
            // Create new entry
            $newEntry = TsaChainEntry::create([
                'uuid' => Str::uuid(),
                'tsa_chain_id' => $chain->id,
                'sequence_number' => $previousEntry->sequence_number + 1,
                'tsa_token_id' => $newToken->id,
                'sealed_hash' => $newHash,
                'previous_entry_id' => $previousEntry->id,
                'reseal_reason' => $reason,
                'tsa_provider' => $newToken->provider,
                'algorithm_used' => 'SHA-256',
                'timestamp_value' => $newToken->issued_at,
            ]);
            
            // Update chain
            $chain->update([
                'last_reseal_at' => now(),
                'last_reseal_tsa_id' => $newToken->id,
                'reseal_count' => $chain->reseal_count + 1,
                'next_reseal_due' => now()->addDays($chain->reseal_interval_days),
                'last_verified_at' => now(),
                'verification_status' => 'valid',
            ]);
            
            // Update archived document
            $chain->archivedDocument->update([
                'next_reseal_due' => $chain->next_reseal_due,
                'reseal_count' => $chain->reseal_count,
            ]);
            
            return $newEntry;
        });
    }

    /**
     * Verify the integrity of a TSA chain.
     */
    public function verifyChain(TsaChain $chain): object
    {
        $entries = TsaChainEntry::where('tsa_chain_id', $chain->id)
            ->orderBy('sequence_number')
            ->with('tsaToken')
            ->get();
        
        $errors = [];
        $previousEntry = null;
        
        foreach ($entries as $entry) {
            // Verify sequence
            if ($previousEntry && $entry->sequence_number !== $previousEntry->sequence_number + 1) {
                $errors[] = "Sequence gap at entry {$entry->sequence_number}";
            }
            
            // Verify previous reference
            if ($previousEntry && $entry->previous_entry_id !== $previousEntry->id) {
                $errors[] = "Previous entry mismatch at {$entry->sequence_number}";
            }
            
            // Verify hash chain (for entries > 0)
            if ($entry->sequence_number > 0 && $previousEntry) {
                $expectedData = implode('|', [
                    $chain->preserved_hash,
                    $previousEntry->sealed_hash,
                    $previousEntry->tsa_token_id,
                    $previousEntry->timestamp_value->toIso8601String(),
                    $previousEntry->sequence_number,
                ]);
                
                $expectedHash = $this->hashingService->hashString($expectedData);
                
                if (!hash_equals($expectedHash, $entry->sealed_hash)) {
                    $errors[] = "Hash mismatch at entry {$entry->sequence_number}";
                }
            }
            
            // Verify TSA token
            if (!$this->tsaService->verifyTimestamp($entry->tsaToken)) {
                $errors[] = "TSA token invalid at entry {$entry->sequence_number}";
            }
            
            $previousEntry = $entry;
        }
        
        $isValid = empty($errors);
        
        // Update chain status
        $chain->update([
            'last_verified_at' => now(),
            'verification_status' => $isValid ? 'valid' : 'invalid',
        ]);
        
        return (object) [
            'isValid' => $isValid,
            'errors' => $errors,
            'entriesVerified' => $entries->count(),
            'lastEntry' => $previousEntry,
        ];
    }

    /**
     * Schedule re-seal for all chains due.
     */
    public function scheduleAllDueReseals(): int
    {
        $dueChains = TsaChain::where('chain_status', 'active')
            ->where('next_reseal_due', '<=', now())
            ->get();
        
        $resealed = 0;
        
        foreach ($dueChains as $chain) {
            try {
                $this->reseal($chain);
                $resealed++;
            } catch (\Exception $e) {
                \Log::error('Reseal failed for chain', [
                    'chain_id' => $chain->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $resealed;
    }
}
```

### Scheduled Commands

```php
<?php
// app/Console/Commands/ResealTsaChainsCommand.php

namespace App\Console\Commands;

use App\Services\Archive\TsaResealService;
use Illuminate\Console\Command;

class ResealTsaChainsCommand extends Command
{
    protected $signature = 'archive:reseal-tsa {--dry-run : Show what would be resealed}';
    protected $description = 'Re-seal TSA chains that are due for renewal';

    public function handle(TsaResealService $service): int
    {
        if ($this->option('dry-run')) {
            $count = TsaChain::where('chain_status', 'active')
                ->where('next_reseal_due', '<=', now())
                ->count();
            
            $this->info("Would reseal {$count} chains");
            return 0;
        }
        
        $resealed = $service->scheduleAllDueReseals();
        $this->info("Successfully resealed {$resealed} chains");
        
        return 0;
    }
}
```

```php
// app/Console/Kernel.php - Add to schedule

protected function schedule(Schedule $schedule): void
{
    // Daily: Verify archive integrity
    $schedule->command('archive:verify-integrity')
        ->daily()
        ->at('02:00');
    
    // Weekly: Re-seal TSA chains due
    $schedule->command('archive:reseal-tsa')
        ->weekly()
        ->sundays()
        ->at('03:00');
    
    // Monthly: Generate retention reports
    $schedule->command('archive:retention-report')
        ->monthly()
        ->at('04:00');
}
```

---

## E1-009: API de Verificación Pública

### Descripción

API sin autenticación que permite a cualquier persona verificar la autenticidad de un documento firmado mediante:
- Código de verificación único (12 caracteres alfanuméricos)
- QR code que enlaza al verificador
- Hash del documento para verificación local

### Arquitectura de la API Pública

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         API DE VERIFICACIÓN PÚBLICA                                  │
└─────────────────────────────────────────────────────────────────────────────────────┘

                                    Internet
                                        │
                                        ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              CLOUDFLARE / WAF                                         │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐                       │
│  │ DDoS Protection │  │ Rate Limiting   │  │ Bot Detection   │                       │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘                       │
└───────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              NGINX / LOAD BALANCER                                    │
└───────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              LARAVEL APPLICATION                                      │
├───────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                       │
│   ┌─────────────────────────────────────────────────────────────────────────────────┐ │
│   │                          PublicVerificationController                            │ │
│   │                                                                                  │ │
│   │  GET  /verify/{code}           → verify()        → VerificationResult           │ │
│   │  POST /verify/hash             → verifyByHash()  → VerificationResult           │ │
│   │  GET  /verify/{code}/details   → details()       → AuditTrailSummary            │ │
│   │  GET  /verify/{code}/download  → download()      → EvidencePackage (PDF)        │ │
│   │                                                                                  │ │
│   └─────────────────────────────────────────────────────────────────────────────────┘ │
│                                        │                                              │
│                                        ▼                                              │
│   ┌─────────────────────────────────────────────────────────────────────────────────┐ │
│   │                          PublicVerificationService                               │ │
│   │                                                                                  │ │
│   │  - verifyByCode(code)         - Buscar documento por código                     │ │
│   │  - verifyByHash(hash)         - Buscar por hash del documento                   │ │
│   │  - getVerificationDetails()   - Obtener resumen de evidencias                   │ │
│   │  - generatePublicDossier()    - Generar PDF verificable                         │ │
│   │  - calculateConfidenceLevel() - Calcular nivel de confianza                     │ │
│   │                                                                                  │ │
│   └─────────────────────────────────────────────────────────────────────────────────┘ │
│                                        │                                              │
│                                        ▼                                              │
│   ┌────────────────────┐  ┌────────────────────┐  ┌────────────────────┐             │
│   │ RateLimitMiddleware│  │ ThrottleMiddleware │  │ CaptchaMiddleware  │             │
│   │                    │  │                    │  │ (opcional)         │             │
│   │ 60 req/min por IP  │  │ 1000 req/día/IP    │  │ Si > 10 req/min    │             │
│   └────────────────────┘  └────────────────────┘  └────────────────────┘             │
│                                                                                       │
└───────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              CACHE LAYER (Redis)                                      │
│                                                                                       │
│   verification:{code} → TTL: 5 min     (resultado de verificación cacheado)         │
│   rate_limit:{ip}     → TTL: 1 min     (contador de requests)                        │
│   daily_limit:{ip}    → TTL: 24 hours  (contador diario)                             │
│                                                                                       │
└───────────────────────────────────────────────────────────────────────────────────────┘
```

### Estructura de Base de Datos - Verificación

#### Tabla `verification_codes`

```sql
CREATE TABLE verification_codes (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    
    -- Código de verificación
    code                    VARCHAR(20) NOT NULL UNIQUE,          -- Ej: 'ABC1-DEF2-GH34'
    code_type               ENUM('document', 'signature_process', 'evidence_package') NOT NULL,
    
    -- Referencia al objeto verificable
    verifiable_type         VARCHAR(100) NOT NULL,
    verifiable_id           BIGINT UNSIGNED NOT NULL,
    
    -- Hash para verificación directa
    document_hash           CHAR(64) NOT NULL,
    
    -- QR Code
    qr_code_path            VARCHAR(500) NULL,
    verification_url        VARCHAR(500) NOT NULL,
    
    -- Estado
    is_active               BOOLEAN DEFAULT TRUE,
    expires_at              TIMESTAMP NULL,                       -- NULL = no expira
    
    -- Estadísticas
    verification_count      INT UNSIGNED DEFAULT 0,
    last_verified_at        TIMESTAMP NULL,
    
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_vcode_code (code),
    INDEX idx_vcode_hash (document_hash),
    INDEX idx_vcode_verifiable (verifiable_type, verifiable_id),
    INDEX idx_vcode_tenant (tenant_id),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### Tabla `verification_logs` (Registro de verificaciones)

```sql
CREATE TABLE verification_logs (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    
    -- Referencia
    verification_code_id    BIGINT UNSIGNED NOT NULL,
    
    -- Tipo de verificación
    verification_method     ENUM('code', 'hash', 'qr', 'api') NOT NULL,
    
    -- Resultado
    result                  ENUM('valid', 'invalid', 'expired', 'not_found') NOT NULL,
    confidence_level        ENUM('high', 'medium', 'low') NULL,
    
    -- Detalles de verificación
    checks_performed        JSON NOT NULL,                        -- Lista de verificaciones
    errors_found            JSON NULL,                            -- Errores si los hay
    
    -- Contexto del solicitante
    requester_ip            VARCHAR(45) NOT NULL,
    requester_user_agent    TEXT NULL,
    requester_country       VARCHAR(2) NULL,
    
    -- Timestamp
    verified_at             TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6),
    
    INDEX idx_vlog_code (verification_code_id),
    INDEX idx_vlog_result (result),
    INDEX idx_vlog_date (verified_at),
    INDEX idx_vlog_ip (requester_ip),
    
    FOREIGN KEY (verification_code_id) REFERENCES verification_codes(id) ON DELETE CASCADE
);
```

### API Endpoints

#### Routes

```php
<?php
// routes/api.php

use App\Http\Controllers\Api\PublicVerificationController;

// Public verification routes (no auth required)
Route::prefix('verify')->group(function () {
    Route::get('/{code}', [PublicVerificationController::class, 'verify'])
        ->name('api.verify.code')
        ->middleware(['throttle:verification']);
    
    Route::post('/hash', [PublicVerificationController::class, 'verifyByHash'])
        ->name('api.verify.hash')
        ->middleware(['throttle:verification']);
    
    Route::get('/{code}/details', [PublicVerificationController::class, 'details'])
        ->name('api.verify.details')
        ->middleware(['throttle:verification']);
    
    Route::get('/{code}/evidence', [PublicVerificationController::class, 'downloadEvidence'])
        ->name('api.verify.evidence')
        ->middleware(['throttle:verification-download']);
});
```

#### Rate Limiting

```php
<?php
// app/Providers/RouteServiceProvider.php

RateLimiter::for('verification', function (Request $request) {
    return [
        // Per minute limit
        Limit::perMinute(60)->by($request->ip()),
        // Per day limit
        Limit::perDay(1000)->by($request->ip()),
    ];
});

RateLimiter::for('verification-download', function (Request $request) {
    return [
        Limit::perMinute(10)->by($request->ip()),
        Limit::perDay(100)->by($request->ip()),
    ];
});
```

#### PublicVerificationController

```php
<?php
// app/Http/Controllers/Api/PublicVerificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyByHashRequest;
use App\Services\Verification\PublicVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PublicVerificationController extends Controller
{
    public function __construct(
        private readonly PublicVerificationService $verificationService
    ) {}

    /**
     * Verify a document by code.
     * 
     * @unauthenticated
     */
    public function verify(string $code): JsonResponse
    {
        $result = $this->verificationService->verifyByCode($code);
        
        return response()->json([
            'success' => $result->isValid,
            'data' => [
                'verification_status' => $result->status,
                'confidence_level' => $result->confidenceLevel,
                'document' => [
                    'name' => $result->documentName,
                    'hash' => $result->documentHash,
                    'hash_algorithm' => 'SHA-256',
                    'created_at' => $result->documentCreatedAt,
                ],
                'signatures' => $result->signatures,
                'tsa_timestamps' => $result->tsaTimestamps,
                'audit_trail' => [
                    'entries_count' => $result->auditEntriesCount,
                    'chain_verified' => $result->chainVerified,
                    'first_event' => $result->firstEvent,
                    'last_event' => $result->lastEvent,
                ],
                'verification' => [
                    'document_integrity' => $result->documentIntegrity,
                    'chain_integrity' => $result->chainIntegrity,
                    'tsa_validity' => $result->tsaValidity,
                    'signatures_valid' => $result->signaturesValid,
                ],
                'verified_at' => now()->toIso8601String(),
            ],
            'meta' => [
                'verification_code' => $code,
                'api_version' => 'v1',
            ],
        ], $result->isValid ? 200 : 400);
    }

    /**
     * Verify a document by its hash.
     * 
     * @unauthenticated
     */
    public function verifyByHash(VerifyByHashRequest $request): JsonResponse
    {
        $result = $this->verificationService->verifyByHash(
            $request->validated('hash'),
            $request->validated('algorithm', 'SHA-256')
        );
        
        if (!$result->found) {
            return response()->json([
                'success' => false,
                'error' => 'document_not_found',
                'message' => 'No document found with the provided hash.',
            ], 404);
        }
        
        return response()->json([
            'success' => $result->isValid,
            'data' => [
                'verification_status' => $result->status,
                'verification_code' => $result->verificationCode,
                'document_found' => true,
                'document' => [
                    'name' => $result->documentName,
                    'hash' => $result->documentHash,
                    'matches_provided_hash' => $result->hashMatches,
                ],
                'verified_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get detailed verification information.
     * 
     * @unauthenticated
     */
    public function details(string $code): JsonResponse
    {
        $details = $this->verificationService->getDetails($code);
        
        if (!$details) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
                'message' => 'Verification code not found.',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'document' => $details->document,
                'signers' => $details->signers,
                'audit_trail' => $details->auditTrail,
                'tsa_chain' => $details->tsaChain,
                'evidence_summary' => $details->evidenceSummary,
            ],
        ]);
    }

    /**
     * Download evidence package.
     * 
     * @unauthenticated
     */
    public function downloadEvidence(string $code): Response
    {
        $package = $this->verificationService->generatePublicEvidence($code);
        
        if (!$package) {
            abort(404, 'Verification code not found');
        }
        
        return response($package->content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"evidence_{$code}.pdf\"",
            'Content-Length' => strlen($package->content),
        ]);
    }
}
```

#### PublicVerificationService

```php
<?php
// app/Services/Verification/PublicVerificationService.php

namespace App\Services\Verification;

use App\Models\VerificationCode;
use App\Models\VerificationLog;
use App\Models\Document;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\TsaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicVerificationService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly AuditTrailService $auditService,
        private readonly TsaService $tsaService
    ) {}

    /**
     * Verify a document by verification code.
     */
    public function verifyByCode(string $code): VerificationResult
    {
        // Check cache first
        $cacheKey = "verification:{$code}";
        
        if ($cached = Cache::get($cacheKey)) {
            $this->logVerification($code, 'code', $cached);
            return $cached;
        }
        
        // Find verification code
        $verificationCode = VerificationCode::where('code', $this->normalizeCode($code))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
        
        if (!$verificationCode) {
            $result = new VerificationResult(
                isValid: false,
                status: 'not_found',
                confidenceLevel: 'low'
            );
            
            $this->logVerification($code, 'code', $result);
            return $result;
        }
        
        // Get the verifiable object
        $verifiable = $verificationCode->verifiable;
        
        // Perform comprehensive verification
        $result = $this->performVerification($verifiable, $verificationCode);
        
        // Cache result for 5 minutes
        Cache::put($cacheKey, $result, now()->addMinutes(5));
        
        // Log verification
        $this->logVerification($code, 'code', $result, $verificationCode->id);
        
        // Update stats
        $verificationCode->increment('verification_count');
        $verificationCode->update(['last_verified_at' => now()]);
        
        return $result;
    }

    /**
     * Verify a document by its hash.
     */
    public function verifyByHash(string $hash, string $algorithm = 'SHA-256'): VerificationResult
    {
        // Validate hash format
        if (!$this->hashingService->isValidHash($hash)) {
            return new VerificationResult(
                isValid: false,
                status: 'invalid_hash',
                confidenceLevel: 'low',
                found: false
            );
        }
        
        // Find document by hash
        $verificationCode = VerificationCode::where('document_hash', strtolower($hash))
            ->where('is_active', true)
            ->first();
        
        if (!$verificationCode) {
            return new VerificationResult(
                isValid: false,
                status: 'not_found',
                confidenceLevel: 'low',
                found: false
            );
        }
        
        // Perform verification
        $result = $this->performVerification(
            $verificationCode->verifiable,
            $verificationCode
        );
        
        $result->verificationCode = $verificationCode->code;
        $result->hashMatches = true;
        $result->found = true;
        
        $this->logVerification($hash, 'hash', $result, $verificationCode->id);
        
        return $result;
    }

    /**
     * Perform comprehensive verification.
     */
    private function performVerification($verifiable, VerificationCode $code): VerificationResult
    {
        $checks = [];
        $errors = [];
        
        // 1. Verify document integrity
        $documentIntegrity = $this->verifyDocumentIntegrity($verifiable);
        $checks['document_integrity'] = $documentIntegrity;
        if (!$documentIntegrity['valid']) {
            $errors[] = 'Document integrity check failed';
        }
        
        // 2. Verify audit trail chain
        $chainIntegrity = $this->auditService->verifyChain($verifiable);
        $checks['chain_integrity'] = [
            'valid' => $chainIntegrity->isValid,
            'entries_verified' => $chainIntegrity->entriesVerified,
        ];
        if (!$chainIntegrity->isValid) {
            $errors = array_merge($errors, $chainIntegrity->errors);
        }
        
        // 3. Verify TSA tokens
        $tsaValidity = $this->verifyTsaTokens($verifiable);
        $checks['tsa_validity'] = $tsaValidity;
        if (!$tsaValidity['all_valid']) {
            $errors[] = 'One or more TSA tokens failed verification';
        }
        
        // 4. Verify signatures (if applicable)
        $signaturesValid = $this->verifySignatures($verifiable);
        $checks['signatures'] = $signaturesValid;
        
        // Calculate confidence level
        $confidenceLevel = $this->calculateConfidenceLevel($checks);
        
        // Build result
        $isValid = empty($errors) && $documentIntegrity['valid'] && $chainIntegrity->isValid;
        
        return new VerificationResult(
            isValid: $isValid,
            status: $isValid ? 'valid' : 'verification_failed',
            confidenceLevel: $confidenceLevel,
            documentName: $verifiable->original_name ?? $verifiable->name ?? 'Unknown',
            documentHash: $code->document_hash,
            documentCreatedAt: $verifiable->created_at?->toIso8601String(),
            documentIntegrity: $documentIntegrity['valid'],
            chainIntegrity: $chainIntegrity->isValid,
            tsaValidity: $tsaValidity['all_valid'],
            signaturesValid: $signaturesValid['all_valid'] ?? true,
            auditEntriesCount: $chainIntegrity->entriesVerified,
            chainVerified: $chainIntegrity->isValid,
            firstEvent: $chainIntegrity->firstEntry['created_at'] ?? null,
            lastEvent: $chainIntegrity->lastEntry['created_at'] ?? null,
            signatures: $signaturesValid['signatures'] ?? [],
            tsaTimestamps: $tsaValidity['timestamps'] ?? [],
            checksPerformed: $checks,
            errors: $errors
        );
    }

    /**
     * Verify document file integrity.
     */
    private function verifyDocumentIntegrity($document): array
    {
        try {
            $currentHash = $this->hashingService->hashDocument(
                $document->stored_path ?? $document->file_path,
                $document->storage_disk ?? 'local'
            );
            
            $expectedHash = $document->content_hash ?? $document->hash;
            $valid = hash_equals($expectedHash, $currentHash);
            
            return [
                'valid' => $valid,
                'expected_hash' => $expectedHash,
                'current_hash' => $currentHash,
                'algorithm' => 'SHA-256',
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify TSA tokens.
     */
    private function verifyTsaTokens($verifiable): array
    {
        $tokens = $verifiable->tsaTokens ?? collect();
        $results = [];
        $allValid = true;
        
        foreach ($tokens as $token) {
            $valid = $this->tsaService->verifyTimestamp($token);
            $results[] = [
                'provider' => $token->provider,
                'timestamp' => $token->issued_at?->toIso8601String(),
                'valid' => $valid,
            ];
            
            if (!$valid) {
                $allValid = false;
            }
        }
        
        return [
            'all_valid' => $allValid || $tokens->isEmpty(),
            'count' => $tokens->count(),
            'timestamps' => $results,
        ];
    }

    /**
     * Verify signatures.
     */
    private function verifySignatures($verifiable): array
    {
        // This will be implemented when signature model exists
        return [
            'all_valid' => true,
            'count' => 0,
            'signatures' => [],
        ];
    }

    /**
     * Calculate confidence level based on checks.
     */
    private function calculateConfidenceLevel(array $checks): string
    {
        $score = 0;
        $maxScore = 4;
        
        if ($checks['document_integrity']['valid'] ?? false) $score++;
        if ($checks['chain_integrity']['valid'] ?? false) $score++;
        if ($checks['tsa_validity']['all_valid'] ?? false) $score++;
        if ($checks['signatures']['all_valid'] ?? true) $score++;
        
        $percentage = ($score / $maxScore) * 100;
        
        return match(true) {
            $percentage >= 90 => 'high',
            $percentage >= 70 => 'medium',
            default => 'low',
        };
    }

    /**
     * Log verification attempt.
     */
    private function logVerification(
        string $identifier,
        string $method,
        VerificationResult $result,
        ?int $verificationCodeId = null
    ): void {
        if ($verificationCodeId) {
            VerificationLog::create([
                'uuid' => Str::uuid(),
                'verification_code_id' => $verificationCodeId,
                'verification_method' => $method,
                'result' => $result->status === 'valid' ? 'valid' : 
                           ($result->status === 'not_found' ? 'not_found' : 'invalid'),
                'confidence_level' => $result->confidenceLevel,
                'checks_performed' => $result->checksPerformed ?? [],
                'errors_found' => $result->errors ?? [],
                'requester_ip' => request()->ip(),
                'requester_user_agent' => request()->userAgent(),
            ]);
        }
    }

    /**
     * Normalize verification code format.
     */
    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
    }

    /**
     * Generate a unique verification code.
     */
    public static function generateCode(): string
    {
        $segments = [];
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Avoid confusing chars (0/O, 1/I/L)
        
        for ($i = 0; $i < 3; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }
        
        return implode('-', $segments); // Format: XXXX-XXXX-XXXX
    }
}
```

#### VerificationResult DTO

```php
<?php
// app/Services/Verification/VerificationResult.php

namespace App\Services\Verification;

readonly class VerificationResult
{
    public function __construct(
        public bool $isValid,
        public string $status,
        public string $confidenceLevel,
        public ?string $documentName = null,
        public ?string $documentHash = null,
        public ?string $documentCreatedAt = null,
        public bool $documentIntegrity = false,
        public bool $chainIntegrity = false,
        public bool $tsaValidity = false,
        public bool $signaturesValid = true,
        public int $auditEntriesCount = 0,
        public bool $chainVerified = false,
        public ?string $firstEvent = null,
        public ?string $lastEvent = null,
        public array $signatures = [],
        public array $tsaTimestamps = [],
        public array $checksPerformed = [],
        public array $errors = [],
        public bool $found = true,
        public ?string $verificationCode = null,
        public bool $hashMatches = false,
    ) {}
}
```

### Web Verification Page

```php
<?php
// app/Livewire/Verification/PublicVerifier.php

namespace App\Livewire\Verification;

use App\Services\Verification\PublicVerificationService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;

#[Layout('layouts.public')]
class PublicVerifier extends Component
{
    use WithFileUploads;

    #[Rule('nullable|string|max:20')]
    public string $code = '';
    
    #[Rule('nullable|string|size:64')]
    public string $hash = '';
    
    #[Rule('nullable|file|max:50000|mimes:pdf')]
    public $file = null;
    
    public ?array $result = null;
    public bool $loading = false;
    public string $verificationMethod = 'code';

    public function mount(?string $code = null): void
    {
        if ($code) {
            $this->code = $code;
            $this->verifyByCode();
        }
    }

    public function verifyByCode(): void
    {
        $this->validate(['code' => 'required|string|min:10|max:20']);
        
        $this->loading = true;
        
        $service = app(PublicVerificationService::class);
        $verificationResult = $service->verifyByCode($this->code);
        
        $this->result = [
            'success' => $verificationResult->isValid,
            'data' => [
                'status' => $verificationResult->status,
                'confidence' => $verificationResult->confidenceLevel,
                'document_name' => $verificationResult->documentName,
                'document_hash' => $verificationResult->documentHash,
                'integrity' => [
                    'document' => $verificationResult->documentIntegrity,
                    'chain' => $verificationResult->chainIntegrity,
                    'tsa' => $verificationResult->tsaValidity,
                ],
                'audit' => [
                    'entries' => $verificationResult->auditEntriesCount,
                    'first_event' => $verificationResult->firstEvent,
                    'last_event' => $verificationResult->lastEvent,
                ],
            ],
            'errors' => $verificationResult->errors,
        ];
        
        $this->loading = false;
    }

    public function verifyByFile(): void
    {
        $this->validate(['file' => 'required|file|max:50000|mimes:pdf']);
        
        $this->loading = true;
        
        $hashingService = app(\App\Services\Evidence\HashingService::class);
        $hash = $hashingService->hashUploadedFile($this->file);
        
        $this->hash = $hash;
        $this->verifyByHash();
    }

    public function verifyByHash(): void
    {
        $this->validate(['hash' => 'required|string|size:64']);
        
        $this->loading = true;
        
        $service = app(PublicVerificationService::class);
        $verificationResult = $service->verifyByHash($this->hash);
        
        $this->result = [
            'success' => $verificationResult->isValid,
            'found' => $verificationResult->found,
            'verification_code' => $verificationResult->verificationCode,
            'data' => $verificationResult->found ? [
                'status' => $verificationResult->status,
                'document_name' => $verificationResult->documentName,
                'hash_matches' => $verificationResult->hashMatches,
            ] : null,
        ];
        
        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.verification.public-verifier');
    }
}
```

---

## E2-001: Subida de Documentos PDF

### Descripción

Primera funcionalidad de usuario final para subir documentos PDF que serán firmados. Incluye:
- Validación exhaustiva del PDF
- Almacenamiento seguro con cifrado
- Extracción de metadatos
- Generación de thumbnail
- Aislamiento multi-tenant

### Arquitectura de Upload

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         FLUJO DE SUBIDA DE DOCUMENTOS                                │
└─────────────────────────────────────────────────────────────────────────────────────┘

     Usuario                  Frontend                    Backend
        │                        │                           │
        │   Selecciona PDF       │                           │
        ├───────────────────────▶│                           │
        │                        │                           │
        │                        │   Validación JS           │
        │                        │   - Tipo MIME             │
        │                        │   - Tamaño máximo         │
        │                        │   - Extensión             │
        │                        ├──────────┐                │
        │                        │          │                │
        │   Preview + Progreso   │◀─────────┘                │
        │◀───────────────────────│                           │
        │                        │                           │
        │                        │   POST /documents         │
        │                        │   multipart/form-data     │
        │                        ├──────────────────────────▶│
        │                        │                           │
        │                        │                           │   ┌─────────────────────┐
        │                        │                           │   │ Validación Backend  │
        │                        │                           │   ├─────────────────────┤
        │                        │                           │   │ 1. Virus scan       │
        │                        │                           │   │ 2. PDF structure    │
        │                        │                           │   │ 3. Magic bytes      │
        │                        │                           │   │ 4. Size limits      │
        │                        │                           │   │ 5. Page count       │
        │                        │                           │   └─────────────────────┘
        │                        │                           │              │
        │                        │                           │              ▼
        │                        │                           │   ┌─────────────────────┐
        │                        │                           │   │ Procesamiento       │
        │                        │                           │   ├─────────────────────┤
        │                        │                           │   │ 1. Hash SHA-256     │
        │                        │                           │   │ 2. Extract metadata │
        │                        │                           │   │ 3. Generate thumb   │
        │                        │                           │   │ 4. Encrypt & store  │
        │                        │                           │   │ 5. TSA timestamp    │
        │                        │                           │   │ 6. Create record    │
        │                        │                           │   │ 7. Audit log        │
        │                        │                           │   └─────────────────────┘
        │                        │                           │              │
        │                        │   201 Created             │              │
        │                        │   Document JSON           │◀─────────────┘
        │                        │◀──────────────────────────│
        │                        │                           │
        │   Confirmación         │                           │
        │◀───────────────────────│                           │
        │                        │                           │
```

### Estructura de Base de Datos - Documentos

#### Tabla `documents`

```sql
CREATE TABLE documents (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    
    -- Propietario
    user_id                 BIGINT UNSIGNED NOT NULL,
    
    -- Información del archivo original
    original_name           VARCHAR(255) NOT NULL,
    original_extension      VARCHAR(20) NOT NULL,
    mime_type               VARCHAR(100) NOT NULL,
    file_size               BIGINT UNSIGNED NOT NULL,
    page_count              INT UNSIGNED NULL,
    
    -- Almacenamiento
    storage_disk            VARCHAR(50) NOT NULL DEFAULT 'local',
    stored_path             VARCHAR(500) NOT NULL,
    stored_name             VARCHAR(255) NOT NULL,
    is_encrypted            BOOLEAN DEFAULT TRUE,
    encryption_key_id       VARCHAR(100) NULL,
    
    -- Integridad
    content_hash            CHAR(64) NOT NULL,
    hash_algorithm          VARCHAR(20) DEFAULT 'SHA-256',
    hash_verified_at        TIMESTAMP NULL,
    
    -- TSA
    upload_tsa_token_id     BIGINT UNSIGNED NULL,
    
    -- Thumbnail
    thumbnail_path          VARCHAR(500) NULL,
    thumbnail_generated_at  TIMESTAMP NULL,
    
    -- Metadatos extraídos del PDF
    pdf_metadata            JSON NULL,
    pdf_version             VARCHAR(20) NULL,
    is_pdf_a                BOOLEAN DEFAULT FALSE,
    has_signatures          BOOLEAN DEFAULT FALSE,
    has_encryption          BOOLEAN DEFAULT FALSE,
    has_javascript          BOOLEAN DEFAULT FALSE,
    
    -- Verificación de código
    verification_code_id    BIGINT UNSIGNED NULL,
    
    -- Estado
    status                  ENUM('uploading', 'processing', 'ready', 'error', 'deleted') DEFAULT 'uploading',
    error_message           TEXT NULL,
    
    -- Auditoría
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP NULL,
    
    INDEX idx_doc_tenant (tenant_id),
    INDEX idx_doc_user (user_id),
    INDEX idx_doc_hash (content_hash),
    INDEX idx_doc_status (status),
    INDEX idx_doc_uuid (uuid),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (upload_tsa_token_id) REFERENCES tsa_tokens(id)
);
```

### Validación de PDF

```php
<?php
// app/Services/Document/PdfValidationService.php

namespace App\Services\Document;

use Illuminate\Http\UploadedFile;

class PdfValidationService
{
    /**
     * PDF magic bytes
     */
    private const PDF_MAGIC = '%PDF-';
    
    /**
     * Maximum file size (50MB)
     */
    private const MAX_SIZE = 52428800;
    
    /**
     * Maximum page count
     */
    private const MAX_PAGES = 500;

    /**
     * Validate an uploaded PDF file.
     */
    public function validate(UploadedFile $file): ValidationResult
    {
        $errors = [];
        $warnings = [];
        
        // 1. Basic file checks
        if ($file->getSize() > self::MAX_SIZE) {
            $errors[] = 'File exceeds maximum size of 50MB';
        }
        
        if ($file->getClientOriginalExtension() !== 'pdf') {
            $errors[] = 'File must have .pdf extension';
        }
        
        // 2. MIME type check
        $mimeType = $file->getMimeType();
        if ($mimeType !== 'application/pdf') {
            $errors[] = "Invalid MIME type: {$mimeType}";
        }
        
        // 3. Magic bytes check
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fread($handle, 8);
        fclose($handle);
        
        if (!str_starts_with($header, self::PDF_MAGIC)) {
            $errors[] = 'File does not have valid PDF header';
        }
        
        // 4. PDF structure analysis
        try {
            $analysis = $this->analyzePdfStructure($file);
            
            if ($analysis['page_count'] > self::MAX_PAGES) {
                $errors[] = "PDF exceeds maximum of " . self::MAX_PAGES . " pages";
            }
            
            if ($analysis['has_javascript']) {
                $warnings[] = 'PDF contains JavaScript which will be disabled';
            }
            
            if ($analysis['has_encryption']) {
                $errors[] = 'Encrypted PDFs are not supported. Please remove encryption first.';
            }
            
            if ($analysis['is_corrupted']) {
                $errors[] = 'PDF appears to be corrupted or malformed';
            }
            
        } catch (\Exception $e) {
            $errors[] = 'Failed to analyze PDF structure: ' . $e->getMessage();
        }
        
        // 5. Virus scan (if configured)
        if (config('documents.virus_scan_enabled')) {
            $scanResult = $this->scanForViruses($file);
            if (!$scanResult['clean']) {
                $errors[] = 'File failed virus scan: ' . $scanResult['threat'];
            }
        }
        
        return new ValidationResult(
            valid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            metadata: $analysis ?? []
        );
    }

    /**
     * Analyze PDF structure.
     */
    private function analyzePdfStructure(UploadedFile $file): array
    {
        // Use Smalot PdfParser or similar
        $parser = new \Smalot\PdfParser\Parser();
        
        try {
            $pdf = $parser->parseFile($file->getRealPath());
            $details = $pdf->getDetails();
            $pages = $pdf->getPages();
            
            // Check for JavaScript
            $hasJs = false;
            $content = file_get_contents($file->getRealPath());
            if (preg_match('/\/JS\s|\/JavaScript\s/i', $content)) {
                $hasJs = true;
            }
            
            // Extract metadata
            return [
                'page_count' => count($pages),
                'pdf_version' => $details['PDF Version'] ?? null,
                'title' => $details['Title'] ?? null,
                'author' => $details['Author'] ?? null,
                'creator' => $details['Creator'] ?? null,
                'producer' => $details['Producer'] ?? null,
                'creation_date' => $details['CreationDate'] ?? null,
                'modification_date' => $details['ModDate'] ?? null,
                'has_javascript' => $hasJs,
                'has_encryption' => false, // Parser would fail if encrypted
                'has_signatures' => $this->detectSignatures($content),
                'is_pdf_a' => $this->detectPdfA($content),
                'is_corrupted' => false,
            ];
            
        } catch (\Exception $e) {
            // If parsing fails, PDF might be encrypted or corrupted
            return [
                'page_count' => 0,
                'is_corrupted' => true,
                'has_encryption' => str_contains($e->getMessage(), 'encrypt'),
                'parse_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect if PDF has digital signatures.
     */
    private function detectSignatures(string $content): bool
    {
        return (bool) preg_match('/\/Type\s*\/Sig\b|\/SigFlags\s/i', $content);
    }

    /**
     * Detect if PDF is PDF/A compliant.
     */
    private function detectPdfA(string $content): bool
    {
        return str_contains($content, 'pdfaid:part') || 
               str_contains($content, 'PDF/A');
    }

    /**
     * Scan file for viruses using ClamAV.
     */
    private function scanForViruses(UploadedFile $file): array
    {
        // Implementation depends on antivirus solution
        // Example with ClamAV:
        
        if (!extension_loaded('clamav')) {
            return ['clean' => true, 'skipped' => true];
        }
        
        $scanner = new \ClamAV\Scanner();
        $result = $scanner->scan($file->getRealPath());
        
        return [
            'clean' => !$result->isInfected(),
            'threat' => $result->getVirusName(),
        ];
    }
}
```

### DocumentUploadService

```php
<?php
// app/Services/Document/DocumentUploadService.php

namespace App\Services\Document;

use App\Models\Document;
use App\Models\VerificationCode;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use App\Services\Evidence\AuditTrailService;
use App\Services\Verification\PublicVerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class DocumentUploadService
{
    public function __construct(
        private readonly PdfValidationService $validator,
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService,
        private readonly AuditTrailService $auditService
    ) {}

    /**
     * Upload and process a PDF document.
     */
    public function upload(UploadedFile $file, int $userId): Document
    {
        $tenant = app('tenant');
        
        // 1. Validate the PDF
        $validation = $this->validator->validate($file);
        
        if (!$validation->valid) {
            throw new DocumentUploadException(
                'PDF validation failed',
                $validation->errors
            );
        }
        
        // 2. Create document record (uploading status)
        $document = Document::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'original_extension' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploading',
        ]);
        
        try {
            // 3. Calculate hash before storage
            $contentHash = $this->hashingService->hashUploadedFile($file);
            
            // 4. Check for duplicate (same hash in tenant)
            $duplicate = Document::where('tenant_id', $tenant->id)
                ->where('content_hash', $contentHash)
                ->where('id', '!=', $document->id)
                ->first();
            
            if ($duplicate) {
                $document->delete();
                throw new DuplicateDocumentException(
                    'A document with identical content already exists',
                    $duplicate->uuid
                );
            }
            
            // 5. Store file with encryption
            $storedPath = $this->storeEncrypted($file, $document);
            
            // 6. Update to processing status
            $document->update([
                'stored_path' => $storedPath,
                'stored_name' => basename($storedPath),
                'content_hash' => $contentHash,
                'status' => 'processing',
            ]);
            
            // 7. Extract PDF metadata
            $metadata = $validation->metadata;
            
            // 8. Generate thumbnail
            $thumbnailPath = $this->generateThumbnail($document);
            
            // 9. Get TSA timestamp for upload
            $tsaToken = $this->tsaService->requestTimestamp($contentHash);
            
            // 10. Create verification code
            $verificationCode = $this->createVerificationCode($document);
            
            // 11. Update document with all info
            $document->update([
                'page_count' => $metadata['page_count'] ?? null,
                'pdf_metadata' => $metadata,
                'pdf_version' => $metadata['pdf_version'] ?? null,
                'is_pdf_a' => $metadata['is_pdf_a'] ?? false,
                'has_signatures' => $metadata['has_signatures'] ?? false,
                'has_javascript' => $metadata['has_javascript'] ?? false,
                'thumbnail_path' => $thumbnailPath,
                'thumbnail_generated_at' => $thumbnailPath ? now() : null,
                'upload_tsa_token_id' => $tsaToken->id,
                'verification_code_id' => $verificationCode->id,
                'status' => 'ready',
            ]);
            
            // 12. Log to audit trail
            $this->auditService->logEvent(
                'document.uploaded',
                $document,
                [
                    'original_name' => $document->original_name,
                    'file_size' => $document->file_size,
                    'page_count' => $document->page_count,
                    'content_hash' => $document->content_hash,
                    'verification_code' => $verificationCode->code,
                ]
            );
            
            return $document->fresh();
            
        } catch (\Exception $e) {
            // Update status to error
            $document->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Store file with encryption.
     */
    private function storeEncrypted(UploadedFile $file, Document $document): string
    {
        $tenant = app('tenant');
        
        $path = sprintf(
            'documents/%s/%s/%s_%s.pdf.enc',
            $tenant->id,
            now()->format('Y/m'),
            $document->uuid,
            Str::random(8)
        );
        
        // Read file content
        $content = file_get_contents($file->getRealPath());
        
        // Encrypt content
        $encrypted = $this->encrypt($content);
        
        // Store encrypted file
        Storage::disk('local')->put($path, $encrypted);
        
        return $path;
    }

    /**
     * Encrypt content using Laravel's encryption.
     */
    private function encrypt(string $content): string
    {
        return encrypt($content);
    }

    /**
     * Generate thumbnail for first page.
     */
    private function generateThumbnail(Document $document): ?string
    {
        try {
            // Decrypt and get content
            $content = $this->getDecryptedContent($document);
            
            // Save to temp file
            $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($tempPdf, $content);
            
            // Convert first page to image using Imagick
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($tempPdf . '[0]'); // First page
            $imagick->setImageFormat('png');
            $imagick->thumbnailImage(300, 0); // 300px width, proportional height
            
            // Generate path
            $thumbnailPath = sprintf(
                'thumbnails/%s/%s/%s.png',
                $document->tenant_id,
                now()->format('Y/m'),
                $document->uuid
            );
            
            // Store thumbnail
            Storage::disk('local')->put($thumbnailPath, $imagick->getImageBlob());
            
            // Cleanup
            $imagick->clear();
            $imagick->destroy();
            unlink($tempPdf);
            
            return $thumbnailPath;
            
        } catch (\Exception $e) {
            \Log::warning('Thumbnail generation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Get decrypted content of a document.
     */
    public function getDecryptedContent(Document $document): string
    {
        $encrypted = Storage::disk($document->storage_disk)->get($document->stored_path);
        return decrypt($encrypted);
    }

    /**
     * Create verification code for document.
     */
    private function createVerificationCode(Document $document): VerificationCode
    {
        $code = PublicVerificationService::generateCode();
        
        return VerificationCode::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $document->tenant_id,
            'code' => $code,
            'code_type' => 'document',
            'verifiable_type' => Document::class,
            'verifiable_id' => $document->id,
            'document_hash' => $document->content_hash,
            'verification_url' => route('verify', $code),
            'is_active' => true,
        ]);
    }
}
```

### Livewire Upload Component

```php
<?php
// app/Livewire/Document/UploadDocument.php

namespace App\Livewire\Document;

use App\Services\Document\DocumentUploadService;
use App\Services\Document\DocumentUploadException;
use App\Services\Document\DuplicateDocumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

class UploadDocument extends Component
{
    use WithFileUploads;

    #[Validate('required|file|max:51200|mimes:pdf')]
    public $file;
    
    public bool $uploading = false;
    public ?array $uploadedDocument = null;
    public ?string $error = null;
    public array $warnings = [];
    public int $progress = 0;

    public function updatedFile(): void
    {
        $this->error = null;
        $this->warnings = [];
        
        // Client-side validation feedback
        $this->validate();
    }

    public function upload(DocumentUploadService $uploadService): void
    {
        $this->validate();
        
        $this->uploading = true;
        $this->error = null;
        $this->progress = 0;
        
        try {
            $document = $uploadService->upload(
                $this->file,
                auth()->id()
            );
            
            $this->uploadedDocument = [
                'uuid' => $document->uuid,
                'name' => $document->original_name,
                'size' => $document->file_size,
                'pages' => $document->page_count,
                'hash' => $document->content_hash,
                'verification_code' => $document->verificationCode->code,
                'thumbnail_url' => $document->thumbnail_path 
                    ? route('documents.thumbnail', $document->uuid)
                    : null,
            ];
            
            // Emit event for parent components
            $this->dispatch('document-uploaded', documentId: $document->id);
            
            // Reset file input
            $this->reset('file');
            
        } catch (DuplicateDocumentException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('document-duplicate', existingUuid: $e->existingUuid);
            
        } catch (DocumentUploadException $e) {
            $this->error = 'Validation failed: ' . implode(', ', $e->errors);
            
        } catch (\Exception $e) {
            $this->error = 'Upload failed. Please try again.';
            \Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
        }
        
        $this->uploading = false;
    }

    public function removeFile(): void
    {
        $this->reset(['file', 'uploadedDocument', 'error', 'warnings']);
    }

    public function render()
    {
        return view('livewire.document.upload-document');
    }
}
```

### Blade View

```blade
{{-- resources/views/livewire/document/upload-document.blade.php --}}
<div class="space-y-6">
    {{-- Upload Area --}}
    <div
        x-data="{ 
            isDragging: false,
            handleDrop(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type === 'application/pdf') {
                    @this.upload('file', files[0]);
                }
            }
        }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="isDragging = false; handleDrop($event)"
        class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors"
        :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'"
    >
        @if(!$uploadedDocument)
            <div class="space-y-4">
                {{-- Icon --}}
                <div class="mx-auto w-16 h-16 text-gray-400">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                              d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                
                {{-- Text --}}
                <div>
                    <p class="text-lg font-medium text-gray-700">
                        Arrastra un PDF aquí o
                    </p>
                    <label class="cursor-pointer text-blue-600 hover:text-blue-700 font-medium">
                        selecciona un archivo
                        <input
                            type="file"
                            wire:model="file"
                            accept=".pdf,application/pdf"
                            class="hidden"
                        >
                    </label>
                </div>
                
                {{-- Limits --}}
                <p class="text-sm text-gray-500">
                    PDF • Máximo 50MB • Hasta 500 páginas
                </p>
            </div>
            
            {{-- Loading --}}
            <div wire:loading wire:target="file" class="absolute inset-0 bg-white/80 flex items-center justify-center">
                <div class="flex items-center space-x-2">
                    <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-gray-600">Validando archivo...</span>
                </div>
            </div>
        @else
            {{-- Uploaded Document Preview --}}
            <div class="flex items-start space-x-4">
                @if($uploadedDocument['thumbnail_url'])
                    <img src="{{ $uploadedDocument['thumbnail_url'] }}"
                         alt="Preview"
                         class="w-24 h-32 object-cover rounded shadow">
                @else
                    <div class="w-24 h-32 bg-gray-100 rounded flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                @endif
                
                <div class="flex-1">
                    <h3 class="font-medium text-gray-900">{{ $uploadedDocument['name'] }}</h3>
                    <p class="text-sm text-gray-500">
                        {{ number_format($uploadedDocument['size'] / 1024 / 1024, 2) }} MB
                        • {{ $uploadedDocument['pages'] }} páginas
                    </p>
                    <div class="mt-2 flex items-center space-x-2">
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded">
                            ✓ Verificable
                        </span>
                        <code class="text-xs text-gray-600">{{ $uploadedDocument['verification_code'] }}</code>
                    </div>
                    
                    <button
                        wire:click="removeFile"
                        class="mt-3 text-sm text-red-600 hover:text-red-700"
                    >
                        Eliminar y subir otro
                    </button>
                </div>
            </div>
        @endif
    </div>
    
    {{-- Error Message --}}
    @if($error)
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <p class="ml-2 text-sm text-red-700">{{ $error }}</p>
            </div>
        </div>
    @endif
</div>
```

---

## Configuración Adicional

### Archivo config/archive.php

```php
<?php
// config/archive.php

return [
    'cold_storage' => [
        'driver' => env('ARCHIVE_STORAGE_DRIVER', 's3'),
        'bucket' => env('ARCHIVE_STORAGE_BUCKET', 'ancla-archive'),
        'prefix' => 'archive',
        'encryption' => 'AES256',
        'storage_class' => env('ARCHIVE_STORAGE_CLASS', 'GLACIER_IR'),
    ],

    'reseal' => [
        'interval_days' => env('ARCHIVE_RESEAL_INTERVAL', 365),
        'before_expiry_days' => env('ARCHIVE_RESEAL_BEFORE_EXPIRY', 90),
        'batch_size' => env('ARCHIVE_RESEAL_BATCH_SIZE', 100),
    ],

    'retention' => [
        'default_years' => env('ARCHIVE_RETENTION_YEARS', 5),
        'expiry_action' => env('ARCHIVE_EXPIRY_ACTION', 'notify'),
        'grace_period_days' => env('ARCHIVE_GRACE_PERIOD', 30),
    ],

    'format' => [
        'target_pdfa' => env('ARCHIVE_PDFA_VERSION', 'PDF/A-3b'),
        'auto_convert' => env('ARCHIVE_AUTO_CONVERT_PDFA', true),
    ],
];
```

### Archivo config/documents.php

```php
<?php
// config/documents.php

return [
    'upload' => [
        'max_size' => env('DOCUMENT_MAX_SIZE', 52428800),
        'max_pages' => env('DOCUMENT_MAX_PAGES', 500),
        'allowed_mimes' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],
    ],

    'storage' => [
        'disk' => env('DOCUMENT_STORAGE_DISK', 'local'),
        'encrypt' => env('DOCUMENT_ENCRYPT', true),
        'prefix' => 'documents',
    ],

    'thumbnails' => [
        'enabled' => env('DOCUMENT_THUMBNAILS_ENABLED', true),
        'width' => 300,
        'dpi' => 150,
        'prefix' => 'thumbnails',
    ],

    'security' => [
        'virus_scan_enabled' => env('DOCUMENT_VIRUS_SCAN', false),
        'reject_javascript' => env('DOCUMENT_REJECT_JS', false),
        'reject_encrypted' => true,
    ],
];
```

---

## Lista de Archivos a Crear

### Migraciones (7 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 1 | `database/migrations/2025_01_01_000040_create_documents_table.php` | Tabla de documentos |
| 2 | `database/migrations/2025_01_01_000041_create_verification_codes_table.php` | Códigos de verificación |
| 3 | `database/migrations/2025_01_01_000042_create_verification_logs_table.php` | Log de verificaciones |
| 4 | `database/migrations/2025_01_01_000043_create_archived_documents_table.php` | Documentos archivados |
| 5 | `database/migrations/2025_01_01_000044_create_tsa_chains_table.php` | Cadenas TSA |
| 6 | `database/migrations/2025_01_01_000045_create_tsa_chain_entries_table.php` | Entradas de cadena TSA |
| 7 | `database/migrations/2025_01_01_000046_create_retention_policies_table.php` | Políticas de retención |

### Modelos (7 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 8 | `app/Models/Document.php` | Modelo de documento |
| 9 | `app/Models/VerificationCode.php` | Modelo de código de verificación |
| 10 | `app/Models/VerificationLog.php` | Modelo de log de verificación |
| 11 | `app/Models/ArchivedDocument.php` | Modelo de documento archivado |
| 12 | `app/Models/TsaChain.php` | Modelo de cadena TSA |
| 13 | `app/Models/TsaChainEntry.php` | Modelo de entrada de cadena |
| 14 | `app/Models/RetentionPolicy.php` | Modelo de política de retención |

### Servicios (8 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 15 | `app/Services/Document/DocumentUploadService.php` | Servicio de subida |
| 16 | `app/Services/Document/PdfValidationService.php` | Validación de PDF |
| 17 | `app/Services/Verification/PublicVerificationService.php` | Verificación pública |
| 18 | `app/Services/Verification/VerificationResult.php` | DTO de resultado |
| 19 | `app/Services/Archive/LongTermArchiveService.php` | Archivo a largo plazo |
| 20 | `app/Services/Archive/TsaResealService.php` | Re-sellado TSA |
| 21 | `app/Services/Archive/FormatMigrationService.php` | Migración de formatos |
| 22 | `app/Services/Archive/RetentionPolicyService.php` | Políticas de retención |

### Controladores (2 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 23 | `app/Http/Controllers/Api/PublicVerificationController.php` | API de verificación |
| 24 | `app/Http/Controllers/DocumentController.php` | Controlador de documentos |

### Componentes Livewire (2 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 25 | `app/Livewire/Document/UploadDocument.php` | Componente de subida |
| 26 | `app/Livewire/Verification/PublicVerifier.php` | Verificador público |

### Vistas (3 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 27 | `resources/views/livewire/document/upload-document.blade.php` | Vista de subida |
| 28 | `resources/views/livewire/verification/public-verifier.blade.php` | Vista de verificación |
| 29 | `resources/views/layouts/public.blade.php` | Layout público |

### Comandos (3 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 30 | `app/Console/Commands/ResealTsaChainsCommand.php` | Re-sellado TSA |
| 31 | `app/Console/Commands/VerifyArchiveIntegrityCommand.php` | Verificar integridad |
| 32 | `app/Console/Commands/ProcessRetentionPoliciesCommand.php` | Procesar retención |

### Configuración (2 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 33 | `config/archive.php` | Configuración de archivo |
| 34 | `config/documents.php` | Configuración de documentos |

### Tests (6 archivos)

| # | Archivo | Descripción |
|---|---------|-------------|
| 35 | `tests/Unit/Document/DocumentUploadServiceTest.php` | Tests de subida |
| 36 | `tests/Unit/Document/PdfValidationServiceTest.php` | Tests de validación |
| 37 | `tests/Unit/Verification/PublicVerificationServiceTest.php` | Tests de verificación |
| 38 | `tests/Unit/Archive/TsaResealServiceTest.php` | Tests de re-sellado |
| 39 | `tests/Feature/Api/PublicVerificationApiTest.php` | Tests de API |
| 40 | `tests/Feature/Document/DocumentUploadFlowTest.php` | Tests de flujo |

**Total: 40 archivos**

---

## Plan de Implementación Priorizado

### Fase 1: Document Upload (E2-001) - 3-4 días
**Prioridad: ALTA** - Base para las demás funcionalidades

1. [ ] Crear migración y modelo `Document`
2. [ ] Implementar `PdfValidationService`
3. [ ] Implementar `DocumentUploadService`
4. [ ] Crear componente Livewire `UploadDocument`
5. [ ] Crear vistas Blade
6. [ ] Tests unitarios y de integración

### Fase 2: Public Verification API (E1-009) - 3-4 días
**Prioridad: CRÍTICA** - Requerimiento legal

1. [ ] Crear migraciones `verification_codes` y `verification_logs`
2. [ ] Crear modelos `VerificationCode` y `VerificationLog`
3. [ ] Implementar `PublicVerificationService`
4. [ ] Crear `PublicVerificationController` API
5. [ ] Configurar rate limiting
6. [ ] Crear componente Livewire `PublicVerifier`
7. [ ] Crear página web de verificación
8. [ ] Tests de API y seguridad

### Fase 3: Long-Term Archive (E1-008) - 4-5 días
**Prioridad: CRÍTICA** - Cumplimiento normativo

1. [ ] Crear migraciones de archivo
2. [ ] Crear modelos de archivo
3. [ ] Implementar `TsaResealService`
4. [ ] Implementar `LongTermArchiveService`
5. [ ] Implementar `FormatMigrationService`
6. [ ] Implementar `RetentionPolicyService`
7. [ ] Crear comandos de consola para cron jobs
8. [ ] Configurar scheduler
9. [ ] Tests de cadena TSA y re-sellado

### Fase 4: Integración y QA - 2-3 días

1. [ ] Integrar upload con sistema de evidencias existente
2. [ ] Integrar verificación con audit trail
3. [ ] Tests de integración end-to-end
4. [ ] Pruebas de rendimiento API
5. [ ] Revisión de seguridad
6. [ ] Documentación de API

---

## Consecuencias

### Positivas

- ✅ **Cumplimiento eIDAS**: Preservación a largo plazo con re-sellado TSA
- ✅ **Verificabilidad pública**: Cualquier parte puede verificar documentos
- ✅ **Seguridad**: PDFs cifrados, validados y con control de virus
- ✅ **Escalabilidad**: Almacenamiento en frío para archivos antiguos
- ✅ **Trazabilidad**: Cadena completa de custodia verificable
- ✅ **Usabilidad**: Interfaz simple para subida de documentos

### Negativas

- ⚠️ **Coste TSA**: Re-sellado periódico incrementa costes
- ⚠️ **Almacenamiento**: Archivos duplicados (original + PDF/A)
- ⚠️ **Complejidad**: Múltiples servicios y cadenas de dependencia
- ⚠️ **Latencia API**: Rate limiting puede afectar alto volumen

### Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| TSA provider no disponible | Media | Alto | Fallback a múltiples TSA |
| Corrupción en cold storage | Baja | Crítico | Checksums, replicación |
| API abuse/DDoS | Media | Medio | Rate limiting, Cloudflare |
| Expiración certificados TSA | Media | Alto | Alertas, re-sellado auto |
| Fallo conversión PDF/A | Baja | Bajo | Mantener original |

---

## Métricas de Éxito

| Métrica | Objetivo | Medición |
|---------|----------|----------|
| Uptime API verificación | 99.9% | Monitoring |
| Tiempo respuesta verificación | < 500ms | APM |
| Tasa re-sellado exitoso | 100% | Logs |
| Documentos archivados correctamente | 100% | Verificación diaria |
| PDFs rechazados por validación | < 5% | Analytics |
| Cobertura de tests | > 80% | CI/CD |

---

## Referencias

- [eIDAS Regulation (EU) 910/2014](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=uriserv:OJ.L_.2014.257.01.0073.01.ENG)
- [ETSI EN 319 122-1 - CAdES Long Term Signatures](https://www.etsi.org/deliver/etsi_en/319100_319199/31912201/)
- [PDF/A Standard ISO 19005](https://www.pdfa.org/pdfa-specification/)
- [RFC 3161 - Time-Stamp Protocol](https://www.rfc-editor.org/rfc/rfc3161)
- [OWASP File Upload Security](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- ADR-005: Sistema de Evidencias Core
- ADR-006: Captura de Evidencias Avanzadas

---

**LISTO PARA DESARROLLO**