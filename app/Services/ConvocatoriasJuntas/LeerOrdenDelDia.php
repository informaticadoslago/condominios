<?php

namespace App\Services\ConvocatoriasJuntas;

/**
 * Saca los puntos de un orden del día pegado como texto (normalmente copiado de un PDF
 * o de un Word, con los puntos partidos en varias líneas). Devuelve solo los textos, sin
 * su número: la numeración la pone el formulario según el orden.
 *
 * Empieza un punto nuevo:
 *  - una línea con número y signo: "1.", "2)", "3º", "4:", "5-", "6.-" (un número de hasta 3
 *    cifras: un año suelto al principio de línea es continuación, no un punto);
 *  - una línea con viñeta: "-", "–", "•", "*";
 *  - una línea con número sin signo ("6 Ruegos y preguntas") solo si el punto anterior ya
 *    acababa en . : ; ? !, porque si no suele ser un salto de línea a mitad de frase
 *    ("puesta al cobro el\n3 de julio");
 *  - cualquier línea detrás de una línea en blanco.
 * El resto de líneas continúan el punto anterior. Si en todo el texto no hay ningún
 * número ni viñeta, cada línea es un punto.
 */
class LeerOrdenDelDia
{
    private const CON_SIGNO = '/^\d{1,3}\s*(?:[.)ºª:]-?|-)(?!\d)\s*(.+)$/u';

    private const SIN_SIGNO = '/^\d{1,3}\s+(\D.*)$/u';

    private const VINETA = '/^[-–•*]\s+(.+)$/u';

    /** @return array<int, string> */
    public function ejecutar(string $texto): array
    {
        $lineas = array_map('trim', preg_split('/\r\n|\r|\n/', $texto));

        if (! $this->tieneMarcas($lineas)) {
            return array_values(array_map([$this, 'limpiar'], array_filter($lineas, fn ($linea) => $linea !== '')));
        }

        $puntos = [];
        $actual = null;

        foreach ($lineas as $linea) {
            if ($linea === '') {
                $actual = $this->cerrar($puntos, $actual);

                continue;
            }

            if (preg_match(self::CON_SIGNO, $linea, $m) || preg_match(self::VINETA, $linea, $m)) {
                $actual = $this->cerrar($puntos, $actual);
                $actual = $m[1];
            } elseif (preg_match(self::SIN_SIGNO, $linea, $m) && ($actual === null || preg_match('/[.:;?!]$/u', $actual))) {
                $actual = $this->cerrar($puntos, $actual);
                $actual = $m[1];
            } else {
                $actual = $actual === null ? $linea : $actual.' '.$linea;
            }
        }

        $this->cerrar($puntos, $actual);

        return $puntos;
    }

    /** @param array<int, string> $lineas */
    private function tieneMarcas(array $lineas): bool
    {
        foreach ($lineas as $linea) {
            if (preg_match(self::CON_SIGNO, $linea) || preg_match(self::SIN_SIGNO, $linea) || preg_match(self::VINETA, $linea)) {
                return true;
            }
        }

        return false;
    }

    /** Guarda el punto en curso (si lo hay) y deja la lectura sin punto abierto. */
    private function cerrar(array &$puntos, ?string $actual): null
    {
        if ($actual !== null && ($texto = $this->limpiar($actual)) !== '') {
            $puntos[] = $texto;
        }

        return null;
    }

    private function limpiar(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto));
    }
}
