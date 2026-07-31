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
        Schema::create('tipo_ocupaciones', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        DB::table('tipo_ocupaciones')->insert([
            ['id' => 1, 'descripcion' => 'Alquilado'],
            ['id' => 2, 'descripcion' => 'Propietario'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_ocupaciones');
    }
};
