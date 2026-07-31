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
            EntidadesBancariasSeeder::class,
            FormasDePagoSeeder::class,
            GeneroSeeder::class,
            TipoDocumentoIdentificativoSeeder::class,

            //CreateSuperUserSeeder::class,
            PermisosYRolesInicialSeeder::class,
            ProvinciasTableSeeder::class,
            // TiposdeviasTableSeeder::class,
        ]);

    }
}
