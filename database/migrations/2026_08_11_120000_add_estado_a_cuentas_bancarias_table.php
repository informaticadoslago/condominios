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
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->unsignedBigInteger('estado_id')->default(1)->after('cuenta_contable')->index('cuentas_bancarias_estado_id_foreign');

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropForeign('cuentas_bancarias_estado_id_foreign');
            $table->dropColumn('estado_id');
        });
    }
};
