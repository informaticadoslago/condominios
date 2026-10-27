<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tipo de los documentos que se cuelgan de una convocatoria de junta (cuentas,
     * presupuestos…) para mandarlos con ella por correo.
     */
    public function up(): void
    {
        DB::table('tipo_documentos')->insert([
            'id'     => 11,
            'nombre' => 'Convocatoria de junta',
        ]);
    }

    public function down(): void
    {
        DB::table('tipo_documentos')->where('id', 11)->delete();
    }
};
