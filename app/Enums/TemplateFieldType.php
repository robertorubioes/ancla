<?php

namespace App\Enums;

/**
 * Tipo de campo de una plantilla.
 *
 * Los tipos calculados (isComputed) no aparecen en el formulario ni se
 * aceptan por API: los rellena el sistema. Evitan pedir al usuario que
 * teclee datos que la plataforma ya conoce.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
enum TemplateFieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case DATE = 'date';
    case SELECT = 'select';
    case CHECKBOX = 'checkbox';
    case SIGNER_NAME = 'signer_name';
    case SIGNER_EMAIL = 'signer_email';
    case TODAY = 'today';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Texto',
            self::TEXTAREA => 'Texto largo',
            self::NUMBER => 'Numero',
            self::DATE => 'Fecha',
            self::SELECT => 'Desplegable',
            self::CHECKBOX => 'Casilla',
            self::SIGNER_NAME => 'Nombre del firmante',
            self::SIGNER_EMAIL => 'Correo del firmante',
            self::TODAY => 'Fecha de generacion',
        };
    }

    /**
     * Los rellena el sistema, no la persona ni la API.
     */
    public function isComputed(): bool
    {
        return match ($this) {
            self::SIGNER_NAME, self::SIGNER_EMAIL, self::TODAY => true,
            default => false,
        };
    }

    /**
     * Reglas de validacion propias del tipo, sin contar required ni las
     * reglas extra que declare el campo.
     *
     * @return list<string>
     */
    public function baseRules(): array
    {
        return match ($this) {
            self::TEXT => ['string', 'max:255'],
            self::TEXTAREA => ['string', 'max:5000'],
            self::NUMBER => ['numeric'],
            self::DATE => ['date'],
            self::SELECT => ['string'],
            self::CHECKBOX => ['boolean'],
            self::SIGNER_NAME, self::SIGNER_EMAIL, self::TODAY => [],
        };
    }

    /**
     * Tipos que puede elegir quien edita una plantilla.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => ! $type->isComputed(),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
