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
        Schema::create('tipo_direcciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 20);
        });

        DB::unprepared(<<<'SQL'
INSERT INTO `tipo_direcciones` (`id`, `nombre`) VALUES (185,'Domicilio'),
(186,'Facturación'),
(187,'Delegación');
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_direcciones');
    }
};
