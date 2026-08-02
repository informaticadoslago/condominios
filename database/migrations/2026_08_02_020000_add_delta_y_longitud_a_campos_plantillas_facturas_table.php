<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_plantillas_facturas', function (Blueprint $table) {
            $table->integer('delta_columna')->nullable()->after('texto_ancla');
            $table->integer('delta_lineas')->nullable()->after('delta_columna');
            $table->unsignedInteger('longitud_valor')->nullable()->after('delta_lineas');
        });
    }

    public function down(): void
    {
        Schema::table('campos_plantillas_facturas', function (Blueprint $table) {
            $table->dropColumn(['delta_columna', 'delta_lineas', 'longitud_valor']);
        });
    }
};
