<?php

namespace App\Services\Document;

use setasign\Fpdi\Fpdi;

/**
 * FPDI con las primitivas de dibujo que la pagina de certificacion necesita.
 *
 * FPDF solo sabe trazar lineas y rectangulos: no tiene Circle(). La pagina de
 * certificacion la llamaba igualmente, asi que la generacion del documento
 * final reventaba con "Call to undefined method". El circulo es la adaptacion
 * del script Ellipse de la propia FPDF: cuatro curvas de Bezier.
 */
class CertificationPdf extends Fpdi
{
    /**
     * Circulo en milimetros.
     *
     * @param  string  $style  'D' contorno, 'F' relleno, 'FD' ambos
     */
    public function Circle(float $x, float $y, float $r, string $style = 'D'): void
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    /**
     * Elipse en milimetros, centrada en ($x, $y).
     */
    public function Ellipse(float $x, float $y, float $rx, float $ry, string $style = 'D'): void
    {
        $op = match ($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
        };

        // 4/3*(raiz(2)-1) es la longitud de tirador que hace que cuatro curvas
        // de Bezier aproximen la circunferencia con error despreciable.
        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;

        $k = $this->k;
        $h = $this->h;

        $this->_out(sprintf(
            '%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $k, ($h - $y) * $k,
            ($x + $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x + $lx) * $k, ($h - ($y - $ry)) * $k,
            $x * $k, ($h - ($y - $ry)) * $k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $lx) * $k, ($h - ($y - $ry)) * $k,
            ($x - $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x - $rx) * $k, ($h - $y) * $k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x - $lx) * $k, ($h - ($y + $ry)) * $k,
            $x * $k, ($h - ($y + $ry)) * $k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x + $lx) * $k, ($h - ($y + $ry)) * $k,
            ($x + $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x + $rx) * $k, ($h - $y) * $k,
            $op
        ));
    }
}
