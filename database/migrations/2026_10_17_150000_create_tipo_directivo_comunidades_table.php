<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Puestos de la directiva de una comunidad (presidente, secretario...). Catálogo
     * común a todas las comunidades, editable desde Maestros (config/catalogos.php).
     */
    public function up(): void
    {
        Schema::create('tipo_directivo_comunidades', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            $table->unsignedBigInteger('estado_id')->default(1)
                ->index('tipo_directivo_comunidades_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_directivo_comunidades')->insert([
            ['id' => 1, 'descripcion' => 'Presidente', 'estado_id' => 1],
            ['id' => 2, 'descripcion' => 'Vicepresidente', 'estado_id' => 1],
            ['id' => 3, 'descripcion' => 'Secretario', 'estado_id' => 1],
            ['id' => 4, 'descripcion' => 'Tesorero', 'estado_id' => 1],
            ['id' => 5, 'descripcion' => 'Vocal', 'estado_id' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_directivo_comunidades');
    }
};
