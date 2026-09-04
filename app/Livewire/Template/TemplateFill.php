<?php

namespace App\Livewire\Template;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\SigningProcess;
use App\Services\Template\TemplateException;
use App\Services\Template\TemplateProcessService;
use App\Services\Template\TemplateRenderException;
use App\Services\Template\TemplateRenderService;
use App\Services\Template\TemplateSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rellena una plantilla y genera el proceso de firma.
 *
 * El formulario NO esta escrito a mano: se deriva del esquema de la version
 * publicada, igual que hara el cuerpo JSON de la API. Anadir un campo en el
 * editor lo hace aparecer aqui sin tocar esta clase.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
#[Layout('components.layouts.app')]
class TemplateFill extends Component
{
    public DocumentTemplate $template;

    public DocumentTemplateVersion $version;

    /**
     * Valores del formulario, indexados por la clave del campo.
     *
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * Firmantes del proceso.
     *
     * Si la plantilla fija roles, hay una entrada por rol y su clave 'role'
     * viene rellena. Si no los fija, es una lista libre a la que se anaden y
     * quitan filas: quien firma se decide al usar la plantilla.
     *
     * @var list<array{name: string, email: string, phone: string, role: string|null, label: string}>
     */
    public array $signers = [];

    public string $customMessage = '';

    public string $deadlineAt = '';

    public string $signatureOrder = SigningProcess::ORDER_PARALLEL;

    public bool $sendNow = true;

    public bool $generating = false;

    public string $error = '';

    /**
     * Clave en cache del PDF de vista previa. Nunca se persiste en disco:
     * es un borrador que caduca solo.
     */
    public ?string $previewKey = null;

    public string $previewError = '';

    public function mount(DocumentTemplate $template): void
    {
        if (! $template->isUsable()) {
            abort(409, 'Esta plantilla no esta habilitada todavia.');
        }

        $this->template = $template;
        $this->version = $template->currentVersion;

        $this->values = $this->schema()->defaults();

        $roles = $this->version->signerRoles;

        if ($roles->isEmpty()) {
            $this->addSigner();

            return;
        }

        foreach ($roles as $role) {
            $this->signers[] = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'role' => $role->role_key,
                'label' => $role->label,
            ];
        }
    }

    /**
     * La plantilla no fija roles, asi que los firmantes son libres.
     */
    public function hasFixedRoles(): bool
    {
        return $this->version->signerRoles()->exists();
    }

    public function addSigner(): void
    {
        if ($this->hasFixedRoles()) {
            return;
        }

        $this->signers[] = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'role' => null,
            'label' => 'Firmante '.(count($this->signers) + 1),
        ];
    }

    public function removeSigner(int $index): void
    {
        if ($this->hasFixedRoles() || count($this->signers) <= 1 || ! isset($this->signers[$index])) {
            return;
        }

        unset($this->signers[$index]);
        $this->signers = array_values($this->signers);
    }

    public function schema(): TemplateSchema
    {
        return TemplateSchema::for($this->version);
    }

    /**
     * Reglas derivadas del esquema, para que Livewire valide en vivo con las
     * mismas que aplicara el servicio al generar.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = $this->schema()->rules('values');

        foreach (array_keys($this->signers) as $index) {
            $rules["signers.{$index}.name"] = ['required', 'string', 'max:255'];
            $rules["signers.{$index}.email"] = ['required', 'email', 'max:255'];
            $rules["signers.{$index}.phone"] = ['nullable', 'string', 'max:20'];
        }

        $rules['customMessage'] = ['nullable', 'string', 'max:500'];
        $rules['deadlineAt'] = ['nullable', 'date', 'after:today'];
        $rules['signatureOrder'] = ['required', 'in:sequential,parallel'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = $this->schema()->attributes('values');

        foreach ($this->signers as $index => $signer) {
            $etiqueta = $signer['label'] ?: 'firmante '.($index + 1);
            $attributes["signers.{$index}.name"] = "nombre de {$etiqueta}";
            $attributes["signers.{$index}.email"] = "correo de {$etiqueta}";
        }

        return $attributes;
    }

    /**
     * Genera una vista previa con lo que haya escrito hasta ahora.
     *
     * NO se valida: el formulario puede estar a medias y la gracia es ver el
     * documento mientras se rellena. Los campos vacios simplemente no se
     * estampan.
     *
     * Si un valor no cabe en su caja, el renderizador lanza; aqui eso no es
     * un fallo sino justo lo que hay que enseñar, antes de mandar un contrato
     * con el importe cortado.
     */
    public function preview(TemplateRenderService $renderer): void
    {
        $this->previewError = '';
        $this->error = '';

        $signersByRole = [];
        foreach ($this->signers as $signer) {
            if (filled($signer['role'] ?? null)) {
                $signersByRole[$signer['role']] = $signer;
            }
        }

        try {
            $pdf = $renderer->render($this->version, $this->values, $signersByRole);
        } catch (TemplateRenderException $e) {
            $this->previewKey = null;
            $this->previewError = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->previewKey = null;
            $this->previewError = 'No se pudo generar la vista previa.';

            return;
        }

        $key = Str::random(40);

        Cache::put(
            self::previewCacheKey($key),
            ['user_id' => auth()->id(), 'pdf' => $pdf],
            now()->addMinutes(10),
        );

        $this->previewKey = $key;
    }

    public static function previewCacheKey(string $key): string
    {
        return 'template-preview:'.$key;
    }

    /**
     * Cualquier cambio invalida la vista previa: mejor sin ella que
     * enseñando una que ya no corresponde con lo escrito.
     */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'values.') || str_starts_with($property, 'signers.')) {
            $this->previewKey = null;
        }
    }

    public function generate(TemplateProcessService $processes): void
    {
        $this->error = '';
        $this->generating = true;

        try {
            $this->validate();

            $process = $processes->createProcess(
                template: $this->template,
                author: auth()->user(),
                values: $this->values,
                signers: $this->signers,
                customMessage: filled($this->customMessage) ? $this->customMessage : null,
                deadlineAt: filled($this->deadlineAt) ? $this->deadlineAt : null,
                signatureOrder: $this->signatureOrder,
            );

            if ($this->sendNow) {
                $process->sendNotifications();
            }
        } catch (ValidationException $e) {
            $this->generating = false;

            throw $e;
        } catch (TemplateRenderException|TemplateException $e) {
            // Un valor que no cabe en su caja llega aqui: es un mensaje util
            // para quien rellena, no un error interno.
            $this->error = $e->getMessage();
            $this->generating = false;

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'No se pudo generar el documento. Intentalo de nuevo.';
            $this->generating = false;

            return;
        }

        $this->redirectRoute('signing-processes.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.template.template-fill', [
            'fields' => $this->schema()->inputFields(),
            'fixedRoles' => $this->hasFixedRoles(),
        ]);
    }
}
