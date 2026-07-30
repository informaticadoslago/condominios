<?php

namespace App\Support\Pdf;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Rellena un PDF con formulario (AcroForm) usando el binario pdftk.
 *
 * La gracia frente a L9: aquí no se dispara a ciegas un array gigante de campos
 * contra la plantilla. Primero se le PREGUNTA al PDF qué campos tiene
 * (camposDisponibles) y solo se le mandan esos, de modo que cada plantilla
 * (ACS, EMU, la del año que viene) se rellena con lo que sabe recibir y lo demás
 * se ignora sin ensuciar nada. Quien construye los datos puede además consultar
 * la lista para decidir (ver FichaMatriculacion y las casillas de asignaturas).
 *
 * Los datos se le pasan a pdftk en XFDF (XML, UTF-8): a diferencia del FDF, no
 * hay que escapar a mano ni convertir acentos a UTF-16.
 */
class FormularioPdf
{
    private array $campos;

    private function __construct(private string $pdf) {}

    /** @param string $pdf contenido binario de la plantilla */
    public static function desdeContenido(string $pdf): self
    {
        return new self($pdf);
    }

    /** Nombres de los campos del formulario, tal como los declara la plantilla. */
    public function camposDisponibles(): array
    {
        if (isset($this->campos)) {
            return $this->campos;
        }

        $plantilla = $this->aFicheroTemporal($this->pdf);

        try {
            $volcado = $this->pdftk([$plantilla, 'dump_data_fields_utf8']);
        } finally {
            unlink($plantilla);
        }

        preg_match_all('/^FieldName: (.*)$/m', $volcado, $encontrados);

        return $this->campos = array_values(array_unique(array_map('trim', $encontrados[1])));
    }

    /**
     * Devuelve el PDF relleno. De $datos solo se usa lo que la plantilla conoce.
     *
     * Se aplana (flatten): la ficha se imprime y se firma, no se sigue editando.
     * need_appearances va antes para que pdftk pinte el texto de los campos que la
     * plantilla dejó sin apariencia; sin él, el aplanado los borraría.
     */
    public function rellenar(array $datos): string
    {
        $datos = array_intersect_key($datos, array_flip($this->camposDisponibles()));

        $plantilla = $this->aFicheroTemporal($this->pdf);
        $xfdf = $this->aFicheroTemporal($this->xfdf($datos));
        $salida = tempnam(sys_get_temp_dir(), 'ficha');

        try {
            $this->pdftk([$plantilla, 'fill_form', $xfdf, 'output', $salida, 'need_appearances', 'flatten']);

            return file_get_contents($salida);
        } finally {
            foreach ([$plantilla, $xfdf, $salida] as $temporal) {
                if (is_file($temporal)) {
                    unlink($temporal);
                }
            }
        }
    }

    private function xfdf(array $datos): string
    {
        $campos = '';
        foreach ($datos as $nombre => $valor) {
            $campos .= sprintf(
                '<field name="%s"><value>%s</value></field>',
                htmlspecialchars((string) $nombre, ENT_XML1),
                htmlspecialchars((string) $valor, ENT_XML1),
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">'
            .'<fields>'.$campos.'</fields>'
            .'</xfdf>';
    }

    private function aFicheroTemporal(string $contenido): string
    {
        $fichero = tempnam(sys_get_temp_dir(), 'ficha');
        file_put_contents($fichero, $contenido);

        return $fichero;
    }

    private function pdftk(array $argumentos): string
    {
        $proceso = new Process(['pdftk', ...$argumentos]);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('pdftk: '.$proceso->getErrorOutput());
        }

        return $proceso->getOutput();
    }
}
