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
            $table->string('iban', 34)->after('titular_id');
            $table->unsignedBigInteger('entidad_bancaria_id')->nullable()->after('iban')
                ->index('cuentas_bancarias_entidad_bancaria_id_foreign');
            $table->string('alias', 50)->nullable()->after('entidad_bancaria_id');

            $table->foreign('entidad_bancaria_id')->references('id')->on('entidades_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropForeign(['entidad_bancaria_id']);
            $table->dropColumn(['iban', 'entidad_bancaria_id', 'alias']);
        });
    }
};
