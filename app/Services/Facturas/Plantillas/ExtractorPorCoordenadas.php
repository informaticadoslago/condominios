<?php

namespace App\Services\Facturas\Plantillas;

/**
 * Ancla y reextrae campos de factura por su posición real en la página del PDF
 * (coordenadas de pdftotext -bbox-layout), para plantillas donde no hay ninguna
 * etiqueta de texto cerca del valor (las etiquetas están "quemadas" en una imagen
 * de fondo, ej. facturas generadas con un formulario preimpreso escaneado). Es el
 * mismo problema que resuelve ExtractorPosicional pero cuando ese no tiene nada a
 * lo que anclarse: aquí el ancla es "esta misma casilla de la página", no un texto.
 */
class ExtractorPorCoordenadas
{
    protected const TOLERANCIA_Y = 3.0;
    protected const TOLERANCIA_X = 5.0;

    /**
     * Al marcar: dado el rango seleccionado (offsets de carácter en el texto de -layout) y
     * los bloques con posición del mismo PDF (de LectorPdf::aBloquesConPosicion), localiza a
     * qué bloque de la página corresponde. Como el valor puede repetirse en la página (ej. un
     * mismo importe en varias columnas), cuenta qué OCURRENCIA del valor es la seleccionada en
     * el texto y coge esa misma ocurrencia en los bloques ordenados en orden de lectura
     * (página, luego arriba-abajo, luego izquierda-derecha) — el mismo orden en que
     * pdftotext -layout reconstruye el texto, así que ambas cuentas coinciden.
     *
     * Autovalida el resultado con un roundtrip por buscarPorPosicion(): si no se reencuentra a
     * sí mismo (tolerancia solapando dos bloques distintos), se descarta en vez de guardar algo
     * que fallaría en la siguiente factura — mismo criterio que ExtractorPosicional.
     */
    public function construirAncla(string $texto, int $inicio, int $fin, array $bloques): ?array
    {
        $valor = trim(mb_substr($texto, $inicio, $fin - $inicio));
        if ($valor === '') {
            return null;
        }

        $ocurrencia = $this->ocurrenciaEnTexto($texto, $valor, $inicio);
        if ($ocurrencia === 0) {
            return null;
        }

        $bloque = $this->enesimaOcurrencia($this->enOrdenDeLectura($bloques), $valor, $ocurrencia);
        if ($bloque === null) {
            return null;
        }

        if ($this->buscarPorPosicion($bloques, $bloque['x'], $bloque['y'], $bloque['ancho'], $bloque['pagina']) !== $valor) {
            return null;
        }

        return [
            'valor'  => $valor,
            'pagina' => $bloque['pagina'],
            'pos_x'  => $bloque['x'],
            'pos_y'  => $bloque['y'],
            'pos_ancho' => $bloque['ancho'],
        ];
    }

    /** En facturas siguientes: coge el bloque cuya posición coincide (con tolerancia) con la guardada. */
    public function buscarPorPosicion(array $bloques, float $x, float $y, float $ancho, int $pagina): ?string
    {
        $mejorTexto      = null;
        $mejorDistancia  = null;

        foreach ($bloques as $bloque) {
            if ($bloque['pagina'] !== $pagina) {
                continue;
            }

            $distanciaY = abs($bloque['y'] - $y);
            $distanciaX = abs($bloque['x'] - $x);
            if ($distanciaY > self::TOLERANCIA_Y || $distanciaX > self::TOLERANCIA_X) {
                continue;
            }

            $distancia = $distanciaY + $distanciaX;
            if ($mejorDistancia === null || $distancia < $mejorDistancia) {
                $mejorDistancia = $distancia;
                $mejorTexto     = $bloque['texto'];
            }
        }

        return $mejorTexto;
    }

    /** Nº de veces que $valor aparece en $texto hasta (e incluyendo) la ocurrencia que empieza en $inicio. */
    protected function ocurrenciaEnTexto(string $texto, string $valor, int $inicio): int
    {
        $ocurrencia = 0;
        $posicion   = 0;

        while (($posicion = mb_strpos($texto, $valor, $posicion)) !== false && $posicion <= $inicio) {
            $ocurrencia++;
            $posicion += mb_strlen($valor);
        }

        return $ocurrencia;
    }

    protected function enesimaOcurrencia(array $bloquesOrdenados, string $valor, int $ocurrencia): ?array
    {
        $vistos = 0;
        foreach ($bloquesOrdenados as $bloque) {
            if ($bloque['texto'] !== $valor) {
                continue;
            }

            $vistos++;
            if ($vistos === $ocurrencia) {
                return $bloque;
            }
        }

        return null;
    }

    /** Página, luego arriba-abajo, luego izquierda-derecha: el mismo orden en que -layout reconstruye el texto. */
    protected function enOrdenDeLectura(array $bloques): array
    {
        usort($bloques, fn ($a, $b) => [$a['pagina'], $a['y'], $a['x']] <=> [$b['pagina'], $b['y'], $b['x']]);

        return $bloques;
    }
}
