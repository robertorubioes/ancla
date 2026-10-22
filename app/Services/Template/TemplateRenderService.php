<?php

namespace App\Services\Template;

use App\Enums\TemplateFieldType;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateVersion;
use App\Services\Document\DocumentUploadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;

/**
 * Genera el PDF final estampando los valores sobre el PDF base.
 *
 * Reutiliza la mecanica que PdfEmbedder ya usa para la firma: importar el PDF
 * con FPDI y dibujar en coordenadas. A diferencia de aquel, aqui se conserva
 * el tamano y la orientacion de cada pagina original, de modo que funciona
 * con documentos que no sean A4 vertical.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
class TemplateRenderService
{
    /**
     * Cuerpo de letra minimo antes de dar el valor por no encajable.
     */
    private const MIN_FONT_SIZE = 6;

    private const FONT_FAMILY = 'helvetica';

    public function __construct(
        private readonly DocumentUploadService $documents,
    ) {}

    /**
     * Genera el documento.
     *
     * @param  array<string, mixed>  $values  Valores ya validados por TemplateSchema
     * @param  array<string, array{name?: string, email?: string}>  $signers  Por role_key
     * @return string Contenido del PDF
     *
     * @throws TemplateRenderException
     */
    public function render(
        DocumentTemplateVersion $version,
        array $values,
        array $signers = [],
    ): string {
        $basePdf = $this->baseContent($version);

        $tempPath = tempnam(sys_get_temp_dir(), 'tpl_render_');
        if ($tempPath === false) {
            throw TemplateRenderException::pdfReadFailed('no se pudo crear el fichero temporal');
        }

        try {
            file_put_contents($tempPath, $basePdf);

            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);
            $pdf->SetCreator('Firmalum');

            try {
                $pageCount = $pdf->setSourceFile($tempPath);
            } catch (\Throwable $e) {
                throw TemplateRenderException::pdfReadFailed($e->getMessage());
            }

            $fields = $version->fields()->get();
            $this->assertPagesExist($fields, $pageCount);

            for ($page = 1; $page <= $pageCount; $page++) {
                $templateId = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($templateId);

                // Conservar el tamano y la orientacion originales
                $pdf->AddPage(
                    $size['orientation'],
                    [$size['width'], $size['height']],
                );
                $pdf->useTemplate($templateId);

                foreach ($fields->where('page', $page) as $field) {
                    $text = $this->textFor($field, $values, $signers);

                    if ($text === '') {
                        continue;
                    }

                    $this->stamp($pdf, $field, $text);
                }
            }

            return (string) $pdf->Output('S');
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Contenido del PDF base, descifrandolo si hace falta.
     */
    private function baseContent(DocumentTemplateVersion $version): string
    {
        $document = $version->document;

        if ($document === null) {
            throw TemplateRenderException::pdfReadFailed('la version no tiene documento base');
        }

        return $this->documents->getDecryptedContent($document);
    }

    /**
     * @param  Collection<int, DocumentTemplateField>  $fields
     *
     * @throws TemplateRenderException
     */
    private function assertPagesExist(Collection $fields, int $pageCount): void
    {
        foreach ($fields as $field) {
            if ($field->page < 1 || $field->page > $pageCount) {
                throw TemplateRenderException::pageOutOfRange($field->key, $field->page, $pageCount);
            }
        }
    }

    /**
     * Texto a estampar para un campo, ya formateado segun su tipo.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, array{name?: string, email?: string}>  $signers
     */
    private function textFor(
        DocumentTemplateField $field,
        array $values,
        array $signers,
    ): string {
        if ($field->type->isComputed()) {
            return $this->computedText($field, $signers);
        }

        $value = $values[$field->key] ?? $field->default_value;

        if ($value === null || $value === '') {
            return '';
        }

        return match ($field->type) {
            TemplateFieldType::NUMBER => $this->formatNumber($value),
            TemplateFieldType::DATE => $this->formatDate($value),
            TemplateFieldType::SELECT => $field->optionMap()[(string) $value] ?? (string) $value,
            TemplateFieldType::CHECKBOX => $value ? 'X' : '',
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, array{name?: string, email?: string}>  $signers
     */
    private function computedText(DocumentTemplateField $field, array $signers): string
    {
        if ($field->type === TemplateFieldType::TODAY) {
            return Carbon::now()->format('d/m/Y');
        }

        // El rol al que se refiere viaja en validation.role, para no anadir
        // otra columna a una tabla que ya es ancha.
        $roleKey = $field->validation['role'] ?? null;

        if (! is_string($roleKey) || ! isset($signers[$roleKey])) {
            return '';
        }

        return match ($field->type) {
            TemplateFieldType::SIGNER_NAME => (string) ($signers[$roleKey]['name'] ?? ''),
            TemplateFieldType::SIGNER_EMAIL => (string) ($signers[$roleKey]['email'] ?? ''),
            default => '',
        };
    }

    private function formatNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $number = (float) $value;

        return $number === floor($number)
            ? number_format($number, 0, ',', '.')
            : number_format($number, 2, ',', '.');
    }

    private function formatDate(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Dibuja el texto dentro de la caja del campo.
     *
     * Si no entra, reduce el cuerpo de letra. Si aun asi no cabe, aborta en
     * lugar de producir un documento con texto recortado o superpuesto.
     *
     * @throws TemplateRenderException
     */
    private function stamp(Fpdi $pdf, DocumentTemplateField $field, string $text): void
    {
        $encoded = $this->toPdfEncoding($text);
        $multiline = $field->type === TemplateFieldType::TEXTAREA;

        $fontSize = $this->fittingFontSize($pdf, $field, $encoded, $multiline);

        $pdf->SetFont(self::FONT_FAMILY, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($field->x, $field->y);

        $align = match ($field->align) {
            'center' => 'C',
            'right' => 'R',
            default => 'L',
        };

        if ($multiline) {
            $pdf->MultiCell($field->width, $fontSize * 0.45, $encoded, 0, $align);

            return;
        }

        $pdf->Cell($field->width, $field->height, $encoded, 0, 0, $align);
    }

    /**
     * Mayor cuerpo de letra, sin pasar del declarado, con el que el texto
     * entra en la caja.
     *
     * @throws TemplateRenderException
     */
    private function fittingFontSize(
        Fpdi $pdf,
        DocumentTemplateField $field,
        string $text,
        bool $multiline,
    ): int {
        for ($size = $field->font_size; $size >= self::MIN_FONT_SIZE; $size--) {
            $pdf->SetFont(self::FONT_FAMILY, '', $size);

            if ($multiline) {
                if ($this->multilineFits($pdf, $field, $text, $size)) {
                    return $size;
                }

                continue;
            }

            if ($pdf->GetStringWidth($text) <= $field->width) {
                return $size;
            }
        }

        throw TemplateRenderException::valueDoesNotFit($field->key, $field->label);
    }

    private function multilineFits(Fpdi $pdf, DocumentTemplateField $field, string $text, int $size): bool
    {
        $lineHeight = $size * 0.45;
        $maxLines = (int) floor($field->height / $lineHeight);

        if ($maxLines < 1) {
            return false;
        }

        $lines = 1;
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($pdf->GetStringWidth($candidate) <= $field->width) {
                $current = $candidate;

                continue;
            }

            // Una sola palabra que no entra en el ancho: no hay reparto posible
            if ($current === '') {
                return false;
            }

            $lines++;
            $current = $word;
        }

        return $lines <= $maxLines;
    }

    /**
     * Las fuentes core de FPDF son Latin-1: sin esta conversion, cualquier
     * tilde o eñe sale como mojibake.
     */
    private function toPdfEncoding(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);

        return $converted === false ? $text : $converted;
    }
}
