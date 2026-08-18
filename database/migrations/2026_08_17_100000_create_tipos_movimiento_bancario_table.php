<?php

use App\Models\EntidadBancaria;
use App\Models\TipoComisionBancaria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con qué texto identifica cada banco, en sus extractos, un tipo de cargo que nos
     * interesa. Es del banco, no de la empresa: dos empresas que lleven cuenta en el
     * mismo banco ven el mismo texto, por eso cuelga de entidad_bancaria_id y no de
     * empresa_contable_id (esa relación ya la tiene tipo_comisiones_bancarias, eje
     * distinto).
     *
     * Un tipo puede tener más de un texto (aquí, remesa tiene dos: el gasto y su IVA
     * van en líneas separadas del extracto). `prefijo_descripcion` es solo para los
     * casos donde el mismo TIPO OPERACIÓN sirve tanto para la liquidación normal como
     * para la devolución (en ABANCA, "GASTOS LIQUIDACIÓN REMESAS" e "I.V.A."): sin ese
     * prefijo no hay forma de distinguirlas por la columna sola.
     */
    public function up(): void
    {
        Schema::create('tipos_movimiento_bancario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_bancaria_id')->index('tipos_movimiento_bancario_entidad_bancaria_id_foreign');
            $table->string('tipo_operacion', 60);
            $table->string('prefijo_descripcion', 20)->nullable();
            $table->string('codigo', 20);
            $table->timestamps();

            $table->unique(['entidad_bancaria_id', 'tipo_operacion', 'prefijo_descripcion'], 'tipos_movimiento_bancario_unico');

            $table->foreign('entidad_bancaria_id')->references('id')->on('entidades_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });

        $abanca = EntidadBancaria::where('codigo', '2080')->first();

        if (! $abanca) {
            return;
        }

        DB::table('tipos_movimiento_bancario')->insert([
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'COMISIÓN MANTENIMIENTO', 'prefijo_descripcion' => null, 'codigo' => TipoComisionBancaria::MANTENIMIENTO, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'COMISIÓN ADMINISTRACIÓN', 'prefijo_descripcion' => null, 'codigo' => TipoComisionBancaria::MANTENIMIENTO, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'GASTOS LIQUIDACIÓN REMESAS', 'prefijo_descripcion' => 'LIQ.', 'codigo' => TipoComisionBancaria::REMESA, 'created_at' => now(), 'updated_at' => now()],
            ['entidad_bancaria_id' => $abanca->id, 'tipo_operacion' => 'I.V.A.', 'prefijo_descripcion' => 'LIQ.', 'codigo' => TipoComisionBancaria::REMESA, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_movimiento_bancario');
    }
};
