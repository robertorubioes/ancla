<?php

namespace App\Livewire\Template;

use App\Enums\TemplateFieldType;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateSigner;
use App\Models\DocumentTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Editor visual de una plantilla.
 *
 * Coloca cajas de campo sobre las paginas del PDF. El navegador se encarga de
 * pintar y arrastrar; aqui solo llegan coordenadas en milimetros, y toda la
 * validacion y la persistencia ocurren en el servidor.
 *
 * Solo se editan versiones en borrador: una version publicada es inmutable,
 * porque hay procesos de firma que apuntan a ella.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
#[Layout('components.layouts.app')]
class TemplateEditor extends Component
{
    public DocumentTemplate $template;

    public DocumentTemplateVersion $version;

    /**
     * Campos en edicion. Indice del array = identidad temporal en la interfaz.
     *
     * @var list<array<string, mixed>>
     */
    public array $fields = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $signerRoles = [];

    public ?int $selectedField = null;

    public string $error = '';

    public string $success = '';

    public function mount(DocumentTemplate $template): void
    {
        $this->template = $template;

        $version = $this->resolveEditableVersion($template);

        if ($version === null) {
            abort(409, 'Esta plantilla no tiene ninguna version en borrador que editar.');
        }

        $this->version = $version;
        $this->loadFromVersion();
    }

    /**
     * La version editable es la ultima sin publicar.
     */
    private function resolveEditableVersion(DocumentTemplate $template): ?DocumentTemplateVersion
    {
        return $template->versions()
            ->whereNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    private function loadFromVersion(): void
    {
        $this->fields = $this->version->fields()
            ->get()
            ->map(static fn (DocumentTemplateField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'help_text' => $field->help_text,
                'type' => $field->type->value,
                'required' => $field->required,
                'default_value' => $field->default_value,
                'options' => $field->options ?? [],
                'validation' => $field->validation ?? [],
                'page' => $field->page,
                'x' => $field->x,
                'y' => $field->y,
                'width' => $field->width,
                'height' => $field->height,
                'font_size' => $field->font_size,
                'align' => $field->align,
            ])
            ->values()
            ->all();

        $this->signerRoles = $this->version->signerRoles()
            ->get()
            ->map(static fn (DocumentTemplateSigner $role): array => [
                'id' => $role->id,
                'role_key' => $role->role_key,
                'label' => $role->label,
                'signature_page' => $role->signature_page,
                'signature_x' => $role->signature_x,
                'signature_y' => $role->signature_y,
            ])
            ->values()
            ->all();
    }

    /**
     * Anade un campo en la posicion indicada. La llama el editor del
     * navegador al soltar una caja nueva sobre una pagina.
     */
    public function addField(int $page = 1, float $x = 20.0, float $y = 20.0): void
    {
        $this->fields[] = [
            'id' => null,
            'key' => $this->nextAvailableKey(),
            'label' => 'Campo nuevo',
            'help_text' => null,
            'type' => TemplateFieldType::TEXT->value,
            'required' => true,
            'default_value' => null,
            'options' => [],
            'validation' => [],
            'page' => max(1, $page),
            'x' => round($x, 2),
            'y' => round($y, 2),
            'width' => 60.0,
            'height' => 8.0,
            'font_size' => 10,
            'align' => 'left',
        ];

        $this->selectedField = count($this->fields) - 1;
    }

    /**
     * Mueve o redimensiona un campo. La llama el editor al soltar el raton.
     */
    public function moveField(int $index, float $x, float $y, ?float $width = null, ?float $height = null, ?int $page = null): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        $this->fields[$index]['x'] = round(max(0, $x), 2);
        $this->fields[$index]['y'] = round(max(0, $y), 2);

        if ($width !== null) {
            $this->fields[$index]['width'] = round(max(5, $width), 2);
        }

        if ($height !== null) {
            $this->fields[$index]['height'] = round(max(4, $height), 2);
        }

        if ($page !== null) {
            $this->fields[$index]['page'] = max(1, $page);
        }
    }

    public function selectField(int $index): void
    {
        $this->selectedField = isset($this->fields[$index]) ? $index : null;
    }

