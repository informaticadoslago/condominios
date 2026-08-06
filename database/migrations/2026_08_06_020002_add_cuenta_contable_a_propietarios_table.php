<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            // Su subcuenta de cliente en la contabilidad de la comunidad (43000001…),
            // tal y como la devuelve la contabilidad al darlo de alta. Texto opaco, no
            // una FK: aquí no entra ningún id de las tablas contables.
            $table->char('cuenta_contable', 8)->nullable()->after('estado_id');
        });
    }

    public function down(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            $table->dropColumn('cuenta_contable');
        });
    }
};
