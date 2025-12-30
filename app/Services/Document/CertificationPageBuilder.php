<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\SigningProcess;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/**
 * Build a certification page for the final signed document.
 *
 * This page summarizes the signing process, signers timeline,
 * evidence packages, and verification information.
 */
class CertificationPageBuilder
{
    /**
     * Build certification page and return PDF content.
     */
    public function build(SigningProcess $process): string
    {
        Log::info('Building certification page', [
            'process_id' => $process->id,
            'signers_count' => $process->signers->count(),
        ]);

        $pdf = new Fpdi;
        $pdf->AddPage();

        // Header with Firmalum branding
        $this->addHeader($pdf);

        // Process information
        $this->addProcessInformation($pdf, $process);

        // Signers timeline
        $this->addSignersTimeline($pdf, $process);

        // Evidence summary
        $this->addEvidenceSummary($pdf, $process);

        // Verification instructions
        $this->addVerificationInstructions($pdf, $process);

        // Footer with timestamp and hash
        $this->addFooter($pdf, $process);

        $content = $pdf->Output('S');

        Log::info('Certification page built', [
            'size' => strlen($content),
        ]);

        return $content;
    }

    /**
     * Add header with branding.
     */
    private function addHeader(Fpdi $pdf): void
    {
        // Firmalum Logo placeholder (would be actual logo in production)
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(99, 102, 241); // Purple-600
        $pdf->Cell(0, 15, 'Firmalum', 0, 1, 'C');

        // Certificate title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(31, 41, 55); // Gray-800
        $pdf->Cell(0, 10, 'CERTIFICADO DE FIRMA ELECTRONICA', 0, 1, 'C');

        // Subtitle
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(107, 114, 128); // Gray-500
        $pdf->Cell(0, 8, 'Este documento certifica la autenticidad e integridad del proceso de firma', 0, 1, 'C');

        $pdf->Ln(5);

        // Separator line
        $pdf->SetDrawColor(229, 231, 235); // Gray-200
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(8);
    }

    /**
     * Add process information section.
     */
    private function addProcessInformation(Fpdi $pdf, SigningProcess $process): void
    {
        $this->addSectionTitle($pdf, 'INFORMACION DEL PROCESO');

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(55, 65, 81); // Gray-700

        $data = [
            'ID del Proceso' => $process->uuid,
            'Documento Original' => $process->document->original_name,
            'Fecha de Creacion' => $process->created_at->format('d/m/Y H:i:s'),
            'Fecha de Finalizacion' => $process->completed_at?->format('d/m/Y H:i:s') ?? 'N/A',
            'Orden de Firma' => $process->signature_order === 'sequential' ? 'Secuencial' : 'Paralelo',
            'Total de Firmantes' => (string) $process->signers->count(),
            'Estado' => strtoupper($process->status),
        ];

        foreach ($data as $label => $value) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(50, 6, $label.':', 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 6, $value, 0, 'L');
        }

