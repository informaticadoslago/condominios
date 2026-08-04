<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsientoContable extends Model
{
    protected $table = 'asiento_contables';

    protected $fillable = [
        'ejercicio_contable_id', 'numero', 'fecha', 'concepto',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function ejercicioContable()
    {
        return $this->belongsTo(EjercicioContable::class);
    }

    public function apuntesContables()
    {
        return $this->hasMany(ApunteContable::class);
    }

    /** Cuentas de las líneas del Debe (normalmente una sola). No hace consultas si apuntesContables.cuentaContable ya viene cargado. */
    public function cuentasDebe()
    {
        return $this->apuntesContables->where('debe', '>', 0)->pluck('cuentaContable');
    }

    /** Cuentas de las líneas del Haber (normalmente una sola). No hace consultas si apuntesContables.cuentaContable ya viene cargado. */
    public function cuentasHaber()
    {
        return $this->apuntesContables->where('haber', '>', 0)->pluck('cuentaContable');
    }
}
