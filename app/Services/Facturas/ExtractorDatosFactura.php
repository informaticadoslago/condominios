<?php

namespace App\Services\Facturas;

/** Busca en el texto de una factura los datos que luego forman la plantilla: razón social, CIF, fecha e importes. */
class ExtractorDatosFactura
{
    /** $cifPropio: CIF de la comunidad actual, para no confundirlo con el del proveedor (es el
     *  cliente en la factura, y en formatos como facturas de suministros suele aparecer más
     *  destacado que el del proveedor real). */
    public function extraer(string $texto, ?string $cifPropio = null): array
    {
        return [
            'razon_social' => $this->buscarRazonSocial($texto),
            'cif'          => $this->buscarCif($texto, $cifPropio),
            'fecha'        => $this->buscarFecha($texto),
            'importes'     => $this->buscarImportes($texto),
        ];
    }

    protected function buscarRazonSocial(string $texto): ?string
    {
        // Formas societarias españolas más habituales: S.A., S.L., S.A.U., S.L.U., S.L.N.E., S.COOP., C.B., S.C.
        // El punto tras la primera letra es obligatorio: sin él, "SA"/"SL" sueltas (ej. "SA
        // de REE") cuelan como si fueran una forma societaria cuando no lo son. ESPJ (Entidad
        // Sin Personalidad Jurídica, el género de C.B./S.C.) no tiene ese riesgo de colisión
        // con palabras sueltas, así que no hace falta exigirle puntos.
        $formas = '(?:S\.\s*A\.?\s*U?\.?|S\.\s*L\.?\s*(?:U\.?|N\.?\s*E\.?)?|S\.\s*COOP\.?|C\.\s*B\.?|S\.\s*C\.?|E\.?\s*S\.?\s*P\.?\s*J\.?)';

        if (preg_match('/([A-ZÁÉÍÓÚÑa-záéíóúñ0-9][A-ZÁÉÍÓÚÑa-záéíóúñ0-9&.,\'\- ]{1,80}?,?\s+' . $formas . ')\b/u', $texto, $m)) {
            $razonSocial = trim(preg_replace('/\s+/', ' ', $m[1]));

            // Pie de página legal típico ("...N.I.F. A-12345678 Empresa, S.L."): la ventana
            // de 80 caracteres puede arrastrar toda la frase de antes; si dentro del propio
            // trozo hay un CIF, el nombre real empieza justo después de él.
            $razonSocial = preg_replace('/^.*\b[NC]\.?\s*I\.?\s*F\.?\.?\s*:?\s*[A-Z]?[\-.]?\d[\d.\-]*\s*/ui', '', $razonSocial);

            return trim($razonSocial);
        }

        return null;
    }

    protected function buscarCif(string $texto, ?string $cifPropio = null): ?string
    {
        $cifPropio = $cifPropio ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cifPropio)) : null;

        // Letra + 7 dígitos + dígito/letra de control, con separador opcional (guion, punto o
        // espacio). Puede haber varios en el texto (el del cliente y el del proveedor): nos
        // quedamos con el primero que NO sea el de nuestra propia comunidad.
        if (preg_match_all('/\b([A-HJNPQRSUVW])[.\-\s]?(\d{7})[.\-\s]?([0-9A-J])\b/i', $texto, $m, PREG_SET_ORDER)) {
            foreach ($m as $coincidencia) {
                $candidato = strtoupper($coincidencia[1] . $coincidencia[2] . $coincidencia[3]);
                if ($candidato !== $cifPropio) {
                    return $candidato;
                }
            }
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
        // Formato español (1.234,56) y, aunque menos habitual en factura española, el que
        // usa el punto como decimal (20.00, 1,234.56) — algún software de facturación lo usa
        // igualmente aquí (ej. Piensa Solutions/Tesys).
        preg_match_all('/\d{1,3}(?:\.\d{3})*,\d{2}\s*€?|\d{1,3}(?:,\d{3})*\.\d{2}\s*€?/', $texto, $matches);

        return array_values(array_unique($matches[0]));
    }
}
