<?php

namespace Database\Seeders;

use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\EntidadBancaria;
use App\Models\FormaDePago;
use App\Models\FormaPagoInmueble;
use App\Models\Inmueble;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Models\TipoInmueble;
use App\Models\TipoOcupacion;
use App\Models\Titularidad;
use Illuminate\Database\Seeder;

/**
 * Un edificio de demo por comunidad: planta baja + 3 plantas, con propietarios
 * variados (hombres y mujeres, algún menor de edad, algún NIE) y algún inmueble
 * compartido entre varios. Cada propietario tiene una cuenta bancaria (IBAN
 * ficticio pero válido) y cada inmueble su forma de pago vigente: la mayoría por
 * recibo bancario y unos cuantos por transferencia. El edificio 1 tiene además 15
 * plazas de garaje en planta -1. Las comunidades a las que se cuelgan estos edificios las genera
 * DemoComunidadSeeder (nombre y CIF al azar en cada pasada, para poder acumular);
 * por eso este seeder ya no busca una comunidad por CIF fijo, sino que recibe las
 * comunidades recién creadas (ver generar()). Aparte de DatabaseSeeder: mejor
 * `php artisan condominios:fakeseed` (ver FakeSeed), que encadena los tres. Lanzado
 * suelto (`php artisan db:seed --class=DemoInmuebleSeeder`) genera también su propio
 * par de comunidades nuevas.
 */
class DemoInmuebleSeeder extends Seeder
{
    public function run(): void
    {
        $comunidadSeeder = new DemoComunidadSeeder();
        $comunidadSeeder->setCommand($this->command);

        $this->generar($comunidadSeeder->generar());
    }

