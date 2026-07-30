<?php

namespace App\Models\Traits;

use App\Models\Documento;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * Documentos adjuntos de un modelo (Alumno, Profesor, Socio, Empresa…). Lo único
 * que hace falta para que una entidad tenga documentos es usar este trait.
 */
trait ConDocumentos
{
    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    /** Sube el fichero al disco de documentos y lo cuelga de este modelo. */
    public function adjuntarDocumento(UploadedFile $fichero, int $tipoDocumentoId, ?string $descripcion = null): Documento
    {
        return $this->documentos()->create(
            Documento::subirFichero($fichero) + [
                'tipo_documento_id' => $tipoDocumentoId,
                'descripcion'       => $descripcion,
            ]
        );
    }

    /** Cuelga de este modelo un fichero ya subido (alta a medias: estaba en borradores). */
    public function adjuntarDocumentoSubido(array $metadatos): Documento
    {
        $metadatos = Documento::consolidarFichero($metadatos);

        return $this->documentos()->create([
            'tipo_documento_id' => $metadatos['tipo_documento_id'],
            'descripcion'       => $metadatos['descripcion'] ?? null,
            'nombrefichero'     => $metadatos['nombrefichero'],
            'nombrelocal'       => $metadatos['nombrelocal'] ?? null,
            'camino'            => $metadatos['camino'] ?? '',
            'extension'         => $metadatos['extension'] ?? null,
            'size'              => $metadatos['size'] ?? null,
        ]);
    }
}
