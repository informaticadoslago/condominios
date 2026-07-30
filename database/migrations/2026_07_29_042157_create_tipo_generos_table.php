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
        Schema::create('tipo_generos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 10);
        });

        DB::unprepared(<<<'SQL'
INSERT INTO `tipo_generos` (`id`, `nombre`) VALUES (1,'Hombre'),
(2,'Mujer'),
(3,'Otro');
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_generos');
    }
};
