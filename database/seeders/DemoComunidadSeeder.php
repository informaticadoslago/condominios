<?php

namespace Database\Seeders;

use App\Models\Comunidad;
use App\Models\EntidadBancaria;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para hacer una demo: dos comunidades nuevas (con su persona
 * jurídica, igual que hace ComunidadForm::store()). Nombre y CIF se generan al azar
 * en cada ejecución (el CIF con dígito de control válido) para poder relanzar el
 * seeder varias veces sin chocar: se van acumulando comunidades demo en vez de
 * reescribir siempre las mismas dos. Aparte de DatabaseSeeder (no se llama
 * automáticamente): se lanza a mano con `php artisan db:seed --class=DemoComunidadSeeder`,
 * o mejor, encadenado con `php artisan condominios:fakeseed` (ver FakeSeed).
 */
class DemoComunidadSeeder extends Seeder
{
    public function run(): void
    {
        $this->generar();
    }

    /** @return array{edificio1: Comunidad, edificio2: Comunidad} */
    public function generar(): array
    {
        return [
            'edificio1' => $this->crearComunidad(),
            'edificio2' => $this->crearComunidad(),
        ];
    }

    /** Toda la demo va contra la misma entidad, para poder probar con un banco concreto. */
    private const ENTIDAD = '2080';

    private function crearComunidad(): Comunidad
    {
        $nombre = $this->nombreAlAzar();
        $cif    = $this->cifAlAzar();

        $persona = Persona::create([
            'nombre'                   => '',
            'apellido1'                => null,
            'apellido2'                => null,
            'razon_social'             => $nombre,
            'nombre_comercial'         => null,
            'documento_pais_id'        => Pais::ESPAÑA,
            'tipo_documento_id'        => TipoDocumentoIdentificativo::DOCUMENTO_CIF,
            'documento_identificativo' => $cif,
            'fecha_nacimiento'         => null,
            'genero_id'                => TipoGenero::GENERO_OTRO,
        ]);

        $sufijo = '000';

        $comunidad = Comunidad::create([
            'persona_id'                  => $persona->id,
            'sufijo'                      => $sufijo,
            'identificador_acreedor_sepa' => Comunidad::calcularIdentificadorAcreedor($cif, $sufijo),
        ]);

        // Cuenta de abono de la comunidad: es a donde el banco ingresa cada remesa, y
        // sin ella no se puede generar ninguna.
        $cuenta = $comunidad->cuentasBancarias()->create([
            'iban'                => $this->ibanComunidad($comunidad->id),
            'entidad_bancaria_id' => EntidadBancaria::where('codigo', self::ENTIDAD)->value('id'),
            'alias'               => 'Cuenta de la comunidad',
        ]);

        $this->command?->info(
            "Comunidad «{$nombre}» ({$cif}): id {$comunidad->id}, "
            ."acreedor {$comunidad->identificador_acreedor_sepa}, cuenta {$cuenta->iban}"
        );

        return $comunidad;
    }

    /**
     * IBAN de la cuenta de la comunidad, en la entidad 2080. El número de cuenta lleva
     * el id dentro para que dos comunidades demo nunca compartan IBAN.
     */
    private function ibanComunidad(int $comunidadId): string
    {
        $oficina = '0001';
        $cuenta  = str_pad((string) $comunidadId, 10, '0', STR_PAD_LEFT);

        $ccc = self::ENTIDAD.$oficina
            .$this->digitoControlCcc('00'.self::ENTIDAD.$oficina).$this->digitoControlCcc($cuenta)
            .$cuenta;

        // Control del IBAN: 'ES00' al final (E=14, S=28) y lo que falta hasta 98 del
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

    /** "Residencial/Edificio/... + nombre" al azar: no hace falta que sea único, solo variado. */
    private function nombreAlAzar(): string
    {
        $tipos = ['Residencial', 'Edificio', 'Urbanización', 'Conjunto Residencial'];
        $nombres = [
            'Los Almendros', 'Los Robles', 'El Pinar', 'Las Palmeras', 'Vista Alegre',
            'Los Cipreses', 'El Mirador', 'Las Acacias', 'Los Sauces', 'El Bosque',
            'Las Encinas', 'Los Naranjos', 'El Olivar', 'Las Terrazas', 'El Parque',
        ];

        return $tipos[array_rand($tipos)].' '.$nombres[array_rand($nombres)].' '.random_int(1, 999);
    }

    /**
     * CIF de comunidad de propietarios (letra H, ver App\Rules\Includes\ValidadorDocumentoId)
     * con dígito de control válido, para que pase IsCifComunidadRule si algún día se
     * valida. Reintenta si por casualidad ya existe ese número exacto (con 7 cifras al
     * azar, prácticamente nunca).
     */
    private function cifAlAzar(): string
    {
        do {
            $numero = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
            $cif    = 'H'.$numero.$this->digitoControlCif($numero);
        } while (Persona::where('documento_identificativo', $cif)->exists());

        return $cif;
    }

    /** Mismo algoritmo que ValidadorDocumentoId::getCIFCheckDigit() — para la letra H, el dígito siempre es numérico. */
    private function digitoControlCif(string $sieteDigitos): string
    {
        $par = (int) $sieteDigitos[1] + (int) $sieteDigitos[3] + (int) $sieteDigitos[5];
        $impar = $this->sumaDigitos((int) $sieteDigitos[0] * 2)
            + $this->sumaDigitos((int) $sieteDigitos[2] * 2)
            + $this->sumaDigitos((int) $sieteDigitos[4] * 2)
            + $this->sumaDigitos((int) $sieteDigitos[6] * 2);

        $ultimaCifraSuma = ($par + $impar) % 10;

        return (string) ($ultimaCifraSuma > 0 ? 10 - $ultimaCifraSuma : 0);
    }

    private function sumaDigitos(int $numero): int
    {
        return array_sum(str_split((string) $numero));
    }
}
