<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fila sin uso: ningún modelo que use Estado::class (genérico, Activo/Inactivo)
        // referencia el id 3. Los usuarios tienen su propio catálogo (estado_usuarios),
        // separado de este desde el principio.
        DB::table('estados')->where('id', 3)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('estados')->insert(['id' => 3, 'descripcion' => 'Inicial']);
    }
};
