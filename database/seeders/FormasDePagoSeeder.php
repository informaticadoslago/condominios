<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormasDePagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('formas_de_pago')->insert([
            ['descripcion' => 'Recibo bancario', 'estado_id' => 1],
            ['descripcion' => 'Efectivo', 'estado_id' => 2],
            ['descripcion' => 'Transferencia', 'estado_id' => 1],
        ]);

        $this->command->info('Formas de pago cargadas correctamente: 2 registros.');
    }
}
