<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué es cada evento del calendario de un horario: examen, junta de evaluación,
     * claustro… Editable desde Gestión horaria (config/catalogos.php), pero el código se
     * refiere al examen por id (App\Models\TipoEvento::EXAMEN): es el que pide asignatura.
     */
    public function up(): void
    {
        Schema::create('tipo_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            $table->unsignedBigInteger('estado_id')->default(1)
                ->index('tipo_eventos_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_eventos')->insert([
            ['id' => 1, 'descripcion' => 'Examen', 'estado_id' => 1],
            ['id' => 2, 'descripcion' => 'Junta de evaluación', 'estado_id' => 1],
            ['id' => 3, 'descripcion' => 'Claustro de profesores', 'estado_id' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_eventos');
    }
};
