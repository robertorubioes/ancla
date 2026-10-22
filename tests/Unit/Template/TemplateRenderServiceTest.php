<?php

declare(strict_types=1);

namespace Tests\Unit\Template;

use App\Enums\TemplateFieldType;
use App\Models\Document;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use App\Services\Template\TemplateRenderException;
use App\Services\Template\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class TemplateRenderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TemplateRenderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::factory()->create();
        $this->service = app(TemplateRenderService::class);
    }

    /**
     * Un PDF real y parseable, generado con FPDF.
     *
     * @param  list<string>  $pages  Un texto por pagina
     */
    private function realPdf(array $pages = ['Documento base'], string $orientation = 'P', string $size = 'A4'): string
    {
        $pdf = new \FPDF($orientation, 'mm', $size);

        foreach ($pages as $text) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(0, 10, $text, 0, 1);
        }

        return $pdf->Output('S');
    }

    private function versionWithPdf(string $pdfContent): DocumentTemplateVersion
    {
        $path = 'documents/test';
        $filename = Str::uuid().'.pdf';

        Storage::disk('local')->put("{$path}/{$filename}", $pdfContent);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'storage_disk' => 'local',
            'storage_path' => "{$path}/{$filename}",
            'stored_filename' => $filename,
            'is_encrypted' => false,
        ]);

        return DocumentTemplateVersion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function field(DocumentTemplateVersion $version, array $attributes): DocumentTemplateField
    {
        return DocumentTemplateField::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $version->id,
        ], $attributes));
    }

    private function textOf(string $pdf): string
    {
        return (new Parser)->parseContent($pdf)->getText();
    }

    public function test_estampa_los_valores_en_el_documento(): void
    {
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, ['key' => 'nombre', 'label' => 'Nombre']);

        $result = $this->service->render($version, ['nombre' => 'Maria Lopez']);

        $this->assertStringStartsWith('%PDF-', $result);
        $this->assertStringContainsString('Maria Lopez', $this->textOf($result));
    }

    public function test_conserva_las_tildes_y_las_enes(): void
    {
        // Las fuentes core de FPDF son Latin-1: sin conversion esto saldria
        // como mojibake, y aqui casi todo el contenido va en espanol.
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, ['key' => 'nombre']);

        $result = $this->service->render($version, ['nombre' => 'Muñoz Peña']);

        $this->assertStringContainsString('Muñoz Peña', $this->textOf($result));
    }

    public function test_formatea_numeros_y_fechas_al_estilo_local(): void
    {
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, [
            'key' => 'importe',
            'type' => TemplateFieldType::NUMBER,
        ]);
        $this->field($version, [
            'key' => 'alta',
            'type' => TemplateFieldType::DATE,
            'y' => 60.0,
        ]);

        $text = $this->textOf($this->service->render($version, [
            'importe' => 1234.5,
            'alta' => '2026-03-15',
        ]));

        $this->assertStringContainsString('1.234,50', $text);
        $this->assertStringContainsString('15/03/2026', $text);
    }

    public function test_un_desplegable_estampa_la_etiqueta_y_no_el_valor(): void
    {
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, [
            'key' => 'plan',
            'type' => TemplateFieldType::SELECT,
            'options' => [
                ['value' => 'anual', 'label' => 'Pago anual'],
            ],
        ]);

        $text = $this->textOf($this->service->render($version, ['plan' => 'anual']));

        $this->assertStringContainsString('Pago anual', $text);
    }

    public function test_rellena_los_campos_calculados(): void
    {
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, [
            'key' => 'firmante',
            'type' => TemplateFieldType::SIGNER_NAME,
            'validation' => ['role' => 'arrendatario'],
        ]);
        $this->field($version, [
            'key' => 'hoy',
            'type' => TemplateFieldType::TODAY,
            'y' => 60.0,
        ]);

        $text = $this->textOf($this->service->render($version, [], [
            'arrendatario' => ['name' => 'Ana Ruiz', 'email' => 'ana@ejemplo.com'],
        ]));

        $this->assertStringContainsString('Ana Ruiz', $text);
        $this->assertStringContainsString(now()->format('d/m/Y'), $text);
    }

    public function test_usa_el_valor_por_defecto_cuando_no_se_manda_nada(): void
    {
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, ['key' => 'ciudad', 'default_value' => 'Madrid']);

        $text = $this->textOf($this->service->render($version, []));

        $this->assertStringContainsString('Madrid', $text);
    }

    public function test_aborta_si_el_valor_no_cabe_en_su_caja(): void
    {
        // Preferimos un error a un contrato con el importe cortado a la mitad.
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, [
            'key' => 'nombre',
            'label' => 'Nombre',
            'width' => 10.0,
            'font_size' => 10,
        ]);

        $this->expectException(TemplateRenderException::class);
        $this->expectExceptionMessage('no cabe en el espacio reservado');

        $this->service->render($version, [
            'nombre' => 'Un nombre desmesuradamente largo que jamas entraria en diez milimetros',
        ]);
    }

    public function test_reduce_el_cuerpo_de_letra_antes_de_rendirse(): void
    {
        $version = $this->versionWithPdf($this->realPdf());
        $this->field($version, [
            'key' => 'nombre',
            'width' => 40.0,
            'font_size' => 14,
        ]);

        // Con 14pt no entra; reduciendo, si. No debe lanzar.
        $text = $this->textOf($this->service->render($version, [
            'nombre' => 'Maria del Carmen Lopez',
        ]));

        $this->assertStringContainsString('Maria del Carmen Lopez', $text);
    }

    public function test_aborta_si_un_campo_apunta_a_una_pagina_inexistente(): void
    {
        $version = $this->versionWithPdf($this->realPdf(['Unica pagina']));
        $this->field($version, ['key' => 'nombre', 'page' => 7]);

        $this->expectException(TemplateRenderException::class);
        $this->expectExceptionMessage('pagina 7');

        $this->service->render($version, ['nombre' => 'Maria']);
    }

    public function test_estampa_en_la_pagina_indicada_de_un_documento_de_varias(): void
    {
        $version = $this->versionWithPdf($this->realPdf(['Primera', 'Segunda', 'Tercera']));
        $this->field($version, ['key' => 'marca', 'page' => 3]);

        $result = $this->service->render($version, ['marca' => 'EN LA TERCERA']);
        $parsed = (new Parser)->parseContent($result);

        $this->assertCount(3, $parsed->getPages());
        $this->assertStringContainsString('EN LA TERCERA', $parsed->getPages()[2]->getText());
    }

    public function test_conserva_el_tamano_y_la_orientacion_de_la_pagina(): void
    {
        // PdfEmbedder importa todo como A4 vertical; aqui no.
        $version = $this->versionWithPdf($this->realPdf(['Apaisado'], 'L', 'A4'));
        $this->field($version, ['key' => 'nombre']);

        $result = $this->service->render($version, ['nombre' => 'Maria']);

        $details = (new Parser)->parseContent($result)->getPages()[0]->getDetails();
        [$x0, $y0, $x1, $y1] = $details['MediaBox'];

        $this->assertGreaterThan(
            (float) $y1 - (float) $y0,
            (float) $x1 - (float) $x0,
            'Una pagina apaisada debe seguir siendo mas ancha que alta.'
        );
    }

    public function test_los_campos_vacios_no_dejan_rastro(): void
    {
        $version = $this->versionWithPdf($this->realPdf(['Base']));
        $this->field($version, ['key' => 'opcional', 'required' => false]);

        $text = $this->textOf($this->service->render($version, ['opcional' => null]));

        $this->assertStringContainsString('Base', $text);
    }
}
