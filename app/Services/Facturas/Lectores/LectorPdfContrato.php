<?php

namespace App\Services\Facturas\Lectores;

interface LectorPdfContrato
{
    public function aTexto(string $rutaAbsoluta): string;
}
