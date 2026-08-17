<?php

use App\Models\EntidadBancaria;
use App\Models\TipoComisionBancaria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El Q43 trae el mismo tipo de movimiento con un texto más corto que la CSV (campo
     * de ancho fijo: "COMIS.MANTENIM." en vez de "COMISIÓN MANTENIMIENTO"). "I.V.A."
     * es idéntico en los dos formatos, así que esa fila ya vale para ambos y no se
     * repite.
     */
    public function up(): void
    {
        $abanca = EntidadBancaria::where('codigo', '2080')->first();

        if (! $abanca) {
            return;
        }

        DB::table('tipos_movimiento_bancario')->insert([
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'COMIS.MANTENIM.', 'prefijo_descripcion' => null, 'codigo' => TipoComisionBancaria::MANTENIMIENTO, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'COMIS.ADMINIST.', 'prefijo_descripcion' => null, 'codigo' => TipoComisionBancaria::MANTENIMIENTO, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'GTOS LIQUI REME', 'prefijo_descripcion' => 'LIQ.', 'codigo' => TipoComisionBancaria::REMESA, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('tipos_movimiento_bancario')->whereIn('tipo_operacion', ['COMIS.MANTENIM.', 'COMIS.ADMINIST.', 'GTOS LIQUI REME'])->delete();
    }
};
