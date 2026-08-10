<?php

namespace Tests\Feature\Comunidades;

use App\Models\AvisoRecibo;
use App\Models\Cobro;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\LineaRemesa;
use App\Models\MandatoSepa;
use App\Models\PersonaComunidad;
use App\Models\Presupuesto;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoEstadoPresupuesto;
use App\Models\TipoPresupuesto;
use App\Services\Comunidades\ComunidadExportador;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * La exportación de una comunidad tiene que llevarse TODO lo que cuelga de ella. Cada
 * vez que aparece una tabla nueva colgando de la comunidad hay que meterla en
 * ComunidadExportador y añadirla aquí: si no, el .zip sale incompleto sin dar ningún
 * error, que es la manera más silenciosa de perder datos.
 */
class ComunidadExportarTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    private Presupuesto $presupuesto;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('coms');

        // Las formas de pago son catálogo de seeder y recibos/cobros tienen FK contra ellas.
        DB::table('formas_de_pago')->insert([
            ['id' => FormaDePago::RECIBO_BANCARIO, 'descripcion' => 'Recibo bancario', 'estado_id' => 1],
            ['id' => FormaDePago::EFECTIVO, 'descripcion' => 'Efectivo', 'estado_id' => 1],
            ['id' => FormaDePago::TRANSFERENCIA, 'descripcion' => 'Transferencia', 'estado_id' => 1],
        ]);

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        $this->presupuesto = Presupuesto::create([
            'comunidad_id'        => $this->comunidad->id,
            'nombre'              => 'Presupuesto 2026',
            'anho'                => 2026,
            'tipo_presupuesto_id' => TipoPresupuesto::CUOTAS,
            'estado_id'           => TipoEstadoPresupuesto::PROVISIONAL,
        ]);
    }

    /** Un propietario con su inmueble, su cuenta, su mandato, su recibo remesado y cobrado. */
    private function cicloCompletoDeCobro(): void
    {
        $persona = PersonaComunidad::create([
            'comunidad_id'             => $this->comunidad->id,
            'nombre'                   => 'Vecina del quinto',
            'documento_identificativo' => '12345678Z',
        ]);

        $propietario = Propietario::create(['persona_comunidad_id' => $persona->id]);

        $inmueble = Inmueble::create([
            'comunidad_id'     => $this->comunidad->id,
            'ocupacion_id'     => 1,
            'tipo_inmueble_id' => 1,
            'planta'           => '5',
            'puerta'           => 'A',
            'coeficiente'      => 10,
        ]);

        $cuenta = CuentaBancaria::create([
            'titular_type'         => Propietario::class,
            'titular_id'           => $propietario->id,
            'persona_comunidad_id' => $persona->id,
            'iban'                 => 'ES9121000418450200051332',
        ]);

        MandatoSepa::create([
            'comunidad_id'       => $this->comunidad->id,
            'cuenta_bancaria_id' => $cuenta->id,
            'referencia'         => 'P1912345678Z0001',
            'fecha_firma'        => '2026-01-15',
        ]);

        $recibo = Recibo::create([
            'presupuesto_id'    => $this->presupuesto->id,
            'inmueble_id'       => $inmueble->id,
            'propietario_id'    => $propietario->id,
            'numero_pago'       => 1,
            'fecha_vencimiento' => '2026-01-31',
            'importe'           => '120.00',
            'forma_de_pago_id'  => FormaDePago::RECIBO_BANCARIO,
            'cuenta_bancaria_id' => $cuenta->id,
        ]);

        $remesa = Remesa::create([
            'comunidad_id'       => $this->comunidad->id,
            'cuenta_bancaria_id' => $cuenta->id,
            'referencia'         => 'REM-2026-02-05-1',
            'fecha_cargo'        => '2026-02-05',
        ]);

        $linea = LineaRemesa::create([
            'remesa_id' => $remesa->id,
            'recibo_id' => $recibo->id,
            'importe'   => '120.00',
            'iban'      => $cuenta->iban,
        ]);

        Cobro::create([
            'recibo_id'        => $recibo->id,
            'forma_de_pago_id' => FormaDePago::RECIBO_BANCARIO,
            'linea_remesa_id'  => $linea->id,
            'fecha'            => '2026-02-05',
            'importe'          => '120.00',
        ]);

        AvisoRecibo::create([
            'recibo_id'    => $recibo->id,
            'motivo'       => 'emision',
            'destinatario' => 'vecina@example.test',
            'enviado_at'   => '2026-01-20 10:00:00',
        ]);
    }

    private function xmlExportado(): \SimpleXMLElement
    {
        $nombreZip = app(ComunidadExportador::class)->exportar($this->comunidad);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('coms')->path($nombreZip)) === true);
        $datos = $zip->getFromName('datos.xml');
        $zip->close();

        $xml = simplexml_load_string($datos);
        $this->assertNotFalse($xml, 'El datos.xml no está bien formado.');

        return $xml;
    }

    public function test_el_zip_se_lleva_recibos_remesas_cobros_avisos_y_mandatos(): void
    {
        $this->cicloCompletoDeCobro();

        $xml = $this->xmlExportado();

        $this->assertCount(1, $xml->recibos->fila, 'Faltan los recibos en la exportación.');
        $this->assertCount(1, $xml->remesas->fila, 'Faltan las remesas en la exportación.');
        $this->assertCount(1, $xml->lineas_remesas->fila, 'Faltan las líneas de remesa en la exportación.');
        $this->assertCount(1, $xml->cobros->fila, 'Faltan los cobros en la exportación.');
        $this->assertCount(1, $xml->avisos_recibos->fila, 'Faltan los avisos de recibo en la exportación.');
        $this->assertCount(1, $xml->mandatos_sepa->fila, 'Faltan los mandatos SEPA en la exportación.');

        $this->assertSame('P1912345678Z0001', (string) $xml->mandatos_sepa->fila[0]->referencia);
        $this->assertSame('120.00', (string) $xml->cobros->fila[0]->importe);
    }

    public function test_no_se_lleva_lo_de_otra_comunidad(): void
    {
        $this->cicloCompletoDeCobro();

        $otra = (new DemoComunidadSeeder())->generar()['edificio1'];

        MandatoSepa::create([
            'comunidad_id'       => $otra->id,
            'cuenta_bancaria_id' => CuentaBancaria::create([
                'titular_type' => Comunidad::class,
                'titular_id'   => $otra->id,
                'iban'         => 'ES7921000813610123456789',
            ])->id,
            'referencia'  => 'P1987654321X0001',
            'fecha_firma' => '2026-01-15',
        ]);

        $xml = $this->xmlExportado();

        $this->assertCount(1, $xml->mandatos_sepa->fila);
        $this->assertStringNotContainsString('P1987654321X0001', $xml->asXML());
    }

    public function test_el_indice_cuenta_las_tablas_nuevas(): void
    {
        $this->cicloCompletoDeCobro();

        $nombreZip = app(ComunidadExportador::class)->exportar($this->comunidad);

        $zip = new ZipArchive();
        $zip->open(Storage::disk('coms')->path($nombreZip));
        $indice = $zip->getFromName('indice.md');
        $zip->close();

        foreach (['recibos', 'remesas', 'lineas_remesas', 'cobros', 'avisos_recibos', 'mandatos_sepa'] as $tabla) {
            $this->assertStringContainsString("- **{$tabla}**: 1 fila(s)", $indice);
        }
    }

    public function test_borrar_comunidad_con_mandatos_y_remesas_no_revienta_por_foreign_keys(): void
    {
        $this->cicloCompletoDeCobro();

        $this->artisan('condominios:comunidad-borrar', ['comunidad' => $this->comunidad->id])
            ->expectsConfirmation('¿Continuar?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('comunidades', ['id' => $this->comunidad->id]);
        $this->assertDatabaseCount('mandatos_sepa', 0);
        $this->assertDatabaseCount('remesas', 0);
        $this->assertDatabaseCount('lineas_remesas', 0);
        $this->assertDatabaseCount('cobros', 0);
        $this->assertDatabaseCount('avisos_recibos', 0);
    }
}
