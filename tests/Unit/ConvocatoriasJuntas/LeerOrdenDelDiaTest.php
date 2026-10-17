<?php

namespace Tests\Unit\ConvocatoriasJuntas;

use App\Services\ConvocatoriasJuntas\LeerOrdenDelDia;
use PHPUnit\Framework\TestCase;

class LeerOrdenDelDiaTest extends TestCase
{
    private function leer(string $texto): array
    {
        return (new LeerOrdenDelDia())->ejecutar($texto);
    }

    /** El de una convocatoria real: puntos partidos en varias líneas y el último mal numerado y sin punto. */
    public function test_orden_del_dia_copiado_de_un_pdf(): void
    {
        $texto = <<<'TXT'
        1. Presentación nuevo administrador Comunidad. Renovación Junta de Gobierno.
        2. Exposición sobre las obligaciones de relaciones electrónicas por parte de la Comunidad
        con las Administraciones Públicas y otras obligaciones de las Comunidades en materia de
        Protección de datos, fiscal y Plan de Riesgos laborales. Decisiones a adoptar.
        3. Reparación de grietas estructurales en edificio. Ratificación de derrama al efecto puesta al
        cobro en julio 2026. Decisiones a adoptar.
        4. Asunto: Instalación ascensor en edificio. Presentación presupuestos. Decisiones a
        adoptar.
        5. Análisis de actuaciones a realizar en edificio (cambio puerta portal, etc.). Decisiones al
        respecto.
        6. Asunto: Obras que se realicen en edificio. Licencias, dirección de obra, etc.
        6 Ruegos y preguntas.
        TXT;

        $this->assertSame([
            'Presentación nuevo administrador Comunidad. Renovación Junta de Gobierno.',
            'Exposición sobre las obligaciones de relaciones electrónicas por parte de la Comunidad con las Administraciones Públicas y otras obligaciones de las Comunidades en materia de Protección de datos, fiscal y Plan de Riesgos laborales. Decisiones a adoptar.',
            'Reparación de grietas estructurales en edificio. Ratificación de derrama al efecto puesta al cobro en julio 2026. Decisiones a adoptar.',
            'Asunto: Instalación ascensor en edificio. Presentación presupuestos. Decisiones a adoptar.',
            'Análisis de actuaciones a realizar en edificio (cambio puerta portal, etc.). Decisiones al respecto.',
            'Asunto: Obras que se realicen en edificio. Licencias, dirección de obra, etc.',
            'Ruegos y preguntas.',
        ], $this->leer($texto));
    }

    public function test_un_numero_a_mitad_de_frase_no_parte_el_punto(): void
    {
        $texto = "1. Derrama puesta al cobro el\n3 de julio de 2026.\n2. Ruegos y preguntas.";

        $this->assertSame(['Derrama puesta al cobro el 3 de julio de 2026.', 'Ruegos y preguntas.'], $this->leer($texto));
    }

    public function test_un_anho_o_un_decimal_al_principio_de_linea_es_continuacion(): void
    {
        $texto = "1. Aprobación de cuentas de\n2025. Decisiones.\n2. Presupuesto de\n1.500 euros para la fachada.";

        $this->assertSame(['Aprobación de cuentas de 2025. Decisiones.', 'Presupuesto de 1.500 euros para la fachada.'], $this->leer($texto));
    }

    public function test_otros_formatos_de_numero_y_vinetas(): void
    {
        $texto = "1) Uno\n2º Dos\n3.- Tres\n4.Cuatro\n- Cinco\n• Seis";

        $this->assertSame(['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis'], $this->leer($texto));
    }

    public function test_sin_numeros_cada_linea_es_un_punto(): void
    {
        $this->assertSame(['Lectura del acta', 'Ruegos y preguntas'], $this->leer("Lectura del acta\n\n  Ruegos y preguntas  \n"));
    }

    public function test_una_linea_en_blanco_cierra_el_punto(): void
    {
        $texto = "ORDEN DEL DÍA\n\n1. Uno\ncontinúa\n\nFirmado: el presidente";

        $this->assertSame(['ORDEN DEL DÍA', 'Uno continúa', 'Firmado: el presidente'], $this->leer($texto));
    }

    public function test_texto_vacio(): void
    {
        $this->assertSame([], $this->leer("  \n\n "));
    }
}
