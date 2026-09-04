<?php

namespace App\Livewire\Template;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSigner;
use App\Models\DocumentTemplateVersion;
use App\Models\SigningProcess;
use App\Services\Template\TemplateException;
use App\Services\Template\TemplateProcessService;
use App\Services\Template\TemplateRenderException;
use App\Services\Template\TemplateSchema;
use Illuminate\Support\Collection;
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
     * Firmantes asignados a cada rol previsto.
     *
     * @var array<string, array{name: string, email: string, phone: string}>
     */
    public array $signers = [];

    public string $customMessage = '';

    public string $deadlineAt = '';

    public string $signatureOrder = SigningProcess::ORDER_PARALLEL;

    public bool $sendNow = true;

    public bool $generating = false;

    public string $error = '';

    public function mount(DocumentTemplate $template): void
    {
        if (! $template->isUsable()) {
            abort(409, 'Esta plantilla no esta habilitada todavia.');
        }

        $this->template = $template;
        $this->version = $template->currentVersion;

        $this->values = $this->schema()->defaults();

        foreach ($this->version->signerRoles as $role) {
            $this->signers[$role->role_key] = ['name' => '', 'email' => '', 'phone' => ''];
        }
    }

    public function schema(): TemplateSchema
    {
        return TemplateSchema::for($this->version);
    }

    /**
     * Roles de firmante previstos por la plantilla.
     *
     * Metodo normal y no propiedad computada de Livewire: la vista los recibe
     * desde render(), asi que la magia no aporta nada y oscurece el tipo.
     *
     * @return Collection<int, DocumentTemplateSigner>
     */
    public function signerRoles(): Collection
    {
        return $this->version->signerRoles()->get();
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

        foreach (array_keys($this->signers) as $roleKey) {
            $rules["signers.{$roleKey}.name"] = ['required', 'string', 'max:255'];
            $rules["signers.{$roleKey}.email"] = ['required', 'email', 'max:255'];
            $rules["signers.{$roleKey}.phone"] = ['nullable', 'string', 'max:20'];
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

        foreach ($this->signerRoles() as $role) {
            $attributes["signers.{$role->role_key}.name"] = "nombre de {$role->label}";
            $attributes["signers.{$role->role_key}.email"] = "correo de {$role->label}";
        }

        return $attributes;
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
            'roles' => $this->signerRoles(),
        ]);
    }
}
