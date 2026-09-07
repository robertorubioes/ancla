<?php

namespace App\Services\Template;

use App\Enums\TemplateFieldType;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * El esquema de campos de una version de plantilla.
 *
 * Es la UNICA fuente de validacion: tanto el formulario web como el endpoint
 * de la API derivan sus reglas de aqui. Un valor que rechaza uno lo rechaza
 * el otro, con el mismo mensaje. Sin esto acabariamos con dos definiciones de
 * campo que divergen.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
class TemplateSchema
{
    /**
     * @param  Collection<int, DocumentTemplateField>  $fields
     */
    private function __construct(
        private readonly Collection $fields,
    ) {}

    public static function for(DocumentTemplateVersion $version): self
    {
        return new self($version->fields()->get());
    }

    /**
     * @param  iterable<DocumentTemplateField>  $fields
     */
    public static function fromFields(iterable $fields): self
    {
        return new self(collect($fields));
    }

    /**
     * Campos que rellena una persona o un sistema externo.
     *
     * @return Collection<int, DocumentTemplateField>
     */
    public function inputFields(): Collection
    {
        return $this->fields
            ->reject(static fn (DocumentTemplateField $field): bool => $field->type->isComputed())
            ->values();
    }

    /**
     * Campos que rellena la plataforma.
     *
     * @return Collection<int, DocumentTemplateField>
     */
    public function computedFields(): Collection
    {
        return $this->fields
            ->filter(static fn (DocumentTemplateField $field): bool => $field->type->isComputed())
            ->values();
    }

    /**
     * @return Collection<int, DocumentTemplateField>
     */
    public function allFields(): Collection
    {
        return $this->fields;
    }

    /**
     * Reglas de validacion para los valores rellenables.
     *
     * @param  string  $prefix  Prefijo de las claves. La web usa "values" para
     *                          casar con su formulario; la API, cadena vacia.
     * @return array<string, list<string>>
     */
    public function rules(string $prefix = ''): array
    {
        $rules = [];

        foreach ($this->inputFields() as $field) {
            $key = $prefix === '' ? $field->key : "{$prefix}.{$field->key}";

            $fieldRules = [$field->required ? 'required' : 'nullable'];
            $fieldRules = array_merge($fieldRules, $field->type->baseRules());

            if ($field->type === TemplateFieldType::SELECT) {
                $values = array_keys($field->optionMap());
                $fieldRules[] = 'in:'.implode(',', $values);
            }

            foreach ($this->extraRules($field) as $extra) {
                $fieldRules[] = $extra;
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Nombres legibles, para que los mensajes no digan "values.dni".
     *
     * @return array<string, string>
     */
    public function attributes(string $prefix = ''): array
    {
        $attributes = [];

        foreach ($this->inputFields() as $field) {
            $key = $prefix === '' ? $field->key : "{$prefix}.{$field->key}";
            $attributes[$key] = $field->label;
        }

        return $attributes;
    }

    /**
     * Valida un conjunto de valores contra el esquema y devuelve solo los
     * campos conocidos, descartando lo que sobre.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $values): array
    {
        $validator = Validator::make(
            $values,
            $this->rules(),
            [],
            $this->attributes(),
        );

        $validator->validate();

        $known = $this->inputFields()
            ->pluck('key')
            ->all();

        return array_intersect_key($values, array_flip($known));
    }

    /**
     * Valores por defecto declarados por la plantilla.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->inputFields() as $field) {
            $defaults[$field->key] = match ($field->type) {
                TemplateFieldType::CHECKBOX => (bool) $field->default_value,
                default => $field->default_value,
            };
        }

        return $defaults;
    }

    /**
     * Descripcion del esquema para la documentacion de la API y el editor.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->inputFields()
            ->map(static fn (DocumentTemplateField $field): array => array_filter([
                'key' => $field->key,
                'label' => $field->label,
                'help' => $field->help_text,
                'type' => $field->type->value,
                'required' => $field->required,
                'default' => $field->default_value,
                'options' => $field->optionMap() ?: null,
                'validation' => $field->validation ?: null,
            ], static fn (mixed $value): bool => $value !== null))
            ->all();
    }

    /**
     * Traduce las reglas extra declaradas por el campo.
     *
     * Se acepta una lista blanca corta a proposito: `validation` es un JSON
     * que edita quien crea la plantilla, y no debe poder inyectar reglas
     * arbitrarias de Laravel.
     *
     * @return list<string>
     */
    private function extraRules(DocumentTemplateField $field): array
    {
        $rules = [];
        $validation = $field->validation ?? [];

        foreach (['min', 'max'] as $rule) {
            if (isset($validation[$rule]) && is_numeric($validation[$rule])) {
                $rules[] = "{$rule}:{$validation[$rule]}";
            }
        }

        if (isset($validation['regex']) && is_string($validation['regex'])) {
            $rules[] = 'regex:'.$validation['regex'];
        }

        return $rules;
    }
}
