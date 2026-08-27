<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Las cuentas maestra (y sus plantillas) se mudan a cuenta_contable_plantillas;
        // cuenta_contables pasa a llevar solo cuentas reales de una empresa contable, así
        // que esta columna ya no tiene ningún papel aquí.
        Schema::table('cuenta_contables', function (Blueprint $table) {
            $table->dropColumn('plantilla');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuenta_contables', function (Blueprint $table) {
            $table->string('plantilla', 30)->nullable()->after('codigo');
        });
    }
};
