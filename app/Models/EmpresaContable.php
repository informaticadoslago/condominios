<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EmpresaContable extends Model
{
    protected $table = 'empresas_contables';

    protected $fillable = ['cif', 'razon_social'];

    /** Nombre del rol de acceso a esta empresa contable (puerta de entrada, no permisos). */
    public function nombreRol(): string
    {
        return 'empresa-contable-'.$this->id;
    }

    protected static function booted(): void
    {
        static::created(function (self $empresaContable) {
            Role::firstOrCreate(['name' => $empresaContable->nombreRol(), 'guard_name' => 'web']);
        });
    }

    public function cuentaContables()
    {
        return $this->hasMany(CuentaContable::class);
    }

    public function ejercicioContables()
    {
        return $this->hasMany(EjercicioContable::class);
    }
}