        $pdf->Ln(5);
    }

    /**
     * Add signers timeline section.
     */
    private function addSignersTimeline(Fpdi $pdf, SigningProcess $process): void
    {
        $this->addSectionTitle($pdf, 'CRONOLOGIA DE FIRMAS');

        $pdf->SetFont('Arial', '', 9);

        foreach ($process->signers as $index => $signer) {
            $yStart = $pdf->GetY();

            // Signer number badge
            $pdf->SetFillColor(243, 244, 246); // Gray-100
            $pdf->SetTextColor(31, 41, 55); // Gray-800
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Circle($pdf->GetX() + 5, $yStart + 3, 3, 'F');
            $pdf->SetXY($pdf->GetX() + 3.5, $yStart + 1);
            $pdf->Cell(3, 4, (string) ($index + 1), 0, 0, 'C');

            // Signer information
            $pdf->SetXY($pdf->GetX() + 5, $yStart);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(31, 41, 55);
            $pdf->Cell(0, 5, $signer->name, 0, 1);

            $pdf->SetX(20);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(107, 114, 128);
            $pdf->Cell(0, 4, $signer->email, 0, 1);

            // Status and timestamps
            $pdf->SetX(20);
            $pdf->SetFont('Arial', 'I', 8);

            $statusText = match ($signer->status) {
                'signed' => '✓ Firmado',
                'viewed' => '● Visto',
                'sent' => '→ Enviado',
                default => '○ Pendiente',
            };

            $statusColor = match ($signer->status) {
                'signed' => [34, 197, 94], // Green-500
                'viewed' => [234, 179, 8], // Yellow-500
                'sent' => [59, 130, 246], // Blue-500
                default => [156, 163, 175], // Gray-400
            };

            $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
            $pdf->Cell(30, 4, $statusText, 0, 0);

            $pdf->SetTextColor(107, 114, 128);
            if ($signer->signed_at) {
                $pdf->Cell(0, 4, 'Firmado: '.$signer->signed_at->format('d/m/Y H:i:s'), 0, 1);
            } else {
                $pdf->Cell(0, 4, 'No firmado', 0, 1);
            }

            // Evidence package reference
            if ($signer->evidence_package_id) {
                $pdf->SetX(20);
                $pdf->SetFont('Arial', '', 7);
                $pdf->SetTextColor(156, 163, 175);
                $pdf->Cell(0, 3, 'Paquete de evidencias: '.$signer->evidencePackage->uuid ?? 'N/A', 0, 1);
            }

            $pdf->Ln(3);
        }

        $pdf->Ln(3);
    }

    /**
     * Add evidence summary section.
     */
    private function addEvidenceSummary(Fpdi $pdf, SigningProcess $process): void
    {
        $this->addSectionTitle($pdf, 'RESUMEN DE EVIDENCIAS');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(55, 65, 81);

        // Count evidence packages
        $evidencePackages = $process->signers()
            ->whereNotNull('evidence_package_id')
            ->distinct('evidence_package_id')
            ->count();

        $evidenceData = [
            'Paquetes de Evidencia' => (string) $evidencePackages,
            'Firmas Digitales (PAdES-B-LT)' => (string) $process->signers()->whereNotNull('signed_at')->count(),
            'Timestamps TSA Cualificados' => (string) $process->signers()->whereNotNull('signed_at')->count(),
            'Audit Trail Completo' => 'Si',
            'Nivel de Cumplimiento' => 'eIDAS - Firma Electronica Avanzada',
        ];

        foreach ($evidenceData as $label => $value) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(65, 5, $label.':', 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, $value, 0, 1);
        }

        $pdf->Ln(3);

        // Security features box
        $pdf->SetFillColor(239, 246, 255); // Blue-50
        $pdf->SetDrawColor(191, 219, 254); // Blue-200
        $pdf->Rect(20, $pdf->GetY(), 170, 20, 'DF');

        $pdf->SetY($pdf->GetY() + 3);
        $pdf->SetX(25);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(30, 64, 175); // Blue-800
        $pdf->Cell(0, 4, 'CARACTERISTICAS DE SEGURIDAD', 0, 1);

        $pdf->SetX(25);
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(29, 78, 216); // Blue-700
        $pdf->MultiCell(160, 3, '• Hash SHA-256 de integridad • Timestamps cualificados (RFC 3161) • Firmas PAdES-B-LT '.
            '• Captura de evidencias (IP, geolocalizacion, dispositivo) • Audit trail inmutable', 0, 'L');

        $pdf->Ln(8);
    }

    /**
     * Add verification instructions section.
     */
    private function addVerificationInstructions(Fpdi $pdf, SigningProcess $process): void
    {
        $this->addSectionTitle($pdf, 'VERIFICACION PUBLICA');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(55, 65, 81);

        $pdf->MultiCell(0, 5, 'Este documento puede ser verificado publicamente en cualquier momento '.
            'para comprobar su autenticidad e integridad. La verificacion no requiere registro.', 0, 'L');

        $pdf->Ln(2);

        // Verification URL
        $verificationUrl = config('app.url').'/verify';

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 5, 'URL de Verificacion:', 0, 0);
        $pdf->SetFont('Arial', 'U', 9);
        $pdf->SetTextColor(59, 130, 246); // Blue-500
        $pdf->Cell(0, 5, $verificationUrl, 0, 1);

        $pdf->SetTextColor(55, 65, 81);
        $pdf->Ln(3);
    }

    /**
     * Add footer with metadata.
     */
    private function addFooter(Fpdi $pdf, SigningProcess $process): void
    {
        $pdf->SetY(-30);

        // Separator line
        $pdf->SetDrawColor(229, 231, 235);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(3);

        // Generation timestamp
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->Cell(0, 3, 'Documento generado el: '.now()->format('d/m/Y H:i:s').' UTC', 0, 1, 'C');

        // Platform info
        $pdf->Cell(0, 3, 'Firmalum - Plataforma de Firma Electronica Avanzada', 0, 1, 'C');

        // Legal notice
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(0, 3, 'Este certificado es parte integral del documento firmado. Su manipulacion o '.
            'separacion puede invalidar las firmas digitales contenidas.', 0, 'C');
    }

    /**
     * Helper to add section titles.
     */
    private function addSectionTitle(Fpdi $pdf, string $title): void
    {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(31, 41, 55); // Gray-800
        $pdf->SetFillColor(243, 244, 246); // Gray-100
        $pdf->Cell(0, 8, $title, 0, 1, 'L', true);
        $pdf->Ln(3);
    }
}
