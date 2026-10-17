<?php

namespace Database\Seeders;

use App\Models\FormaDePago;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormasDePagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ids fijos (los de las constantes de FormaDePago) e insertOrIgnore: Compensación
        // ya la ha creado su propia migración.
        DB::table('formas_de_pago')->insertOrIgnore([
            ['id' => FormaDePago::RECIBO_BANCARIO, 'descripcion' => 'Recibo bancario', 'estado_id' => 1],
            ['id' => FormaDePago::EFECTIVO, 'descripcion' => 'Efectivo', 'estado_id' => 2],
            ['id' => FormaDePago::TRANSFERENCIA, 'descripcion' => 'Transferencia', 'estado_id' => 1],
            ['id' => FormaDePago::COMPENSACION, 'descripcion' => 'Compensación', 'estado_id' => 1],
        ]);

        $this->command->info('Formas de pago cargadas correctamente: 2 registros.');
    }
}
