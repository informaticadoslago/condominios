<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PersonaComunidad extends Model
{
    protected $table = 'personas_comunidad';

    protected $fillable = [
        'comunidad_id',
        'nombre', 'apellido1', 'apellido2',
        'razon_social', 'nombre_comercial',
        'documento_pais_id', 'tipo_documento_id', 'documento_identificativo',
        'fecha_nacimiento', 'comentarios',
        'genero_id', 'nacionalidad_id',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function propietario()
    {
        return $this->hasOne(Propietario::class, 'persona_comunidad_id');
    }

    public function proveedor()
    {
        return $this->morphOne(Proveedor::class, 'persona');
    }

    public function tipoDocumentoIdentificativo()
    {
        return $this->belongsTo(TipoDocumentoIdentificativo::class, 'tipo_documento_id');
    }

    public function paisDocumentoIdentificativo()
    {
        return $this->belongsTo(Pais::class, 'documento_pais_id');
    }

    public function nacionalidad()
    {
        return $this->belongsTo(Pais::class, 'nacionalidad_id');
    }

    public function direcciones()
    {
        return $this->morphMany(Direccion::class, 'direccionable');
    }

    public function domicilio()
    {
        return $this->morphOne(Direccion::class, 'direccionable')
            ->where('tipo_direccion_id', TipoDireccion::idDomicilio());
    }

    public function contactos()
    {
        return $this->morphMany(Contacto::class, 'contactable');
    }

    private function getNombreApellidos($orden = 1)
    {
        $nombre    = $this->nombre;
        $apellido1 = $this->apellido1;
        $apellido2 = $this->apellido2 ?? '';

        return $orden == 1
            ? trim("$nombre $apellido1 $apellido2")
            : trim("$apellido1 $apellido2").", $nombre";
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(function () {
            if ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_CIF) {
                return $this->razon_social;
            }

            $orden = config('settings.nombrecompleto.apellidosnombre', env('LIST_NOMBRECOMPLETO', 1));

            return $this->getNombreApellidos($orden);
        });
    }

    public function scopeMayorDeEdad($query)
    {
        return $query->where('fecha_nacimiento', '<=', now()->subYears(18)->toDateString());
    }

    public function scopeBuscarNombreCompleto($q, ?string $search)
    {
        if (! $search) {
            return $q;
        }

        $terms = preg_split('/\s+/', trim($search));
        foreach ($terms as $term) {
            $q->where(function ($q2) use ($term) {
                $q2->orWhere('nombre', 'like', "%{$term}%")
                    ->orWhere('apellido1', 'like', "%{$term}%")
                    ->orWhere('apellido2', 'like', "%{$term}%");
            });
        }

        return $q;
    }
}
