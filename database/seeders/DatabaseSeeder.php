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
            //EstadoSeeder::class,
            //EstadoUsuarioSeeder::class,

            //EntidadesBancariasSeeder::class,
            GeneroSeeder::class,
            //PaisSeeder::class,
            //ProvinciaSeeder::class,
            //MunicipioSeeder::class,
            //PoblacionSeeder::class,
            TipoDocumentoIdentificativoSeeder::class,

            //CreateSuperUserSeeder::class,
            PermisosYRolesInicialSeeder::class,
            // AjustesUserModelL8Seeder::class,
            // ProvinciasTableSeeder::class,
            // TiposdeviasTableSeeder::class,
        ]);

    }
}
