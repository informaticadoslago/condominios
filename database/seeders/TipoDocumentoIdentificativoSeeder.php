<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\TipoDocumentoIdentificativo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TipoDocumentoIdentificativoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        TipoDocumentoIdentificativo::truncate();
        Schema::enableForeignKeyConstraints();

        $tipos = [
            ['id' => 2, 'nombre' => 'NIF','tipo'=>'1'],
            ['id' => 3, 'nombre' => 'NIE','tipo'=>'1'],
            ['id' => 5, 'nombre' => 'Pasaporte','tipo'=>'1'],
            ['id' => 6, 'nombre' => 'CIF','tipo'=>'2'],
            ['id' => 4, 'nombre' => 'NIF EU','tipo'=>'1'],
        ];
        foreach ($tipos as $tipo) {
            TipoDocumentoIdentificativo::create($tipo);
        }
    }
}
