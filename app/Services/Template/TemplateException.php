<?php

namespace App\Services\Template;

use RuntimeException;

/**
 * Fallo en el ciclo de vida de una plantilla.
 */
class TemplateException extends RuntimeException
{
    public static function documentNotReady(): self
    {
        return new self(
            'El documento todavia no esta listo. Espera a que termine de procesarse antes de convertirlo en plantilla.'
        );
    }

    public static function documentFromAnotherTenant(): self
    {
        return new self('Ese documento no pertenece a tu organizacion.');
    }

    public static function noDraftToPublish(): self
    {
        return new self('No hay ninguna version en borrador que habilitar.');
    }

    public static function needsSignerRoles(): self
    {
        return new self(
            'La plantilla necesita al menos un firmante previsto: sin el no puede generar un proceso de firma.'
        );
    }

    public static function needsFields(): self
    {
        return new self(
            'La plantilla no tiene ningun campo. Sin campos variables no aporta nada sobre subir el PDF directamente.'
        );
    }

    public static function nothingToCopy(): self
    {
        return new self('La plantilla no tiene ninguna version publicada de la que partir.');
    }

    public static function notUsable(): self
    {
        return new self('Esta plantilla no esta habilitada todavia.');
    }

    public static function missingSigner(string $label): self
    {
        return new self("Falta asignar el firmante '{$label}'.");
    }
}
