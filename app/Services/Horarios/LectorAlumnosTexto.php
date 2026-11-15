<?php

namespace App\Services\Horarios;

use Carbon\Carbon;

/**
 * Saca alumnos de un listado pegado como texto, un dato por línea:
 *
 *   Acosta Bouzas, Marcos                  ← «Apellidos, Nombre»
 *   4º Ensinanza secundaria obrigatoria    ← curso (se ignora)
 *   2026/2027                              ← año académico (se ignora)
 *   E                                      ← grupo
 *   11/09/2011                             ← fecha de nacimiento
 *   23045                                  ← número (se ignora)
 *
 * No cuenta líneas: cada alumno empieza en una línea con coma y sin cifras, su fecha es
 * la primera línea dd/mm/aaaa que venga detrás y su grupo, la línea justo anterior a la
 * fecha. Así una línea de más o de menos en un alumno no descoloca a los siguientes.
 */
class LectorAlumnosTexto
{
    private const FECHA = '#^(\d{1,2})/(\d{1,2})/(\d{4})$#';

    /**
     * @return array<int, array{nombre: string, apellido1: string, apellido2: string, fecha_nacimiento: ?string, grupo: string, error: ?string}>
     */
    public function leer(string $texto): array
    {
        $lineas = array_values(array_filter(
            array_map(fn ($linea) => trim(preg_replace('/\s+/u', ' ', $linea)), preg_split('/\R/u', $texto)),
            fn ($linea) => $linea !== ''
        ));

        // Cada alumno: su línea de nombre y las que le siguen hasta el siguiente nombre.
        $bloques = [];
        foreach ($lineas as $linea) {
            if ($this->esNombre($linea)) {
                $bloques[] = ['nombre' => $linea, 'resto' => []];
            } elseif ($bloques) {
                $bloques[array_key_last($bloques)]['resto'][] = $linea;
            }
        }

        return array_map(fn (array $bloque) => $this->leerBloque($bloque['nombre'], $bloque['resto']), $bloques);
    }

    private function esNombre(string $linea): bool
    {
        return str_contains($linea, ',') && ! preg_match('/\d/', $linea);
    }

    /** @param  array<int, string>  $resto */
    private function leerBloque(string $lineaNombre, array $resto): array
    {
        [$apellidos, $nombre] = array_map('trim', explode(',', $lineaNombre, 2));

        // La última palabra es el segundo apellido; lo anterior, el primero. Con una sola
        // palabra no hay segundo apellido.
        $palabras  = explode(' ', $apellidos);
        $apellido2 = count($palabras) > 1 ? array_pop($palabras) : '';
        $apellido1 = implode(' ', $palabras);

        $fecha = null;
        $grupo = '';
        $error = null;

        foreach ($resto as $i => $linea) {
            if (preg_match(self::FECHA, $linea, $m)) {
                if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                    $fecha = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
                } else {
                    $error = __('La fecha :fecha no existe', ['fecha' => $linea]);
                }
                $grupo = mb_strtoupper($resto[$i - 1] ?? '');
                break;
            }
        }

        $error ??= match (true) {
            $apellido1 === '' || $nombre === ''                    => __('Falta el nombre o los apellidos'),
            mb_strlen($nombre) > 50 || mb_strlen($apellido1) > 50
                || mb_strlen($apellido2) > 50                      => __('Nombre o apellido de más de 50 letras'),
            $fecha === null                                        => __('Sin fecha de nacimiento'),
            $fecha > now()->toDateString()                         => __('La fecha de nacimiento es posterior a hoy'),
            $grupo === ''                                          => __('Sin grupo'),
            mb_strlen($grupo) > 10                                 => __('El grupo tiene más de 10 letras'),
            default                                                => null,
        };

        return [
            'nombre'           => $nombre,
            'apellido1'        => $apellido1,
            'apellido2'        => $apellido2,
            'fecha_nacimiento' => $fecha,
            'grupo'            => $grupo,
            'error'            => $error,
        ];
    }
}
