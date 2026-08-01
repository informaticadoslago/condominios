<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La migración create_tipo_periodicidad_pagos_table se editó para incluir
     * estado_id+timestamps, pero eso solo sirve para instalaciones nuevas: en las que
     * ya la habían ejecutado (con la versión sin esas columnas) Laravel no la repite.
     * Esta migración pone al día esas instalaciones.
     */
    public function up(): void
    {
        if (Schema::hasColumn('tipo_periodicidad_pagos', 'estado_id')) {
            return;
        }

        Schema::table('tipo_periodicidad_pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('estado_id')->default(1)->after('meses')
                ->index('tipo_periodicidad_pagos_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipo_periodicidad_pagos', function (Blueprint $table) {
            $table->dropForeign(['estado_id']);
            $table->dropColumn(['estado_id', 'created_at', 'updated_at']);
        });
    }
};
