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
        [$inicio, $fin] = $this->ajustarLimitesNumero($texto, $inicio, $fin);

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

    /**
     * Al marcar: dadas las posiciones de la ETIQUETA y el VALOR (ambas elegidas a mano por
     * el usuario, no adivinadas), calcula el desplazamiento relativo entre ambas — cuántas
     * líneas y cuántos caracteres de columna hay de una a otra. Pensado para los campos que
     * cambian de una factura a otra (fecha, nº factura, importe), donde adivinar la etiqueta
     * por proximidad falla si ese texto se repite en el documento.
     */
    public function construirAnclaEtiquetaValor(string $texto, int $inicioEtiqueta, int $finEtiqueta, int $inicioValor, int $finValor): array
    {
        $etiqueta = trim(mb_substr($texto, $inicioEtiqueta, $finEtiqueta - $inicioEtiqueta));

        [$inicioValor, $finValor] = $this->ajustarLimitesNumero($texto, $inicioValor, $finValor);
        $valor = trim(mb_substr($texto, $inicioValor, $finValor - $inicioValor));

        [$inicioLineaEtiqueta] = $this->limitesLinea($texto, $inicioEtiqueta);
        [$inicioLineaValor]    = $this->limitesLinea($texto, $inicioValor);

        return [
            'ancla'          => $etiqueta,
            'valor'          => $valor,
            'delta_columna'  => ($inicioValor - $inicioLineaValor) - ($inicioEtiqueta - $inicioLineaEtiqueta),
            'delta_lineas'   => substr_count(mb_substr($texto, 0, $inicioLineaValor), "\n")
                               - substr_count(mb_substr($texto, 0, $inicioLineaEtiqueta), "\n"),
            'longitud_valor' => mb_strlen($valor),
        ];
    }

    /**
     * En facturas siguientes: busca la etiqueta (elegida a mano, no adivinada), aplica el
     * desplazamiento guardado para saber en qué línea/columna debe estar el valor ahora, y
     * coge esa misma anchura de caracteres — el usuario marcó el valor completo la primera
     * vez, así que no hace falta adivinar dónde termina buscando espacios (un valor de varias
     * palabras, como una fecha en letra o un importe con el símbolo € suelto, no tiene un
     * único espacio que sirva de límite fiable).
     */
    public function buscarPorEtiquetaYDelta(string $texto, string $etiqueta, int $deltaColumna, int $deltaLineas, int $longitudValor): ?string
    {
        $posicionEtiqueta = mb_strpos($texto, $etiqueta);
        if ($posicionEtiqueta === false) {
            return null;
        }

        [$inicioLineaEtiqueta] = $this->limitesLinea($texto, $posicionEtiqueta);
        $columnaEtiqueta = $posicionEtiqueta - $inicioLineaEtiqueta;

        $lineas = explode("\n", $texto);
        $indiceLineaEtiqueta = substr_count(mb_substr($texto, 0, $inicioLineaEtiqueta), "\n");
        $indiceLineaObjetivo = $indiceLineaEtiqueta + $deltaLineas;

        if ($indiceLineaObjetivo < 0 || $indiceLineaObjetivo >= count($lineas)) {
            return null;
        }

        return $this->extraerTokenDeAncho($lineas[$indiceLineaObjetivo], $columnaEtiqueta + $deltaColumna, $longitudValor);
    }

    /** Coge exactamente esa anchura de caracteres desde la columna dada (sin buscar espacios). */
    protected function extraerTokenDeAncho(string $linea, int $columna, int $longitud): ?string
    {
        $longitudLinea = mb_strlen($linea);
        if ($longitudLinea === 0 || $columna < 0 || $columna >= $longitudLinea) {
            return null;
        }

        $token = trim(mb_substr($linea, $columna, $longitud));

        return $token === '' ? null : $token;
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

    /**
     * Si la selección empieza o acaba a mitad de un número (fácil que pase marcando con el
     * ratón, un carácter de más o de menos), engancha esos dígitos sueltos al valor en vez de
     * dejarlos en la etiqueta o perderlos — ej. seleccionar "6/05/2026" de "Fecha Factura:
     * 06/05/2026" debe capturar el "0" que quedó fuera, no dejarlo pegado a la etiqueta.
     */
    protected function ajustarLimitesNumero(string $texto, int $inicio, int $fin): array
    {
        while ($inicio > 0 && $this->esDigito(mb_substr($texto, $inicio - 1, 1))) {
            $inicio--;
        }

        while ($fin < mb_strlen($texto) && $this->esDigito(mb_substr($texto, $fin, 1))) {
            $fin++;
        }

        return [$inicio, $fin];
    }

    protected function esDigito(string $caracter): bool
    {
        return $caracter !== '' && preg_match('/\d/', $caracter) === 1;
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

    /**
     * Recorre hasta N líneas hacia arriba (-1) o abajo (+1), saltando las vacías, buscando la
     * mejor columna en TODAS las líneas del margen (no solo la primera no vacía): una columna
     * de forma inesperada en la línea más cercana (ej. "20,08", otro importe de la misma fila)
     * no debe ganarle a la etiqueta real ("TOTAL FACTURA") que está una línea más lejos.
     */
    protected function buscarEnLineasCercanas(string $texto, int $inicioLineaActual, int $columnaInicio, int $columnaFin, int $hacia): ?string
    {
        $inicioLinea     = $inicioLineaActual;
        $mejorTexto      = null;
        $mejorPuntuacion = null;

        for ($salto = 0; $salto < self::SALTO_LINEAS_MAXIMO; $salto++) {
            if ($hacia < 0) {
                if ($inicioLinea === 0) {
                    break;
                }
                [$inicioLinea, $finLinea] = $this->limitesLinea($texto, $inicioLinea - 1);
            } else {
                [, $finLineaActual] = $this->limitesLinea($texto, $inicioLinea);
                if ($finLineaActual >= mb_strlen($texto)) {
                    break;
                }
                [$inicioLinea, $finLinea] = $this->limitesLinea($texto, $finLineaActual + 1);
            }

            $linea = mb_substr($texto, $inicioLinea, $finLinea - $inicioLinea);
            if (trim($linea) === '') {
                continue;
            }

            [$texto_, $puntuacion] = $this->mejorColumna($linea, $columnaInicio, $columnaFin, $hacia);
            if ($texto_ !== null && ($mejorPuntuacion === null || $puntuacion < $mejorPuntuacion)) {
                $mejorPuntuacion = $puntuacion;
                $mejorTexto      = $texto_;
            }

            // Ya hay un candidato con la forma esperada (etiqueta o valor): no hace falta
            // seguir mirando más lejos, cuanto más cerca mejor entre los que sí cuadran.
            if ($mejorPuntuacion !== null && $mejorPuntuacion < 1000) {
                break;
            }
        }

        return $mejorTexto;
    }

    /**
     * De las columnas de la línea, la más cercana que "tiene la forma esperada" según la
     * dirección: buscando hacia atrás queremos una ETIQUETA (con letras), buscando hacia
     * adelante queremos un VALOR (con dígitos) — y solo si ninguna la tiene, la más cercana
     * sea cual sea. Devuelve [texto, puntuación] o [null, null].
     */
    protected function mejorColumna(string $linea, int $columnaInicio, int $columnaFin, int $hacia): array
    {
        $mejorTexto      = null;
        $mejorPuntuacion = null;

        foreach ($this->columnas($linea) as $columna) {
            $distancia = $this->distanciaSpans($columnaInicio, $columnaFin, $columna['inicio'], $columna['fin']);
            if ($distancia > self::TOLERANCIA_COLUMNA) {
                continue;
            }

            $formaEsperada = $hacia < 0
                ? preg_match('/\p{L}/u', $columna['texto'])
                : preg_match('/\d/', $columna['texto']);

            $puntuacion = ($formaEsperada ? 0 : 1000) + $distancia;

            if ($mejorPuntuacion === null || $puntuacion < $mejorPuntuacion) {
                $mejorPuntuacion = $puntuacion;
                $mejorTexto      = $columna['texto'];
            }
        }

        return [$mejorTexto, $mejorPuntuacion];
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

    /**
     * Quita puntuación suelta al principio (restos de un salto de línea a media etiqueta,
     * ej. ": B-36...") y un punto final "pegado" sin espacio (fin de frase, ej. "CIF.
     * A-82018474." — nunca es parte real de una fecha/CIF/importe/nº factura).
     */
    protected function limpiarValor(string $valor): string
    {
        $valor = preg_replace('/^[^\p{L}\p{N}]+/u', '', $valor);

        return preg_replace('/\.$/', '', $valor);
    }

    protected function primerToken(string $texto): string
    {
        $partes = preg_split('/\s{2,}/u', trim($texto));

        return trim($partes[0]);
    }
}
