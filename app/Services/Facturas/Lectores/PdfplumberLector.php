<?php

namespace App\Services\Facturas\Lectores;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/** Convierte un PDF a texto con pdfplumber (Python), vía el venv de storage/app/pyenv. */
class PdfplumberLector implements LectorPdfContrato
{
    public function aTexto(string $rutaAbsoluta): string
    {
        $process = new Process([
            base_path('storage/app/pyenv/bin/python3'),
            base_path('python/pdfplumber_a_texto.py'),
            $rutaAbsoluta,
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
