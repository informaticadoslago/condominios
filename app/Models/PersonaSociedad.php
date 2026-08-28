<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PersonaSociedad extends Model
{
    protected $table = 'personas_sociedad';

    protected $fillable = [
        'sociedad_id',
        'nombre', 'apellido1', 'apellido2',
        'razon_social', 'nombre_comercial',
        'documento_pais_id', 'tipo_documento_id', 'documento_identificativo',
        'fecha_nacimiento', 'comentarios',
        'genero_id', 'nacionalidad_id',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function sociedad()
    {
        return $this->belongsTo(Sociedad::class);
    }

    public function cliente()
    {
        return $this->hasOne(SociedadCliente::class);
    }

    public function proveedor()
    {
        return $this->morphOne(Proveedor::class, 'persona');
    }

    public function trabajador()
    {
        return $this->hasOne(SociedadTrabajador::class);
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
