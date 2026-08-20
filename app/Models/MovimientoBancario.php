<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Línea en bruto del extracto del banco (CSV o Q43), sin clasificar. */
class MovimientoBancario extends Model
{
    protected $table = 'movimientos_bancarios';

    protected $fillable = [
        'cuenta_bancaria_id',
        'fecha_valor',
        'fecha_contable',
        'fecha_operacion',
        'tipo_operacion',
        'descripcion',
        'referencia',
        'importe',
        'saldo',
        'divisa',
        'hash',
    ];

    protected $casts = [
        'fecha_valor'     => 'date',
        'fecha_contable'  => 'date',
        'fecha_operacion' => 'date',
    ];

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    /**
     * El tipo del catálogo (tipos_movimiento_bancario) que casa con este movimiento,
     * misma regla que ClasificarComisionesDesdeMovimientos: mismo TIPO OPERACIÓN, y si
     * el tipo trae prefijo, que la descripción empiece por él. Null si no hay ninguno
     * dado de alta todavía para ese texto.
     */
    public function tipoCatalogado(): ?TipoMovimientoBancario
    {
        if (! $this->cuentaBancaria->entidad_bancaria_id) {
            return null;
        }

        return TipoMovimientoBancario::where('entidad_bancaria_id', $this->cuentaBancaria->entidad_bancaria_id)
            ->where('tipo_operacion', $this->tipo_operacion)
            ->get()
            ->first(fn (TipoMovimientoBancario $t) => $t->prefijo_descripcion === null
                || Str::startsWith(trim($this->descripcion ?? ''), $t->prefijo_descripcion));
    }
}
