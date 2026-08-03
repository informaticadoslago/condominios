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
            // Titular REAL de la cuenta (quien firma): normalmente es la propia persona
            // del titular_type/titular_id (Propietario, Proveedor…), pero cuando ese
            // titular es un propietario menor de edad, aquí va otra persona adulta —
            // los menores no tienen firma. Ver App\Livewire\Propietarios\Crear\Steps\CuentaBancariaStep.
            $table->unsignedBigInteger('persona_comunidad_id')->nullable()->after('titular_id')
                ->index('cuentas_bancarias_persona_comunidad_id_foreign');

            $table->foreign('persona_comunidad_id')->references('id')->on('personas_comunidad')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropForeign(['persona_comunidad_id']);
            $table->dropColumn('persona_comunidad_id');
        });
    }
};
