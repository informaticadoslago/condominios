<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una junta de propietarios es ordinaria o extraordinaria. Catálogo fijo: el código
     * se refiere a las filas por id (constantes de App\Models\TipoJunta).
     */
    public function up(): void
    {
        Schema::create('tipo_juntas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        DB::table('tipo_juntas')->insert([
            ['id' => 1, 'descripcion' => 'Ordinaria'],
            ['id' => 2, 'descripcion' => 'Extraordinaria'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_juntas');
    }
};
