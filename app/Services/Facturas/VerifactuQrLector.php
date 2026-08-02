<?php

namespace App\Services\Facturas;

use Symfony\Component\Process\Process;
use Zxing\QrReader;

/**
 * Busca en las imágenes embebidas de un PDF un QR de VeriFactu (AEAT) y, si lo
 * encuentra, devuelve directamente NIF/nº factura/fecha/importe del emisor —
 * datos de la propia Agencia Tributaria, sin ambigüedad de "cliente vs proveedor"
 * ni depender de ninguna plantilla.
 */
class VerifactuQrLector
{
    /** Un QR real nunca llega a este tamaño; una foto o un escaneo de página completa sí
     *  (y decodificarlo con ImageMagick puede agotar la memoria del proceso PHP). */
    protected const MAX_PIXELES_QR = 2_000_000;

    public function buscar(string $rutaPdf): ?array
    {
        $carpeta = sys_get_temp_dir() . '/verifactu_' . uniqid();
        mkdir($carpeta);

        try {
            $process = new Process(['pdfimages', '-png', $rutaPdf, $carpeta . '/img']);
            $process->run();

            foreach (glob($carpeta . '/img-*.png') as $imagen) {
                if ($this->esDemasiadoGrande($imagen)) {
                    continue;
                }

                $datos = $this->intentarDecodificar($imagen);
                if ($datos) {
                    return $datos;
                }
            }

            return null;
        } finally {
            array_map('unlink', glob($carpeta . '/*'));
            @rmdir($carpeta);
        }
    }

    protected function esDemasiadoGrande(string $rutaImagen): bool
    {
        $info = @getimagesize($rutaImagen);
        if (! $info) {
            return false; // si ni siquiera se puede leer la cabecera, que lo intente y falle solo con esta imagen
        }

        [$ancho, $alto] = $info;

        return ($ancho * $alto) > self::MAX_PIXELES_QR;
    }

    protected function intentarDecodificar(string $rutaImagen): ?array
    {
        try {
            $texto = (new QrReader($rutaImagen))->text();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $texto || ! str_contains($texto, 'ValidarQR')) {
            return null;
        }

        parse_str((string) parse_url($texto, PHP_URL_QUERY), $parametros);

        if (empty($parametros['nif'])) {
            return null;
        }

        return [
            'cif'            => strtoupper($parametros['nif']),
            'numero_factura' => $parametros['numserie'] ?? null,
            'fecha'          => isset($parametros['fecha']) ? str_replace('-', '/', $parametros['fecha']) : null,
            'importe'        => isset($parametros['importe']) ? str_replace('.', ',', $parametros['importe']) . ' €' : null,
        ];
    }
}
