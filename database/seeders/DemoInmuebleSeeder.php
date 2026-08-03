<?php

namespace Database\Seeders;

use App\Models\Comunidad;
use App\Models\Inmueble;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Models\TipoInmueble;
use App\Models\TipoOcupacion;
use App\Models\Titularidad;
use Illuminate\Database\Seeder;

/**
 * Un edificio de demo por comunidad (las que crea DemoComunidadSeeder, localizado
 * por su CIF): planta baja + 3 plantas, con propietarios variados (hombres y
 * mujeres, algún menor de edad, algún NIE) y algún inmueble compartido entre varios.
 * El edificio 1 tiene además 15 plazas de garaje en planta -1.
 * Aparte de DatabaseSeeder: `php artisan db:seed --class=DemoInmuebleSeeder`
 * (o mejor, `php artisan doslago:fakeseed`, que ya lo encadena).
 */
class DemoInmuebleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->edificios() as $cif => $spec) {
            $comunidad = $this->comunidadPorCif($cif);

            if (! $comunidad) {
                $this->command?->warn("No existe ninguna comunidad con CIF {$cif} (¿has ejecutado DemoComunidadSeeder antes?).");

                continue;
            }

            $propietarios = [];
            foreach ($spec['personas'] as $clave => $datos) {
                $propietarios[$clave] = $this->crearPropietario($comunidad, $datos);
            }

            $inmuebles = [];
            foreach ($spec['inmuebles'] as $clave => $datos) {
                $inmuebles[$clave] = $this->crearInmueble($comunidad, $datos);
            }

            foreach ($spec['titularidades'] as $inmuebleClave => $lineas) {
                foreach ($lineas as $linea) {
                    Titularidad::firstOrCreate(
                        [
                            'inmueble_id'    => $inmuebles[$inmuebleClave]->id,
                            'propietario_id' => $propietarios[$linea['persona']]->id,
                        ],
                        [
                            'cuota_percent' => $linea['cuota'],
                            'causa'         => $linea['causa'],
                            'fecha_inicio'  => $linea['inicio'],
                            'fecha_fin'     => null,
                        ]
                    );
                }
            }

