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
        Schema::create('tipo_documentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 50);
        });

        DB::unprepared(<<<'SQL'
INSERT INTO `tipo_documentos` (`id`, `nombre`) VALUES (1,'Otros'),
(2,'DNI / NIE'),
(3,'Libro de familia'),
(4,'Matrícula'),
(5,'Domiciliación bancaria'),
(6,'Titulación'),
(7,'Certificado'),
(8,'Plantilla diploma');
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_documentos');
    }
};
