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
        Schema::create('tipo_cuenta_contables', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        DB::table('tipo_cuenta_contables')->insert([
            ['id' => 1, 'descripcion' => 'Activo'],
            ['id' => 2, 'descripcion' => 'Pasivo'],
            ['id' => 3, 'descripcion' => 'Patrimonio Neto'],
            ['id' => 4, 'descripcion' => 'Ingreso'],
            ['id' => 5, 'descripcion' => 'Gasto'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_cuenta_contables');
    }
};
