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
        Schema::create('tipo_estado_presupuestos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        DB::table('tipo_estado_presupuestos')->insert([
            ['id' => 1, 'descripcion' => 'Provisional'],
            ['id' => 2, 'descripcion' => 'Presentado'],
            ['id' => 3, 'descripcion' => 'Aprobado'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_estado_presupuestos');
    }
};
