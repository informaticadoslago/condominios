<?php
namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            PlanCuentasComunidadesSeeder::class,
            EntidadesBancariasSeeder::class,
            FormasDePagoSeeder::class,
            GeneroSeeder::class,
            TipoDocumentoIdentificativoSeeder::class,

            //CreateSuperUserSeeder::class,
            PermisosYRolesInicialSeeder::class,
            // TiposdeviasTableSeeder::class,
        ]);

    }
}
