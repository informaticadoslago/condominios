<?php

namespace Database\Seeders;

use App\Models\Comunidad;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para hacer una demo: por ahora, dos comunidades (con su persona
 * jurídica, igual que hace ComunidadForm::store()). Aparte de DatabaseSeeder (no se
 * llama automáticamente): se lanza a mano con
 * `php artisan db:seed --class=DemoComunidadSeeder`.
 */
class DemoComunidadSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->comunidades() as $datos) {
            $persona = Persona::updateOrCreate(
                ['documento_identificativo' => $datos['cif']],
                [
                    'nombre'                   => '',
                    'apellido1'                => null,
                    'apellido2'                => null,
                    'razon_social'             => $datos['nombre'],
                    'nombre_comercial'         => null,
                    'documento_pais_id'        => Pais::ESPAÑA,
                    'tipo_documento_id'        => TipoDocumentoIdentificativo::DOCUMENTO_CIF,
                    'fecha_nacimiento'         => null,
                    'genero_id'                => TipoGenero::GENERO_OTRO,
                ]
            );

            $comunidad = Comunidad::firstOrCreate(['persona_id' => $persona->id]);

            $this->command?->info("Comunidad «{$datos['nombre']}» ({$datos['cif']}): id {$comunidad->id}");
        }
    }

    /** @return array<int, array{nombre: string, cif: string}> */
    private function comunidades(): array
    {
        return [
            ['nombre' => 'Residencial Los Almendros', 'cif' => 'H12345674'],
            ['nombre' => 'Edificio Marítimo', 'cif' => 'H76543214'],
        ];
    }
}
