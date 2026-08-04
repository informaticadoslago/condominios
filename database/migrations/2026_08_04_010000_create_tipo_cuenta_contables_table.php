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
            $table->unsignedBigInteger('estado_id')->default(1)->index('tipo_cuenta_contables_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_cuenta_contables')->insert([
            ['id' => 1, 'descripcion' => 'Activo', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'descripcion' => 'Pasivo', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'descripcion' => 'Patrimonio Neto', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'descripcion' => 'Ingreso', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'descripcion' => 'Gasto', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
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
