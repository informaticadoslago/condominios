<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            // Los horarios que ya existen son de alumno (1).
            $table->unsignedBigInteger('tipo_horario_id')->default(1)->after('nombre')->index('horarios_tipo_horario_id_foreign');

            $table->foreign('tipo_horario_id')->references('id')->on('tipo_horarios')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropForeign(['tipo_horario_id']);
            $table->dropIndex('horarios_tipo_horario_id_foreign');
            $table->dropColumn('tipo_horario_id');
        });
    }
};