    /** @param array{edificio1: Comunidad, edificio2: Comunidad} $comunidades */
    public function generar(array $comunidades): void
    {
        foreach (['edificio1' => $this->edificio1(), 'edificio2' => $this->edificio2()] as $clave => $spec) {
            $comunidad = $comunidades[$clave];

            $propietarios = [];
            foreach ($spec['personas'] as $clavePersona => $datos) {
                $propietarios[$clavePersona] = $this->crearPropietario($comunidad, $datos);
            }

            // En una segunda pasada, porque la cuenta de un propietario menor de edad
            // la firma OTRA persona del mismo edificio, que puede ir después en el spec.
            $cuentas = [];
            $orden   = 0;
            foreach ($spec['personas'] as $clavePersona => $datos) {
                $cuentas[$clavePersona] = $this->crearCuentaBancaria(
                    $comunidad,
                    $propietarios[$clavePersona],
                    $datos,
                    ++$orden,
                    isset($datos['titular_cuenta']) ? $propietarios[$datos['titular_cuenta']] : null,
                );
            }

            $inmuebles = [];
            foreach ($spec['inmuebles'] as $claveInmueble => $datos) {
                $inmuebles[$claveInmueble] = $this->crearInmueble($comunidad, $datos);
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

            $porTransferencia = $this->crearFormasDePago($spec, $inmuebles, $propietarios, $cuentas);

            $this->command?->info(
                "Edificio de «{$comunidad->nombre}»: ".count($inmuebles).' inmuebles, '.count($propietarios)
                .' propietarios (todos con cuenta bancaria), '.$porTransferencia
                .' inmuebles por transferencia y '.(count($inmuebles) - $porTransferencia).' por recibo bancario.'
            );
        }
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

    /**
     * Una cuenta por propietario, con IBAN ficticio pero válido y distinto en cada
     * comunidad (el número de cuenta lleva dentro el id de la comunidad y el orden de
     * la persona en el spec, así relanzar el seeder no cambia los IBAN ya creados).
     *
     * $titularReal solo viene cuando el propietario es menor de edad: los menores no
     * tienen firma, así que la cuenta es suya pero la titula un adulto (misma regla
     * que App\Livewire\Propietarios\Crear\Steps\CuentaBancariaStep).
     */
    private function crearCuentaBancaria(
        Comunidad $comunidad,
        Propietario $propietario,
        array $datos,
        int $orden,
        ?Propietario $titularReal = null,
    ): CuentaBancaria {
        $atributos = [
            'iban'                 => $this->ibanEspanol(
                $datos['entidad'],
                // Oficina inventada, una distinta por persona.
                str_pad((string) (1000 + $orden), 4, '0', STR_PAD_LEFT),
                str_pad((string) $comunidad->id, 6, '0', STR_PAD_LEFT).str_pad((string) $orden, 4, '0', STR_PAD_LEFT),
            ),
            'entidad_bancaria_id'  => $this->entidadBancariaId($datos['entidad']),
            'persona_comunidad_id' => ($titularReal ?? $propietario)->persona_comunidad_id,
        ];

        $cuenta = $propietario->cuentasBancarias()->first();

        if ($cuenta) {
            $cuenta->update($atributos);

            return $cuenta;
        }

        return $propietario->cuentasBancarias()->create($atributos);
    }

    /** id de la entidad bancaria por su código de 4 dígitos (ver EntidadesBancariasSeeder), cacheado. */
    private function entidadBancariaId(string $codigo): ?int
    {
        static $ids = [];

        return $ids[$codigo] ??= EntidadBancaria::where('codigo', $codigo)->value('id');
    }

    /**
     * IBAN español a partir de entidad + oficina + número de cuenta: calcula los dos
     * dígitos de control del CCC y después los del propio IBAN. Los datos son
     * ficticios, pero el IBAN resultante pasa la validación real (ver App\Rules\IsIBANRule).
     */
    private function ibanEspanol(string $entidad, string $oficina, string $cuenta): string
    {
        $ccc = $entidad.$oficina
            .$this->digitoControlCcc('00'.$entidad.$oficina).$this->digitoControlCcc($cuenta)
            .$cuenta;

        // Control del IBAN: se pasa "ES00" al final (E=14, S=28) y se completa a 98 el
        // resto de dividir entre 97.
        $control = 98 - (int) bcmod($ccc.'142800', '97');

        return 'ES'.str_pad((string) $control, 2, '0', STR_PAD_LEFT).$ccc;
    }

    /** Dígito de control del CCC: pesos 1,2,4,8,5,10,9,7,3,6; un resto de 10 vale 1 y uno de 11 vale 0. */
    private function digitoControlCcc(string $digitos): string
    {
        $pesos = [1, 2, 4, 8, 5, 10, 9, 7, 3, 6];
        $suma  = 0;

        foreach (str_split($digitos) as $posicion => $digito) {
            $suma += ((int) $digito) * $pesos[$posicion];
        }

        $resto = 11 - ($suma % 11);

        return (string) match ($resto) {
            11 => 0,
            10 => 1,
            default => $resto,
        };
    }

    /**
     * Forma de pago vigente de cada inmueble. Paga el titular de mayor cuota (el
     * primero de la lista de titularidades) y, salvo los que el spec manda por
     * transferencia, va por recibo bancario contra la cuenta de ese titular. La fecha
     * de inicio es la de su titularidad: la forma de pago nace con el propietario.
     *
     * @param  array<string, Inmueble>       $inmuebles
     * @param  array<string, Propietario>    $propietarios
     * @param  array<string, CuentaBancaria> $cuentas
     * @return int inmuebles que quedan por transferencia
     */
    private function crearFormasDePago(array $spec, array $inmuebles, array $propietarios, array $cuentas): int
    {
        $porTransferencia = 0;

        foreach ($spec['titularidades'] as $inmuebleClave => $lineas) {
            $linea = $lineas[0];
            // (string): la clave '3' (la planta entera) PHP la guarda como entero al ser
            // índice de array, y la lista de transferencias son cadenas.
            $transfiere  = in_array((string) $inmuebleClave, $spec['transferencias'], true);
            $propietario = $propietarios[$linea['persona']];

            if ($transfiere) {
                $porTransferencia++;
            }

            $atributos = [
                'propietario_id'     => $propietario->id,
                'forma_de_pago_id'   => $transfiere ? FormaDePago::TRANSFERENCIA : FormaDePago::RECIBO_BANCARIO,
                'cuenta_bancaria_id' => $transfiere ? null : $cuentas[$linea['persona']]->id,
                'fecha_inicio'       => $linea['inicio'],
            ];

            // En la app un cambio de forma de pago cierra la fila vigente y abre otra
            // (ver FormaPagoInmueble). Aquí no: son datos de demo y lo que interesa es
            // que relanzar el seeder deje lo que dice el spec, no un historial inventado.
            $vigente = FormaPagoInmueble::vigente()->where('inmueble_id', $inmuebles[$inmuebleClave]->id)->first();

            if ($vigente) {
                $vigente->update($atributos);
            } else {
                FormaPagoInmueble::create($atributos + [
                    'inmueble_id' => $inmuebles[$inmuebleClave]->id,
                    'fecha_fin'   => null,
                ]);
            }
        }

        return $porTransferencia;
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
                'p1'  => ['nombre' => 'Antonio', 'apellido1' => 'García', 'apellido2' => 'Pérez', 'documento' => '11111111H', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1975-03-14', 'entidad' => '2100'],
                'p2'  => ['nombre' => 'María', 'apellido1' => 'Fernández', 'apellido2' => 'López', 'documento' => '22222222J', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1980-07-22', 'entidad' => '0182'],
                'p3'  => ['nombre' => 'Lucía', 'apellido1' => 'Rodríguez', 'apellido2' => 'Sánchez', 'documento' => '33333333P', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1990-11-05', 'entidad' => '0049'],
                // Menor: su cuenta la titula Antonio (p1), del que lleva el primer apellido.
                'p4'  => ['nombre' => 'Diego', 'apellido1' => 'García', 'apellido2' => 'Fernández', 'documento' => '44444444A', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '2010-05-20', 'entidad' => '2100', 'titular_cuenta' => 'p1'],
                'p5'  => ['nombre' => 'Klaus', 'apellido1' => 'Weber', 'apellido2' => null, 'documento' => 'X1234567L', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIE, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1985-02-10', 'entidad' => '1465'],
                'p6'  => ['nombre' => 'Carmen', 'apellido1' => 'Sánchez', 'apellido2' => 'Domínguez', 'documento' => '66666666Q', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1978-09-30', 'entidad' => '0081'],
                // Solo tienen garaje, ningún piso.
                'p7'  => ['nombre' => 'Roberto', 'apellido1' => 'Delgado', 'apellido2' => 'Vega', 'documento' => '12345678Z', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1982-03-11', 'entidad' => '0128'],
                'p8'  => ['nombre' => 'Beatriz', 'apellido1' => 'Morales', 'apellido2' => 'Reyes', 'documento' => '23456789D', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1976-08-29', 'entidad' => '2080'],
                'p9'  => ['nombre' => 'Fernando', 'apellido1' => 'Ibáñez', 'apellido2' => 'Cano', 'documento' => '34567890V', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1993-01-17', 'entidad' => '3058'],
                'p10' => ['nombre' => 'Alicia', 'apellido1' => 'Serrano', 'apellido2' => 'Pascual', 'documento' => '45678901G', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1987-10-03', 'entidad' => '2103'],
                // Menor sin ningún parentesco en el edificio: firma por él otra adulta, Alicia (p10).
                'p11' => ['nombre' => 'Hugo', 'apellido1' => 'Prieto', 'apellido2' => 'Gallardo', 'documento' => '56789012B', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '2011-02-14', 'entidad' => '0073', 'titular_cuenta' => 'p10'],
                'p12' => ['nombre' => 'Marta', 'apellido1' => 'Cortés', 'apellido2' => 'Reyes', 'documento' => '67890123B', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1995-06-25', 'entidad' => '0049'],
                'p13' => ['nombre' => 'Giulia', 'apellido1' => 'Rossi', 'apellido2' => null, 'documento' => 'Z9876543A', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIE, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1989-07-19', 'entidad' => '0182'],
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
            // El resto va por recibo bancario. Aquí: un piso de un menor (1ºB, Diego), uno
            // de un residente extranjero (2ºA, Klaus) y tres plazas sueltas.
            'transferencias' => ['1b', '2a', 'garaje9', 'garaje12', 'garaje14'],
        ];
    }

    /** Segundo edificio: mismo reparto de coeficientes, pero sin compartir ningún inmueble ni garaje. */
    private function edificio2(): array
    {
        return [
            'personas' => [
                'q1' => ['nombre' => 'Manuel', 'apellido1' => 'Torres', 'apellido2' => 'Vidal', 'documento' => '77777777B', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1972-01-25', 'entidad' => '2100'],
                'q2' => ['nombre' => 'Isabel', 'apellido1' => 'Romero', 'apellido2' => 'Castro', 'documento' => '88888888Y', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1988-06-14', 'entidad' => '0182'],
                // Menor sin ningún parentesco en el edificio: firma por ella Isabel (q2).
                'q3' => ['nombre' => 'Sofía', 'apellido1' => 'Jiménez', 'apellido2' => 'Ortega', 'documento' => '99999999R', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '2012-08-02', 'entidad' => '0049', 'titular_cuenta' => 'q2'],
                'q4' => ['nombre' => 'Sophie', 'apellido1' => 'Dubois', 'apellido2' => null, 'documento' => 'Y7654321G', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIE, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1983-12-19', 'entidad' => '1465'],
                'q5' => ['nombre' => 'Elena', 'apellido1' => 'Vázquez', 'apellido2' => 'Molina', 'documento' => '20202020Q', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_MUJER, 'nacimiento' => '1979-04-08', 'entidad' => '0081'],
                'q6' => ['nombre' => 'Raúl', 'apellido1' => 'Castillo', 'apellido2' => 'Herrera', 'documento' => '30303030R', 'tipo_documento' => TipoDocumentoIdentificativo::DOCUMENTO_NIF, 'genero' => TipoGenero::GENERO_HOMBRE, 'nacimiento' => '1991-10-30', 'entidad' => '0128'],
            ],
            'inmuebles' => $this->inmueblesDelEdificio(),
            // Aquí el menor (1ºB, Sofía) sí va por recibo bancario, contra la cuenta que le
            // titula Isabel: en el edificio 1 es al revés, para tener los dos casos.
            'transferencias' => ['1a', '3'],
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
}
