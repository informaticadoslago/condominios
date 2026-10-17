<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El puesto de cada directivo deja de ser texto libre y pasa a ser del catálogo
     * tipo_directivo_comunidades. Las filas que hubiera se conservan: cada texto
     * distinto se busca en el catálogo por su descripción y, si no está, se añade.
     * La FK va al final: MySQL no deja cambiar a NOT NULL una columna que ya la lleva.
     */
    public function up(): void
    {
        Schema::table('comunidad_directivos', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_directivo_comunidad_id')->nullable()->after('persona_comunidad_id');
        });

        foreach (DB::table('comunidad_directivos')->distinct()->pluck('puesto') as $puesto) {
            $descripcion = mb_substr(trim($puesto), 0, 50);
            $tipoId = DB::table('tipo_directivo_comunidades')->where('descripcion', $descripcion)->value('id')
                ?? DB::table('tipo_directivo_comunidades')->insertGetId([
                    'descripcion' => $descripcion,
                    'estado_id'   => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

            DB::table('comunidad_directivos')->where('puesto', $puesto)->update(['tipo_directivo_comunidad_id' => $tipoId]);
        }

        Schema::table('comunidad_directivos', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_directivo_comunidad_id')->nullable(false)->change();
            $table->index('tipo_directivo_comunidad_id', 'comunidad_directivos_tipo_directivo_comunidad_id_foreign');
            $table->foreign('tipo_directivo_comunidad_id')->references('id')->on('tipo_directivo_comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->dropColumn('puesto');
        });
    }

    public function down(): void
    {
        Schema::table('comunidad_directivos', function (Blueprint $table) {
            $table->string('puesto', 100)->default('')->after('persona_comunidad_id');
        });

        DB::table('comunidad_directivos')
            ->join('tipo_directivo_comunidades', 'tipo_directivo_comunidades.id', '=', 'comunidad_directivos.tipo_directivo_comunidad_id')
            ->update(['comunidad_directivos.puesto' => DB::raw('tipo_directivo_comunidades.descripcion')]);

        Schema::table('comunidad_directivos', function (Blueprint $table) {
            $table->dropForeign(['tipo_directivo_comunidad_id']);
            $table->dropIndex('comunidad_directivos_tipo_directivo_comunidad_id_foreign');
            $table->dropColumn('tipo_directivo_comunidad_id');
        });
    }
};
