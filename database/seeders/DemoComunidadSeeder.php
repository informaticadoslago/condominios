<?php

namespace Database\Seeders;

use App\Models\Comunidad;
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

        $comunidad = Comunidad::create(['persona_id' => $persona->id]);

        $this->command?->info("Comunidad «{$nombre}» ({$cif}): id {$comunidad->id}");

        return $comunidad;
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
