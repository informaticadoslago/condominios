<?php

namespace App\Services\Facturas;

/** Busca en el texto de una factura los datos que luego forman la plantilla: razón social, CIF, fecha e importes. */
class ExtractorDatosFactura
{
    public function extraer(string $texto): array
    {
        return [
            'razon_social' => $this->buscarRazonSocial($texto),
            'cif'          => $this->buscarCif($texto),
            'fecha'        => $this->buscarFecha($texto),
            'importes'     => $this->buscarImportes($texto),
        ];
    }

    protected function buscarRazonSocial(string $texto): ?string
    {
        // Formas societarias españolas más habituales: S.A., S.L., S.A.U., S.L.U., S.L.N.E., S.COOP., C.B., S.C.
        $formas = '(?:S\.?\s*A\.?\s*U?\.?|S\.?\s*L\.?\s*(?:U\.?|N\.?\s*E\.?)?|S\.?\s*COOP\.?|C\.?\s*B\.?|S\.?\s*C\.?)';

        if (preg_match('/([A-ZÁÉÍÓÚÑa-záéíóúñ0-9][A-ZÁÉÍÓÚÑa-záéíóúñ0-9&.,\'\- ]{1,80}?,?\s+' . $formas . ')\b/u', $texto, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        return null;
    }

    protected function buscarCif(string $texto): ?string
    {
        // Letra + 7 dígitos + dígito/letra de control, con separador opcional (guion, punto o espacio).
        if (preg_match('/\b([A-HJNPQRSUVW])[.\-\s]?(\d{7})[.\-\s]?([0-9A-J])\b/i', $texto, $m)) {
            return strtoupper($m[1] . $m[2] . $m[3]);
        }

        return null;
    }

    protected function buscarFecha(string $texto): ?string
    {
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/', $texto, $m)) {
            return $m[0];
        }

        return null;
    }

    protected function buscarImportes(string $texto): array
    {
        preg_match_all('/\d{1,3}(?:\.\d{3})*,\d{2}\s*€?/', $texto, $matches);

        return array_values(array_unique($matches[0]));
    }
}
