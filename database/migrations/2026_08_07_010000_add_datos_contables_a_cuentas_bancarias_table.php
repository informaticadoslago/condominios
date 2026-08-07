<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            // Nombre con el que la cuenta figura en el plan contable ("BANCO X C/C"). Lo
            // escribe quien lleva la comunidad en sus datos financieros, porque es él
            // quien decide cómo quiere leerlo en el mayor.
            $table->string('nombre_contable', 150)->nullable()->after('alias');

            // Subcuenta de tesorería (572xxxxx) que la contabilidad estrena con ese
            // nombre. Sin FK a propósito: referencia opaca a un módulo que no conoce a la
            // gestión, igual que la cuenta del propietario. Nulo = la comunidad todavía
            // no lleva contabilidad, que es el caso de las que solo se administran.
            $table->char('cuenta_contable', 8)->nullable()->after('nombre_contable');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropColumn(['nombre_contable', 'cuenta_contable']);
        });
    }
};
