<?php

namespace App\Services\Template;

use RuntimeException;

/**
 * Fallo al generar un documento a partir de una plantilla.
 */
class TemplateRenderException extends RuntimeException
{
    public static function pdfReadFailed(string $reason): self
    {
        return new self("No se pudo leer el PDF base de la plantilla: {$reason}");
    }

    public static function pageOutOfRange(string $fieldKey, int $page, int $pageCount): self
    {
        return new self(
            "El campo '{$fieldKey}' apunta a la pagina {$page}, y el documento tiene {$pageCount}."
        );
    }

    /**
     * El texto no cabe ni reduciendo el cuerpo de letra.
     *
     * Se aborta a proposito: un contrato con el importe cortado a la mitad es
     * peor que un error.
     */
    public static function valueDoesNotFit(string $fieldKey, string $label): self
    {
        return new self(
            "El valor de '{$label}' no cabe en el espacio reservado del documento. ".
            "Acortalo o amplia la caja del campo '{$fieldKey}' en la plantilla."
        );
    }

    public static function missingSignerRole(string $roleKey): self
    {
        return new self("La plantilla espera un firmante con el rol '{$roleKey}' y no se ha indicado.");
    }
}
