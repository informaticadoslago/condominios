<?php

namespace App\Services\Facturas\Plantillas;

/**
 * Ancla y reextrae campos de factura por posición en el texto (pdftotext -layout).
 *
 * Cubre los dos formatos reales encontrados:
 *  - "etiqueta: valor" en la misma línea (ej. "Fecha: 31/01/2025").
 *  - tabla con la etiqueta en una línea de cabecera y el valor alineado por columna
 *    una o varias líneas más abajo (ej. "TOTAL FACTURA" / "115,68€"), típico de
 *    pdftotext -layout al reconstruir tablas con columnas.
 */
class ExtractorPosicional
{
    protected const TOLERANCIA_COLUMNA = 20;
    protected const SALTO_LINEAS_MAXIMO = 4;

    /** Al marcar: dado el rango seleccionado (offsets de carácter), calcula ancla y valor. */
    public function construirAncla(string $texto, int $inicio, int $fin): array
    {
        $valor = trim(mb_substr($texto, $inicio, $fin - $inicio));

        [$inicioLinea] = $this->limitesLinea($texto, $inicio);
        $prefijoLinea   = mb_substr($texto, $inicioLinea, $inicio - $inicioLinea);
        $ultimaColumna  = $this->ultimaColumna($prefijoLinea);

        // El trozo de texto justo antes, en la misma línea, solo sirve de ancla si "parece"
        // una etiqueta (tiene letras): si es puramente numérico es el valor de otra columna
        // de la misma fila (ej. "20,08" antes de "115,68"), no una etiqueta real.
        if ($ultimaColumna !== null && preg_match('/\p{L}/u', $ultimaColumna)) {
            return ['ancla' => $ultimaColumna, 'valor' => $valor];
        }

        $columnaInicio = $inicio - $inicioLinea;
        $columnaFin    = $fin - $inicioLinea;
        $ancla         = $this->buscarEnLineasCercanas($texto, $inicioLinea, $columnaInicio, $columnaFin, hacia: -1);

        return ['ancla' => $ancla ?? $ultimaColumna ?? $valor, 'valor' => $valor];
    }

    /** En facturas siguientes: busca el ancla guardada y devuelve el valor que le corresponde ahora. */
    public function buscarPorAncla(string $texto, string $ancla): ?string
    {
        $posicion = mb_strpos($texto, $ancla);
        if ($posicion === false) {
            return null;
        }

        $finAncla = $posicion + mb_strlen($ancla);
        [$inicioLinea, $finLinea] = $this->limitesLinea($texto, $posicion);

        $restoLinea = trim(mb_substr($texto, $finAncla, $finLinea - $finAncla));
        if ($restoLinea !== '') {
            $candidato = $this->primerToken($restoLinea);
            // Solo vale como valor si tiene pinta de serlo (algún dígito): si no, es la
            // siguiente etiqueta de la misma fila de cabeceras (ej. "FECHA" tras "NUMERO FACTURA").
            if (preg_match('/\d/', $candidato)) {
                return $this->limpiarValor($candidato);
            }
        }

        $columna = $finAncla - $inicioLinea;
        $valor   = $this->buscarEnLineasCercanas($texto, $inicioLinea, $columna, $columna, hacia: 1);

        return $valor === null ? null : $this->limpiarValor($valor);
    }

    /** Límites [inicio, fin) de la línea que contiene $offset, en offsets de carácter. */
    protected function limitesLinea(string $texto, int $offset): array
    {
        $anterior = mb_substr($texto, 0, $offset);
        $inicio   = mb_strrpos($anterior, "\n");
        $inicio   = $inicio === false ? 0 : $inicio + 1;

        $fin = mb_strpos($texto, "\n", $offset);
        $fin = $fin === false ? mb_strlen($texto) : $fin;

        return [$inicio, $fin];
    }

