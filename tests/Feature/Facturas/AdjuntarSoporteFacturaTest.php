<?php

namespace Tests\Feature\Facturas;

use App\Livewire\Facturas\Lista;
use App\Models\Comunidad;
use App\Models\Documento;
use App\Models\FacturaProveedor;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El papel de una factura que se tecleó sin él: llega más tarde y se adjunta desde la
 * propia lista, pulsando en su «Sin soporte».
 */
class AdjuntarSoporteFacturaTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    private Comunidad $otraComunidad;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('documentos.disco'));

        $comunidades = (new DemoComunidadSeeder())->generar();

        $this->comunidad     = $comunidades['edificio1'];
        $this->otraComunidad = $comunidades['edificio2'];

        $user = User::forceCreate([
            'login' => 'tester', 'email' => 'tester@example.test', 'password' => bcrypt('secreto'),
        ]);
        $this->actingAs($user);
        session(['comunidad_actual_id' => $this->comunidad->id]);
    }

    /** Una factura tecleada a mano, sin papel detrás. */
    private function facturaSinSoporte(Comunidad $comunidad, string $numero = 'F-1'): FacturaProveedor
    {
        (new AltaProveedorDesdeFactura())->ejecutar(
            comunidadId: $comunidad->id,
            documento: 'B12345674',
            razonSocial: 'FONTANERÍA DEL SUR SL',
            metadatosFichero: [],
            numeroFactura: $numero,
            fecha: '15/03/2026',
            importe: '119,03',
        );

        return FacturaProveedor::where('numero_factura', $numero)->firstOrFail();
    }

    public function test_el_papel_que_llega_tarde_se_queda_enganchado_a_la_factura(): void
    {
        $factura = $this->facturaSinSoporte($this->comunidad);
        $this->assertNull($factura->documento_id);

        Livewire::test(Lista::class)
            ->set("soporte.{$factura->id}", UploadedFile::fake()->create('la-factura.pdf', 20, 'application/pdf'))
            ->assertHasNoErrors();

        $factura->refresh();
        $documento = Documento::findOrFail($factura->documento_id);

        $this->assertEquals(TipoDocumento::FACTURA, $documento->tipo_documento_id);
        $this->assertSame('Factura F-1', $documento->descripcion);
        $this->assertSame('la-factura.pdf', $documento->nombrelocal);
        // Consolidado: fuera de la carpeta de borradores y con el fichero en su sitio.
        $this->assertSame('', (string) $documento->camino);
        $this->assertTrue($documento->existeFichero());
    }

    public function test_el_papel_cuelga_del_proveedor_de_la_factura(): void
    {
        $factura = $this->facturaSinSoporte($this->comunidad);

        Livewire::test(Lista::class)
            ->set("soporte.{$factura->id}", UploadedFile::fake()->create('otra.pdf', 10, 'application/pdf'));

        $documento = Documento::findOrFail($factura->refresh()->documento_id);

        $this->assertTrue($documento->documentable->is($factura->proveedor));
    }

    public function test_una_factura_de_otra_comunidad_no_recibe_papel(): void
    {
        $ajena = $this->facturaSinSoporte($this->otraComunidad, 'F-AJENA');

        Livewire::test(Lista::class)
            ->set("soporte.{$ajena->id}", UploadedFile::fake()->create('colada.pdf', 10, 'application/pdf'));

        $this->assertNull($ajena->refresh()->documento_id);
    }

    public function test_a_la_factura_que_ya_tiene_papel_no_se_le_cambia(): void
    {
        $factura = $this->facturaSinSoporte($this->comunidad);

        Livewire::test(Lista::class)
            ->set("soporte.{$factura->id}", UploadedFile::fake()->create('primera.pdf', 10, 'application/pdf'));

        $documentoId = $factura->refresh()->documento_id;

        Livewire::test(Lista::class)
            ->set("soporte.{$factura->id}", UploadedFile::fake()->create('segunda.pdf', 10, 'application/pdf'));

        $this->assertSame($documentoId, $factura->refresh()->documento_id);
    }

    public function test_un_fichero_que_no_es_pdf_ni_imagen_no_se_adjunta(): void
    {
        $factura = $this->facturaSinSoporte($this->comunidad);

        Livewire::test(Lista::class)
            ->set("soporte.{$factura->id}", UploadedFile::fake()->create('hoja.xlsx', 10, 'application/vnd.ms-excel'));

        $this->assertNull($factura->refresh()->documento_id);
    }
}
