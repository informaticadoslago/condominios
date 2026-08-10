<?php

namespace Tests\Feature\Comunidades;

use App\Livewire\AdministracionSistema\Comunidades\Importar;
use App\Models\Comunidad;
use App\Services\Comunidades\ComunidadExportador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La importación de una comunidad debe rechazar un ZIP si el CIF ya existe y, tras
 * borrar la comunidad original, aceptar exactamente el mismo ZIP y reconstruir la
 * comunidad aunque pueda recibir IDs distintos en el sistema destino.
 */
class ComunidadImportarTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    private string $nombreZip;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.debug', true);

        Storage::fake('coms');
        Storage::fake('local');
        Storage::fake(config('documentos.disco'));

        $this->artisan('condominios:fakeseed')->assertExitCode(0);

        $this->comunidad = Comunidad::with('persona')->oldest('id')->firstOrFail();

        $this->nombreZip = app(ComunidadExportador::class)->exportar($this->comunidad);
    }

    private function zipExportado(): UploadedFile
    {
        return new UploadedFile(
            Storage::disk('coms')->path($this->nombreZip),
            basename($this->nombreZip),
            'application/zip',
            null,
            true,
        );
    }

    public function test_importar_falla_si_el_cif_ya_existe_y_luego_pasa_despues_de_borrar(): void
    {
        $cif = $this->comunidad->cif;

        Livewire::test(Importar::class)
            ->set('zip', $this->zipExportado())
            ->call('importar')
            ->assertHasErrors(['zip']);

        $this->assertDatabaseHas('comunidades', ['id' => $this->comunidad->id]);
        $this->assertDatabaseHas('personas', ['documento_identificativo' => $this->comunidad->cif]);

        $this->artisan('condominios:comunidad-borrar', ['comunidad' => $this->comunidad->id])
            ->expectsConfirmation('¿Continuar?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('comunidades', ['id' => $this->comunidad->id]);
        $this->assertDatabaseMissing('personas', ['documento_identificativo' => $cif]);

        Livewire::test(Importar::class)
            ->set('zip', $this->zipExportado())
            ->call('importar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', ['documento_identificativo' => $cif]);

        $comunidadImportada = Comunidad::query()
            ->whereHas('persona', fn ($q) => $q->where('documento_identificativo', $cif))
            ->first();

        $this->assertNotNull($comunidadImportada);
    }
}