            $this->command?->info(
                "Edificio de «{$comunidad->nombre}»: ".count($inmuebles).' inmuebles, '.count($propietarios).' propietarios.'
            );
        }
    }

    private function comunidadPorCif(string $cif): ?Comunidad
    {
        $persona = Persona::where('documento_identificativo', $cif)->first();

        return $persona ? Comunidad::where('persona_id', $persona->id)->first() : null;
    }

    private function crearPropietario(Comunidad $comunidad, array $datos): Propietario
    {
        $persona = PersonaComunidad::updateOrCreate(
            ['comunidad_id' => $comunidad->id, 'documento_identificativo' => $datos['documento']],
            [
                'nombre'            => $datos['nombre'],
                'apellido1'         => $datos['apellido1'],
                'apellido2'         => $datos['apellido2'],
                'documento_pais_id' => Pais::ESPAÑA,
                'tipo_documento_id' => $datos['tipo_documento'],
                'fecha_nacimiento'  => $datos['nacimiento'],
                'genero_id'         => $datos['genero'],
            ]
        );

        return Propietario::firstOrCreate(['persona_comunidad_id' => $persona->id]);
    }

    /** updateOrCreate (no firstOrCreate): así un cambio de coeficiente en el spec se aplica al relanzar. */
    private function crearInmueble(Comunidad $comunidad, array $datos): Inmueble
    {
        return Inmueble::updateOrCreate(
            ['comunidad_id' => $comunidad->id, 'planta' => $datos['planta'], 'puerta' => $datos['puerta']],
            [
                'ocupacion_id'     => TipoOcupacion::PROPIETARIO,
                'tipo_inmueble_id' => $datos['tipo_inmueble_id'] ?? TipoInmueble::PISO,
                'coeficiente'      => $datos['coeficiente'],
            ]
        );
    }

    /**
     * Bajo (20%) + plantas 1 y 2 partidas en A/B + planta 3 entera con el resto
     * hasta el 100%. Reparto base, sin garaje (lo usa tal cual el edificio 2).
     */
    private function inmueblesDelEdificio(): array
    {
        return [
            // planta es tinyInteger: Bajo = 0, como en el resto de la app (ver DatosStep).
            'bajo' => ['planta' => 0, 'puerta' => null, 'coeficiente' => 20.00],
            '1a'   => ['planta' => 1, 'puerta' => 'A', 'coeficiente' => 10.00],
            '1b'   => ['planta' => 1, 'puerta' => 'B', 'coeficiente' => 16.67],
            '2a'   => ['planta' => 2, 'puerta' => 'A', 'coeficiente' => 12.00],
            '2b'   => ['planta' => 2, 'puerta' => 'B', 'coeficiente' => 14.67],
            '3'    => ['planta' => 3, 'puerta' => null, 'coeficiente' => 26.66],
        ];
    }

    /**
     * Pisos del edificio 1: al reparto base se le resta el 20% del garaje a partes
     * iguales entre los 6 (20 ÷ 6 = 3,333...%; en 2 decimales, 3,33% para cuatro y
     * 3,34% para dos, para que la resta sume exactamente 20,00 y no 19,98).
     */
    private function pisosEdificio1(): array
    {
        return [
            'bajo' => ['planta' => 0, 'puerta' => null, 'coeficiente' => 16.66], // 20.00 - 3.34
            '1a'   => ['planta' => 1, 'puerta' => 'A', 'coeficiente' => 6.67],   // 10.00 - 3.33
            '1b'   => ['planta' => 1, 'puerta' => 'B', 'coeficiente' => 13.33], // 16.67 - 3.34
            '2a'   => ['planta' => 2, 'puerta' => 'A', 'coeficiente' => 8.67],   // 12.00 - 3.33
            '2b'   => ['planta' => 2, 'puerta' => 'B', 'coeficiente' => 11.34], // 14.67 - 3.33
            '3'    => ['planta' => 3, 'puerta' => null, 'coeficiente' => 23.33], // 26.66 - 3.33
        ];
    }

    /**
     * 15 plazas en planta -1: entre todas ocupan el 20% del edificio. Las 2 grandes
     * ocupan el DOBLE que una normal. 20% ÷ 17 "partes" (13 normales + 2×2) no cae
     * limpio en céntimos: la parte normal sale en 1,176...%, así que 7 quedan en
     * 1,18% y 6 en 1,17% (la diferencia de un céntimo entre ellas es inevitable al
     * redondear) para que la suma cuadre en 20,00 exactos; las grandes sí quedan
     * justo al doble de 1,18 → 2,36% cada una.
     */
    private function garajesEdificio1(): array
    {
        $garajes = [];

        foreach (range(1, 15) as $numero) {
            $coeficiente = match (true) {
                in_array($numero, [1, 2], true) => 2.36,
                in_array($numero, [3, 4, 5, 6, 7, 8, 9], true) => 1.18,
                default => 1.17,
            };

            $garajes["garaje{$numero}"] = [
                'planta'           => -1,
                'puerta'           => (string) $numero,
                'coeficiente'      => $coeficiente,
                'tipo_inmueble_id' => TipoInmueble::GARAJE,
            ];
        }

        return $garajes;
    }

    /**
     * Una plaza por piso (ligada a uno de sus propietarios actuales) más 9 plazas
     * sueltas al 100% de un único dueño: algunas de propietarios que ya existen
     * (repiten plaza) y otras de gente nueva que solo tiene garaje, ningún piso.
     */
    private function titularidadesGarajesEdificio1(): array
    {
        return [
            'garaje1'  => [['persona' => 'p1', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2015-06-01']], // Antonio, ligada al Bajo
            'garaje2'  => [['persona' => 'p3', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2018-03-12']], // Lucía, ligada a 1ºA
            'garaje3'  => [['persona' => 'p4', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2022-01-15']], // Diego, ligada a 1ºB
            'garaje4'  => [['persona' => 'p5', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2019-09-01']], // Klaus, ligada a 2ºA
            'garaje5'  => [['persona' => 'p6', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2020-05-18']], // Carmen, ligada a 2ºB
            'garaje6'  => [['persona' => 'p1', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2023-04-10']], // Antonio (2ª plaza), ligada al 3º
            'garaje7'  => [['persona' => 'p2', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2015-06-01']], // María, co-dueña del Bajo
            'garaje8'  => [['persona' => 'p5', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2021-11-02']], // Klaus (2ª plaza)
            'garaje9'  => [['persona' => 'p7', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2016-02-20']],
            'garaje10' => [['persona' => 'p8', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2017-08-14']],
            'garaje11' => [['persona' => 'p9', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2018-12-05']],
            'garaje12' => [['persona' => 'p10', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_DONACION, 'inicio' => '2020-03-22']],
            'garaje13' => [['persona' => 'p11', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2024-06-01']],
            'garaje14' => [['persona' => 'p12', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2019-04-17']],
            'garaje15' => [['persona' => 'p13', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2022-09-09']],
        ];
    }

    /**
     * Primer edificio: el bajo lo comparten dos propietarios al 50%; 1A, 1B, 2A y 2B
     * tienen cada uno el suyo; y la planta 3 la comparten (a partes casi iguales) uno
     * del bajo, el de 1ºA y el de 2ºB — variado en género, con un menor (herencia) y
     * un NIE (residente extranjero). Además, 15 plazas de garaje en planta -1.
     */
    private function edificio1(): array
    {
        return [
            'personas' => [
                'p1'  => ['nombre' => 'Antonio', 'apellido1' => 'García', 'apellido2' => 'Pérez', 'documento' => '11111111H', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1975-03-14'],
                'p2'  => ['nombre' => 'María', 'apellido1' => 'Fernández', 'apellido2' => 'López', 'documento' => '22222222J', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1980-07-22'],
                'p3'  => ['nombre' => 'Lucía', 'apellido1' => 'Rodríguez', 'apellido2' => 'Sánchez', 'documento' => '33333333P', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1990-11-05'],
                'p4'  => ['nombre' => 'Diego', 'apellido1' => 'García', 'apellido2' => 'Fernández', 'documento' => '44444444A', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '2010-05-20'],
                'p5'  => ['nombre' => 'Klaus', 'apellido1' => 'Weber', 'apellido2' => null, 'documento' => 'X1234567L', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIE, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1985-02-10'],
                'p6'  => ['nombre' => 'Carmen', 'apellido1' => 'Sánchez', 'apellido2' => 'Domínguez', 'documento' => '66666666Q', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1978-09-30'],
                // Solo tienen garaje, ningún piso.
                'p7'  => ['nombre' => 'Roberto', 'apellido1' => 'Delgado', 'apellido2' => 'Vega', 'documento' => '12345678Z', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1982-03-11'],
                'p8'  => ['nombre' => 'Beatriz', 'apellido1' => 'Morales', 'apellido2' => 'Reyes', 'documento' => '23456789D', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1976-08-29'],
                'p9'  => ['nombre' => 'Fernando', 'apellido1' => 'Ibáñez', 'apellido2' => 'Cano', 'documento' => '34567890V', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1993-01-17'],
                'p10' => ['nombre' => 'Alicia', 'apellido1' => 'Serrano', 'apellido2' => 'Pascual', 'documento' => '45678901G', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1987-10-03'],
                'p11' => ['nombre' => 'Hugo', 'apellido1' => 'Prieto', 'apellido2' => 'Gallardo', 'documento' => '56789012B', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '2011-02-14'],
                'p12' => ['nombre' => 'Marta', 'apellido1' => 'Cortés', 'apellido2' => 'Reyes', 'documento' => '67890123B', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1995-06-25'],
                'p13' => ['nombre' => 'Giulia', 'apellido1' => 'Rossi', 'apellido2' => null, 'documento' => 'Z9876543A', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIE, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1989-07-19'],
            ],
            'inmuebles' => array_merge($this->pisosEdificio1(), $this->garajesEdificio1()),
            'titularidades' => array_merge(
                [
                    'bajo' => [
                        ['persona' => 'p1', 'cuota' => 50.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2015-06-01'],
                        ['persona' => 'p2', 'cuota' => 50.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2015-06-01'],
                    ],
                    '1a' => [
                        ['persona' => 'p3', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2018-03-12'],
                    ],
                    '1b' => [
                        ['persona' => 'p4', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2022-01-15'],
                    ],
                    '2a' => [
                        ['persona' => 'p5', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2019-09-01'],
                    ],
                    '2b' => [
                        ['persona' => 'p6', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2020-05-18'],
                    ],
                    '3' => [
                        ['persona' => 'p1', 'cuota' => 33.34, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2023-04-10'],
                        ['persona' => 'p3', 'cuota' => 33.33, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2023-04-10'],
                        ['persona' => 'p6', 'cuota' => 33.33, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2023-04-10'],
                    ],
                ],
                $this->titularidadesGarajesEdificio1()
            ),
        ];
    }

    /** Segundo edificio: mismo reparto de coeficientes, pero sin compartir ningún inmueble ni garaje. */
    private function edificio2(): array
    {
        return [
            'personas' => [
                'q1' => ['nombre' => 'Manuel', 'apellido1' => 'Torres', 'apellido2' => 'Vidal', 'documento' => '77777777B', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1972-01-25'],
                'q2' => ['nombre' => 'Isabel', 'apellido1' => 'Romero', 'apellido2' => 'Castro', 'documento' => '88888888Y', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1988-06-14'],
                'q3' => ['nombre' => 'Sofía', 'apellido1' => 'Jiménez', 'apellido2' => 'Ortega', 'documento' => '99999999R', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '2012-08-02'],
                'q4' => ['nombre' => 'Sophie', 'apellido1' => 'Dubois', 'apellido2' => null, 'documento' => 'Y7654321G', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIE, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1983-12-19'],
                'q5' => ['nombre' => 'Elena', 'apellido1' => 'Vázquez', 'apellido2' => 'Molina', 'documento' => '20202020Q', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1979-04-08'],
                'q6' => ['nombre' => 'Raúl', 'apellido1' => 'Castillo', 'apellido2' => 'Herrera', 'documento' => '30303030R', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1991-10-30'],
            ],
            'inmuebles' => $this->inmueblesDelEdificio(),
            'titularidades' => [
                'bajo' => [['persona' => 'q1', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2016-02-20']],
                '1a'   => [['persona' => 'q2', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2017-05-11']],
                '1b'   => [['persona' => 'q3', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_HERENCIA, 'inicio' => '2024-03-01']],
                '2a'   => [['persona' => 'q4', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2019-11-23']],
                '2b'   => [['persona' => 'q5', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_DONACION, 'inicio' => '2021-07-07']],
                '3'    => [['persona' => 'q6', 'cuota' => 100.00, 'causa' => Titularidad::CAUSA_COMPRAVENTA, 'inicio' => '2020-09-14']],
            ],
        ];
    }

    /** @return array<string, array> CIF de la comunidad => especificación del edificio */
    private function edificios(): array
    {
        return [
            'H12345674' => $this->edificio1(),
            'H76543214' => $this->edificio2(),
        ];
    }
}
