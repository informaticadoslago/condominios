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
        Schema::create('tipo_inmuebles', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        DB::table('tipo_inmuebles')->insert([
            ['id' => 1, 'descripcion' => 'Piso'],
            ['id' => 2, 'descripcion' => 'Garaje'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_inmuebles');
    }
};
