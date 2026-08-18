<?php

use App\Models\EntidadBancaria;
use App\Models\TipoComisionBancaria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ABANCA reutiliza el mismo tipo de operación de liquidar una remesa (GASTOS
     * LIQUIDACIÓN REMESAS en CSV, GTOS LIQUI REME en Q43, e I.V.A. en los dos formatos)
     * para la comisión de devolución: solo cambia el prefijo de la descripción, "DEV."
     * en vez de "LIQ." (visto tanto en el Q43 GE080701.TXT como en el CSV
     * MOVIMIENTOS_ES6020805025353040030509.csv, líneas "DEV. REM. 31-07-2026 FRA
     * BI/IVA ..."). Sin estas filas esas líneas caían en descartadas por no empezar por
     * "LIQ.".
     */
    public function up(): void
    {
        $abanca = EntidadBancaria::where('codigo', '2080')->first();

        if (! $abanca) {
            return;
        }

        DB::table('tipos_movimiento_bancario')->insert([
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'GASTOS LIQUIDACIÓN REMESAS', 'prefijo_descripcion' => 'DEV.', 'codigo' => TipoComisionBancaria::DEVOLUCION, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'GTOS LIQUI REME', 'prefijo_descripcion' => 'DEV.', 'codigo' => TipoComisionBancaria::DEVOLUCION, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'I.V.A.', 'prefijo_descripcion' => 'DEV.', 'codigo' => TipoComisionBancaria::DEVOLUCION, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('tipos_movimiento_bancario')
            ->where('prefijo_descripcion', 'DEV.')
            ->where('codigo', TipoComisionBancaria::DEVOLUCION)
            ->delete();
    }
};
