<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_documento_identificativos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 10);
            $table->tinyInteger('tipo')->default(1);
        });

        DB::unprepared(<<<'SQL'
INSERT INTO `tipo_documento_identificativos` (`id`, `nombre`, `tipo`) VALUES (1,'ERRONEO',1),
(2,'NIF',1),
(3,'NIE',1),
(4,'NIF EU',1),
(5,'Pasaporte',1),
(6,'CIF',2);
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_documento_identificativos');
    }
};
