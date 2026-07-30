<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use ConDocumentos;

    protected $fillable = [
        'fechaalta',
        'persona_id',
        'direccion',
        'telefono',
        'email',
        'iban',
        'comentarios',
        'estado_id',
        'fechabaja',
    ];

    protected $casts = [
        'fechaalta' => 'date:d-m-Y',
        'fechabaja' => 'date:d-m-Y',
    ];

    protected $hidden = ['estado']; // T-L9-L12: Eliminar cuando se borre la columna de la BD

    protected $with = ['persona'];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function estado()
    {
        return $this->belongsTo(EstadoEmpresa::class, 'estado_id');
    }

    // T-L9-L12: Eliminar cuando se borre la columna de la BD
    // Forzar que $empresa->estado siempre devuelva la relación
    public function getEstadoAttribute()
    {
        if ($this->relationLoaded('estado')) {
            return $this->getRelation('estado');
        }

        return $this->getRelationValue('estado');
    }

    // T-L9-L12: Eliminar cuando se borre la columna de la BD
    public function getEstadoObsoletoAttribute()
    {
        return $this->attributes['estado'];
    }

    public function cuentasacreedor()
    {
        return $this->hasMany(EmpresaAcreedor::class);
    }

    public function scopeActiva($query)
    {
        return $query->where('empresas.estado_id', EstadoEmpresa::EMPRESA_ACTIVO);
    }

    public function getNombreCompletoAttribute()
    {
        return $this->persona->nombreCompleto ?? '';
    }
}
