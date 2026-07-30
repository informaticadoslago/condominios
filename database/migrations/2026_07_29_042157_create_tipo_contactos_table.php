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
        Schema::create('tipo_contactos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 20);
            $table->unsignedBigInteger('old_id')->nullable();
        });

        DB::unprepared(<<<'SQL'
INSERT INTO `tipo_contactos` (`id`, `nombre`, `old_id`) VALUES (1,'Móvil',188),
(2,'Teléfono',189),
(3,'Fax',191),
(4,'Email',190),
(5,'e-Boletin',675),
(6,'e-Facturación',287),
(7,'Recoger',192);
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_contactos');
    }
};
