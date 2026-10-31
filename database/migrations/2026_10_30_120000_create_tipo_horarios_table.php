<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un horario es de alumno o de profesor. Editable desde Maestros (config/catalogos.php),
     * pero el código se refiere a estas dos filas por id (constantes de App\Models\TipoHorario).
     */
    public function up(): void
    {
        Schema::create('tipo_horarios', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            $table->unsignedBigInteger('estado_id')->default(1)
                ->index('tipo_horarios_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_horarios')->insert([
            ['id' => 1, 'descripcion' => 'Alumno', 'estado_id' => 1],
            ['id' => 2, 'descripcion' => 'Profesor', 'estado_id' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_horarios');
    }
};
