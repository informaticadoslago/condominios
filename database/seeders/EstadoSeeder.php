<?php

namespace Database\Seeders;

use App\Models\Estado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EstadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Estado::truncate();
        Schema::enableForeignKeyConstraints();

        $estados = [
            ['id' => 1, 'descripcion' => 'Activo'],
            ['id' => 2, 'descripcion' => 'Inactivo'],
            ['id' => 3, 'descripcion' => 'En preparación'],
        ];
        foreach ($estados as $estado) {
            Estado::create($estado);
        }

    }
}
