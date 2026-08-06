<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Models\Role;

class Comunidad extends Model
{
    protected $table = 'comunidades';

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = [
        'persona_id',
        'estado_id',
        'identificador_acreedor_sepa',
        'sufijo',
        'empresa_contable_id',
    ];

    protected $with = ['persona'];

    /** Nombre del rol de acceso a esta comunidad (puerta de entrada, no permisos). */
    public function nombreRol(): string
    {
        return 'comunidad-'.$this->id;
    }

    protected static function booted(): void
    {
        static::created(function (self $comunidad) {
            Role::firstOrCreate(['name' => $comunidad->nombreRol(), 'guard_name' => 'web']);
        });
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActiva($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }

    public function getNombreAttribute()
    {
        return $this->persona?->razon_social;
    }

    public function getCifAttribute()
    {
        return $this->persona?->documento_identificativo;
    }

    /**
     * Identificador de acreedor SEPA a partir del NIF y el sufijo: `ES` + 2 dígitos de
     * control + sufijo + NIF. Para H27747039 con sufijo 000 sale ES92000H27747039.
     *
     * Los dígitos de control se calculan SOLO con el NIF —el sufijo no entra en la
     * cuenta—, con el mismo mod 97 del IBAN. Por eso el sufijo se puede cambiar (sirve
     * para separar remesas de un mismo acreedor) sin que el control cambie.
     */
    public static function calcularIdentificadorAcreedor(string $nif, string $sufijo = '000'): string
    {
        $nif    = strtoupper(preg_replace('/[\s-]+/', '', trim($nif)) ?? '');
        $sufijo = str_pad(substr(trim($sufijo) ?: '000', 0, 3), 3, '0', STR_PAD_LEFT);

        // Letras a números (A=10 … Z=35) sobre el NIF con 'ES00' al final.
        $numerico = '';
        foreach (str_split($nif.'ES00') as $caracter) {
            $numerico .= ctype_alpha($caracter) ? (string) (ord($caracter) - 55) : $caracter;
        }

        $control = 98 - (int) bcmod($numerico, '97');

        return 'ES'.str_pad((string) $control, 2, '0', STR_PAD_LEFT).$sufijo.$nif;
    }

    public function inmuebles()
    {
        return $this->hasMany(Inmueble::class);
    }

    public function directivos()
    {
        return $this->hasMany(ComunidadDirectivo::class);
    }

    public function gruposDeReparto()
    {
        return $this->hasMany(GrupoDeReparto::class);
    }

    public function presupuestos()
    {
        return $this->hasMany(Presupuesto::class);
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }
}
