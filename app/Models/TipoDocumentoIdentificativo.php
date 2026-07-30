<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\Builder;

class TipoDocumentoIdentificativo extends Model
{

    public $timestamps = false;

    protected $table = 'tipo_documento_identificativos';

    const
    TIPO_FISICA = 1,
    TIPO_JURIDICA = 2;
    
    const    
    DOCUMENTO_NIF = 2,
    DOCUMENTO_NIE = 3,
    DOCUMENTO_PASAPORTE = 5,
    DOCUMENTO_CIF = 6,
    DOCUMENTO_NIFEU = 4;
    protected $fillable = [
        'nombre', // {cif:6, nif:2, nie:3, pasaporte:5, nifeu:4}
        'tipo', // 1=>física, 2=>jurídica
    ];



    // tipos_documento

    //nombres_documento

    static function isTipoDocumento($id, $tipo) : bool{
        $tipoDocumento = TipoDocumentoIdentificativo::find($id);
        return $tipoDocumento ? $tipoDocumento->tipo == $tipo : false;
    }

    /**
     * ÚNICO sitio de la regla "qué documentos se permiten según el país".
     * España: NIF/NIE/Pasaporte/CIF · resto de UE: NIF-EU/Pasaporte · resto: Pasaporte.
     */
    public static function idsPorPais(?int $paisId): array
    {
        // España tiene documentos propios (por id, no por grupo: va en UE).
        if ($paisId == Pais::ESPAÑA) {
            return [self::DOCUMENTO_NIF, self::DOCUMENTO_NIE, self::DOCUMENTO_PASAPORTE, self::DOCUMENTO_CIF];
        }

        $grupo = $paisId ? Pais::find($paisId)?->grupo : null;

        return match ($grupo) {
            Pais::GRUPO_UE => [self::DOCUMENTO_NIFEU, self::DOCUMENTO_PASAPORTE],
            default        => [self::DOCUMENTO_PASAPORTE],
        };
    }

    public function scopePorPais(Builder $query, ?int $paisId): void
    {
        $query->whereIn('id', self::idsPorPais($paisId));
    }

    public function scopePersona_fisica(Builder $query):void{
        $query->where('tipo', Self::TIPO_FISICA);
    }
    public function scopePersona_juridica(Builder $query):void{
        $query->where('tipo', Self::TIPO_JURIDICA);
    }
}
