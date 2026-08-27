<?php

namespace App\Models;

use App\Support\HabilidadToken;
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

    /**
     * Habilidad que un token de API tiene que llevar para operar en esta empresa.
     *
     * El rol dice a qué empresas puede entrar el usuario; la habilidad, cuál de ellas
     * eligió al crear el token. Ningún token vale para dos empresas, ni siquiera los de
     * un usuario con rol global: si se filtra uno, solo se lleva esa.
     */
    public function habilidadToken(): string
    {
        return static::habilidadTokenPara($this->id);
    }

    public static function habilidadTokenPara(int $empresaContableId): string
    {
        return HabilidadToken::empresa($empresaContableId);
    }

    protected static function booted(): void
    {
        static::created(function (self $empresaContable) {
            Role::firstOrCreate(['name' => $empresaContable->nombreRol(), 'guard_name' => 'web']);

            // El plan de cuentas NO se copia aquí: quien crea la empresa es quien sabe
            // (si lo sabe) qué plantilla le corresponde -comunidad, sociedad, ninguna-, así
            // que es quien llama a CuentaContable::copiarPlanGlobalA() explícitamente.
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

    public function tiposComisionBancaria()
    {
        return $this->hasMany(TipoComisionBancaria::class);
    }
}