    public function removeField(int $index): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
        $this->selectedField = null;
    }

    public function addSignerRole(): void
    {
        $this->signerRoles[] = [
            'id' => null,
            'role_key' => $this->nextAvailableRoleKey(),
            'label' => 'Firmante',
            'signature_page' => null,
            'signature_x' => null,
            'signature_y' => null,
        ];
    }

    public function removeSignerRole(int $index): void
    {
        if (! isset($this->signerRoles[$index])) {
            return;
        }

        unset($this->signerRoles[$index]);
        $this->signerRoles = array_values($this->signerRoles);
    }

    /**
     * Guarda el esquema completo, reemplazando el anterior.
     *
     * Se valida aqui y no solo en el navegador: el editor manda coordenadas,
     * y nada impide que mande otra cosa.
     */
    public function save(): void
    {
        $this->error = '';
        $this->success = '';

        try {
            $this->validateSchema();
        } catch (ValidationException $e) {
            $this->error = $e->validator->errors()->first();

            throw $e;
        }

        DB::transaction(function (): void {
            $this->version->fields()->delete();
            $this->version->signerRoles()->delete();

            foreach ($this->fields as $order => $field) {
                DocumentTemplateField::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $this->version->tenant_id,
                    'document_template_version_id' => $this->version->id,
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'help_text' => $field['help_text'] ?: null,
                    'type' => $field['type'],
                    'required' => (bool) $field['required'],
                    'default_value' => $field['default_value'] ?: null,
                    'options' => $field['options'] ?: null,
                    'validation' => $field['validation'] ?: null,
                    'page' => $field['page'],
                    'x' => $field['x'],
                    'y' => $field['y'],
                    'width' => $field['width'],
                    'height' => $field['height'],
                    'font_size' => $field['font_size'],
                    'align' => $field['align'],
                    'order' => $order,
                ]);
            }

            foreach ($this->signerRoles as $order => $role) {
                DocumentTemplateSigner::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $this->version->tenant_id,
                    'document_template_version_id' => $this->version->id,
                    'role_key' => $role['role_key'],
                    'label' => $role['label'],
                    'order' => $order,
                    'signature_page' => $role['signature_page'],
                    'signature_x' => $role['signature_x'],
                    'signature_y' => $role['signature_y'],
                ]);
            }
        });

        $this->version->refresh();
        $this->loadFromVersion();

        $this->success = 'Plantilla guardada.';
        $this->dispatch('template-saved');
    }

    /**
     * @throws ValidationException
     */
    private function validateSchema(): void
    {
        $rules = [
            'fields' => ['array'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', 'in:'.implode(',', array_keys(TemplateFieldType::options()))],
            'fields.*.page' => ['required', 'integer', 'min:1'],
            'fields.*.x' => ['required', 'numeric', 'min:0'],
            'fields.*.y' => ['required', 'numeric', 'min:0'],
            'fields.*.width' => ['required', 'numeric', 'min:5'],
            'fields.*.height' => ['required', 'numeric', 'min:4'],
            'fields.*.font_size' => ['required', 'integer', 'min:6', 'max:40'],
            'fields.*.align' => ['required', 'in:left,center,right'],
            'signerRoles' => ['array'],
            'signerRoles.*.role_key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'signerRoles.*.label' => ['required', 'string', 'max:255'],
        ];

        $messages = [
            'fields.*.key.regex' => 'La clave de un campo debe empezar por letra minuscula y contener solo letras, numeros y guion bajo.',
            'signerRoles.*.role_key.regex' => 'La clave de un rol debe empezar por letra minuscula y contener solo letras, numeros y guion bajo.',
        ];

        $this->validate($rules, $messages);

        $this->assertUnique(
            array_column($this->fields, 'key'),
            'fields',
            'Hay dos campos con la misma clave: :dup'
        );

        $this->assertUnique(
            array_column($this->signerRoles, 'role_key'),
            'signerRoles',
            'Hay dos roles de firmante con la misma clave: :dup'
        );

        $this->assertSelectsHaveOptions();
    }

    /**
     * @param  list<string>  $keys
     *
     * @throws ValidationException
     */
    private function assertUnique(array $keys, string $attribute, string $message): void
    {
        $duplicates = array_keys(array_filter(array_count_values($keys), static fn (int $n): bool => $n > 1));

        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                $attribute => [str_replace(':dup', implode(', ', $duplicates), $message)],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertSelectsHaveOptions(): void
    {
        foreach ($this->fields as $index => $field) {
            if ($field['type'] !== TemplateFieldType::SELECT->value) {
                continue;
            }

            if (empty($field['options'])) {
                throw ValidationException::withMessages([
                    "fields.{$index}.options" => ["El desplegable '{$field['label']}' necesita al menos una opcion."],
                ]);
            }
        }
    }

    private function nextAvailableKey(): string
    {
        $existing = array_column($this->fields, 'key');

        $n = count($existing) + 1;
        while (in_array("campo_{$n}", $existing, true)) {
            $n++;
        }

        return "campo_{$n}";
    }

    private function nextAvailableRoleKey(): string
    {
        $existing = array_column($this->signerRoles, 'role_key');

        $n = count($existing) + 1;
        while (in_array("firmante_{$n}", $existing, true)) {
            $n++;
        }

        return "firmante_{$n}";
    }

    /**
     * URL desde la que el navegador descarga el PDF base para pintarlo.
     */
    public function getPdfUrlProperty(): string
    {
        return route('templates.pdf', ['version' => $this->version->uuid]);
    }

    /**
     * @return array<string, string>
     */
    public function getFieldTypesProperty(): array
    {
        $options = [];
        foreach (TemplateFieldType::selectable() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    public function render(): View
    {
        return view('livewire.template.template-editor');
    }
}
