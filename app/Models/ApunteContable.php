<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ApunteContable extends Model
{
    protected $table = 'apunte_contables';

    protected $fillable = [
        'asiento_contable_id', 'cuenta_contable_id', 'debe', 'haber', 'concepto',
    ];

    // 'debe' y 'haber' son céntimos enteros, y se quedan así en todo el modelo: los
    // agregados de SQL (withSum) se saltan los casts de Eloquent, y si el atributo
    // devolviera euros tendríamos el mismo nombre con dos unidades según por dónde
    // se lea. La conversión ocurre solo al presentar, con los accessors de abajo.
    protected $casts = [
        'debe'  => 'integer',
        'haber' => 'integer',
    ];

    protected function debeEuros(): Attribute
    {
        return Attribute::get(fn (): float => $this->debe / 100);
    }

    protected function haberEuros(): Attribute
    {
        return Attribute::get(fn (): float => $this->haber / 100);
    }

    public function asientoContable()
    {
        return $this->belongsTo(AsientoContable::class);
    }

    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class);
    }
}
