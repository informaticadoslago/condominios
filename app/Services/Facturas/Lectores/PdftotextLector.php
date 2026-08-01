<?php

namespace App\Services\Facturas\Lectores;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/** Convierte un PDF a texto con pdftotext (poppler-utils), binario del sistema. */
class PdftotextLector implements LectorPdfContrato
{
    public function aTexto(string $rutaAbsoluta): string
    {
        $process = new Process(['pdftotext', '-layout', $rutaAbsoluta, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