    /** Recorre hasta N líneas hacia arriba (-1) o abajo (+1), saltando las vacías, buscando la mejor columna. */
    protected function buscarEnLineasCercanas(string $texto, int $inicioLineaActual, int $columnaInicio, int $columnaFin, int $hacia): ?string
    {
        $inicioLinea = $inicioLineaActual;

        for ($salto = 0; $salto < self::SALTO_LINEAS_MAXIMO; $salto++) {
            if ($hacia < 0) {
                if ($inicioLinea === 0) {
                    return null;
                }
                [$inicioLinea, $finLinea] = $this->limitesLinea($texto, $inicioLinea - 1);
            } else {
                [, $finLineaActual] = $this->limitesLinea($texto, $inicioLinea);
                if ($finLineaActual >= mb_strlen($texto)) {
                    return null;
                }
                [$inicioLinea, $finLinea] = $this->limitesLinea($texto, $finLineaActual + 1);
            }

            $linea = mb_substr($texto, $inicioLinea, $finLinea - $inicioLinea);
            if (trim($linea) === '') {
                continue;
            }

            // pdftotext -layout puede colar en esta misma línea texto de OTRA columna visual de
            // la página (ej. un recuadro publicitario a la derecha) que comparte altura pero no
            // tiene nada que ver: si no hay columna cercana aquí, seguimos mirando más lejos en
            // vez de rendirnos, hasta agotar el margen de líneas.
            $mejor = $this->mejorColumna($linea, $columnaInicio, $columnaFin);
            if ($mejor !== null) {
                return $mejor;
            }
        }

        return null;
    }

    protected function mejorColumna(string $linea, int $columnaInicio, int $columnaFin): ?string
    {
        $mejorTexto     = null;
        $mejorDistancia = self::TOLERANCIA_COLUMNA + 1;

        foreach ($this->columnas($linea) as $columna) {
            $distancia = $this->distanciaSpans($columnaInicio, $columnaFin, $columna['inicio'], $columna['fin']);
            if ($distancia < $mejorDistancia) {
                $mejorDistancia = $distancia;
                $mejorTexto     = $columna['texto'];
            }
        }

        return $mejorTexto;
    }

    /** Último trozo no vacío (separado por 2+ espacios) de un fragmento de línea, o null si no hay ninguno. */
    protected function ultimaColumna(string $fragmento): ?string
    {
        $columnas = $this->columnas($fragmento);

        return $columnas === [] ? null : end($columnas)['texto'];
    }

    /** Trocea una línea en "columnas" separadas por 2+ espacios, con su posición real (carácter) en la línea. */
    protected function columnas(string $linea): array
    {
        $piezas = preg_split('/\s{2,}/u', $linea, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $columnas = [];
        foreach ($piezas as [$textoBruto, $byteOffset]) {
            $margenIzquierdo = mb_strlen($textoBruto) - mb_strlen(ltrim($textoBruto));
            $texto           = trim($textoBruto);
            if ($texto === '') {
                continue;
            }

            $inicio     = mb_strlen(substr($linea, 0, $byteOffset)) + $margenIzquierdo;
            $columnas[] = ['texto' => $texto, 'inicio' => $inicio, 'fin' => $inicio + mb_strlen($texto)];
        }

        return $columnas;
    }

    /** 0 si los rangos se solapan o son adyacentes; si no, el hueco entre ellos. */
    protected function distanciaSpans(int $s1, int $e1, int $s2, int $e2): int
    {
        if ($s1 <= $e2 && $s2 <= $e1) {
            return 0;
        }

        return $s1 > $e2 ? $s1 - $e2 : $s2 - $e1;
    }

    /** Quita puntuación suelta al principio (restos de un salto de línea a media etiqueta, ej. ": B-36...") */
    protected function limpiarValor(string $valor): string
    {
        return preg_replace('/^[^\p{L}\p{N}]+/u', '', $valor);
    }

    protected function primerToken(string $texto): string
    {
        $partes = preg_split('/\s{2,}/u', trim($texto));

        return trim($partes[0]);
    }
}
