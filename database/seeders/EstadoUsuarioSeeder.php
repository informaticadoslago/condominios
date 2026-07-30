<?php

namespace Database\Seeders;

use App\Models\EstadoUsuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class EstadoUsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        EstadoUsuario::truncate();
        Schema::enableForeignKeyConstraints();

        $estados = [
            ['id' => 1, 'descripcion' => 'Activo'],
            ['id' => 2, 'descripcion' => 'Inactivo'],
            ['id' => 3, 'descripcion' => 'Inicial'],
        ];
        foreach ($estados as $estado) {
            EstadoUsuario::create($estado);
        }
        
    }
}
