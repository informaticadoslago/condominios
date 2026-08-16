<?php

use App\Models\Comunidad;
use App\Models\EmpresaContable;
use App\Models\TipoComisionBancaria;
use App\Services\ComisionesBancarias\AsegurarTiposComisionBancaria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La pantalla de comisiones bancarias pasa a cubrir también el mantenimiento y
     * administración de cuenta (mismo formulario, mismas líneas): lo único que cambia es
     * a qué cuenta de gasto va y si lleva remesa asociada.
     *
     * `tipo_comisiones_bancarias` no es un catálogo global de dos filas fijas: cada
     * empresa contable tiene las suyas, con su propia cuenta_contable_id, porque cada
     * empresa tiene su propio plan de cuentas (importado o no) y el código puede no
     * coincidir con el de la plantilla. `codigo` ('remesa'/'mantenimiento') es lo que no
     * cambia de una empresa a otra, y es por lo que se busca la fila.
     *
     * A las empresas que ya existían se les da de alta aquí mismo (ver
     * AsegurarTiposComisionBancaria, el mismo servicio que se llama al enlazar una
     * comunidad nueva). Las filas de comisiones_bancarias que ya hubiera se enlazan con
     * la de remesa de su empresa; si no se sabe a qué empresa pertenecen, se quedan sin
     * asignar en vez de suponerlo.
     */
    public function up(): void
    {
        Schema::create('tipo_comisiones_bancarias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_contable_id')->index('tipo_comisiones_bancarias_empresa_contable_id_foreign');
            $table->string('codigo', 20);
            $table->string('descripcion', 60);
            $table->unsignedBigInteger('cuenta_contable_id')->nullable()->index('tipo_comisiones_bancarias_cuenta_contable_id_foreign');
            $table->timestamps();

            $table->unique(['empresa_contable_id', 'codigo']);

            $table->foreign('empresa_contable_id')->references('id')->on('empresas_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_contable_id')->references('id')->on('cuenta_contables')->onUpdate('restrict')->onDelete('restrict');
        });

        Schema::table('comisiones_bancarias', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_comision_bancaria_id')->nullable()
                ->after('remesa_id')->index('comisiones_bancarias_tipo_comision_bancaria_id_foreign');

            $table->foreign('tipo_comision_bancaria_id')->references('id')->on('tipo_comisiones_bancarias')
                ->onUpdate('restrict')->onDelete('restrict');
        });

        $servicio = new AsegurarTiposComisionBancaria();

        foreach (EmpresaContable::all() as $empresa) {
            $servicio->ejecutar($empresa);

            $remesa = TipoComisionBancaria::where('empresa_contable_id', $empresa->id)
                ->where('codigo', TipoComisionBancaria::REMESA)
                ->first();

            $cuentasBancarias = DB::table('cuentas_bancarias')
                ->join('comunidades', function ($join) {
                    $join->on('comunidades.id', '=', 'cuentas_bancarias.titular_id')
                        ->where('cuentas_bancarias.titular_type', Comunidad::class);
                })
                ->where('comunidades.empresa_contable_id', $empresa->id)
                ->pluck('cuentas_bancarias.id');

            if ($cuentasBancarias->isNotEmpty()) {
                DB::table('comisiones_bancarias')
                    ->whereIn('cuenta_bancaria_id', $cuentasBancarias)
                    ->update(['tipo_comision_bancaria_id' => $remesa->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('comisiones_bancarias', function (Blueprint $table) {
            $table->dropForeign('comisiones_bancarias_tipo_comision_bancaria_id_foreign');
            $table->dropColumn('tipo_comision_bancaria_id');
        });

        Schema::dropIfExists('tipo_comisiones_bancarias');
    }
};
