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
        Schema::table('inmueble_grupo_de_reparto', function (Blueprint $table) {
            $table->decimal('coeficiente', 5, 2)->nullable()->after('inmueble_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inmueble_grupo_de_reparto', function (Blueprint $table) {
            $table->dropColumn('coeficiente');
        });
    }
};
