<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            // Un pago puede sumar más que los recibos que cubre: el sobrante no es de
            // ninguno de ellos —un recibo no se paga por más de lo que vale—, así que es
            // un cobro sin recibo. recibo_id pasa a admitir null.
            $table->unsignedBigInteger('recibo_id')->nullable()->change();

            // A qué propietario abonar el sobrante. Solo se rellena cuando recibo_id es
            // null: con recibo, la cuenta del propietario ya se llega a través de él.
            $table->unsignedBigInteger('propietario_id')->nullable()->after('recibo_id')
                ->index('cobros_propietario_id_foreign');
            $table->foreign('propietario_id')->references('id')->on('propietarios')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropForeign(['propietario_id']);
            $table->dropIndex('cobros_propietario_id_foreign');
            $table->dropColumn('propietario_id');

            $table->unsignedBigInteger('recibo_id')->nullable(false)->change();
        });
    }
};
