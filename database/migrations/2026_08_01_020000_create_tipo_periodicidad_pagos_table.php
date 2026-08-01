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
        Schema::create('tipo_periodicidad_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            $table->unsignedTinyInteger('meses');
            $table->unsignedBigInteger('estado_id')->default(1)->index('tipo_periodicidad_pagos_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_periodicidad_pagos')->insert([
            ['id' => 1, 'descripcion' => 'Mensual', 'meses' => 1, 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'descripcion' => 'Bimestral', 'meses' => 2, 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'descripcion' => 'Trimestral', 'meses' => 3, 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'descripcion' => 'Semestral', 'meses' => 6, 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'descripcion' => 'Anual', 'meses' => 12, 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_periodicidad_pagos');
    }
};
