<?php

namespace Tests\Feature\Contabilidad;

use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\Comunidad;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\TerceroContable;
use App\Models\User;
use App\Services\Contabilidad\ContabilidadEliminador;
use App\Services\Contabilidad\ContabilidadExportador;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

/**
 * El par de comandos de contabilidad: llevarse una empresa contable entera a un .zip y
 * borrarla de la base. Son el equivalente de comunidad-exportar / comunidad-borrar para
 * el otro módulo.
 */
class ContabilidadExportarBorrarTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;

    private EmpresaContable $otraEmpresa;

    private EjercicioContable $ejercicio;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('coms');

        $this->empresa     = EmpresaContable::create(['cif' => 'H12345678', 'razon_social' => 'Comunidad exportable']);
        $this->otraEmpresa = EmpresaContable::create(['cif' => 'H87654321', 'razon_social' => 'Comunidad vecina']);

        $this->ejercicio = EjercicioContable::create([
            'empresa_contable_id' => $this->empresa->id,
            'nombre'              => '2026',
            'fecha_inicio'        => '2026-01-01',
            'fecha_fin'           => '2026-12-31',
            'cerrado'             => false,
        ]);

        // Jerarquía de tres niveles: lo que obliga a borrar las cuentas de hojas a raíz.
        $grupo    = $this->cuenta('7', 'Ventas e ingresos', null, null);
        $subgrupo = $this->cuenta('70', 'Ventas', null, $grupo);
        $ingresos = $this->cuenta('70500001', 'Cuotas ordinarias', 4, $subgrupo);

        $clientes  = $this->cuenta('4', 'Acreedores y deudores', null, null);
        $cliente   = $this->cuenta('43000001', 'Un propietario', 1, $clientes);

        TerceroContable::create([
            'empresa_contable_id'      => $this->empresa->id,
            'tipo_tercero_contable_id' => 1,
            'sujeto_tipo'              => 'propietario',
            'sujeto_id'                => '42',
            'nif'                      => '12345678Z',
            'razon_social'             => 'Un propietario',
            'cuenta_contable_id'       => $cliente->id,
            'estado_id'                => CuentaContable::ESTADO_ACTIVO,
        ]);

        $asiento = AsientoContable::create([
            'empresa_contable_id'   => $this->empresa->id,
            'ejercicio_contable_id' => $this->ejercicio->id,
            'numero'                => 1,
            'fecha'                 => '2026-01-31',
            'diario'                => 1,
            'concepto'              => 'Cuota de enero',
            'referencia_tipo'       => 'recibo',
            'referencia_id'         => '7',
            'evento'                => 'emision',
        ]);

        ApunteContable::create(['asiento_contable_id' => $asiento->id, 'cuenta_contable_id' => $cliente->id, 'debe' => 12000, 'haber' => 0, 'concepto' => 'Cuota de enero']);
        ApunteContable::create(['asiento_contable_id' => $asiento->id, 'cuenta_contable_id' => $ingresos->id, 'debe' => 0, 'haber' => 12000, 'concepto' => 'Cuota de enero']);
    }

    private function cuenta(string $codigo, string $nombre, ?int $tipo = 1, ?CuentaContable $padre = null): CuentaContable
    {
        return CuentaContable::create([
            'empresa_contable_id'     => $this->empresa->id,
            'tipo_cuenta_contable_id' => $tipo,
            'cuenta_padre_id'         => $padre?->id,
            'codigo'                  => $codigo,
            'nombre'                  => $nombre,
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);
    }

    /** @return array<string, string> nombre dentro del zip => contenido */
    private function contenidoZip(string $nombreZip): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('coms')->path($nombreZip)) === true, "No se pudo abrir {$nombreZip}");

        $contenido = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre             = $zip->getNameIndex($i);
            $contenido[$nombre] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $contenido;
    }

    public function test_el_zip_lleva_los_libros_de_la_empresa_y_nada_mas(): void
    {
        $nombreZip = app(ContabilidadExportador::class)->exportar($this->empresa);

        Storage::disk('coms')->assertExists($nombreZip);
        $ficheros = $this->contenidoZip($nombreZip);

        $this->assertSame(['datos.xml', 'indice.md'], array_keys($ficheros));

        $xml = simplexml_load_string($ficheros['datos.xml']);
        $this->assertNotFalse($xml, 'El datos.xml no está bien formado.');

        $this->assertCount(1, $xml->empresa_contable->fila);
        $this->assertCount(5, $xml->cuenta_contables->fila);
        $this->assertCount(1, $xml->tercero_contables->fila);
        $this->assertCount(1, $xml->ejercicio_contables->fila);
        $this->assertCount(1, $xml->asiento_contables->fila);
        $this->assertCount(2, $xml->apunte_contables->fila);

        // Importes en céntimos, tal cual están en la base.
        $this->assertSame('12000', (string) $xml->apunte_contables->fila[0]->debe);

        // Las referencias opacas viajan sin traducir.
        $this->assertSame('recibo', (string) $xml->asiento_contables->fila[0]->referencia_tipo);
        $this->assertSame('propietario', (string) $xml->tercero_contables->fila[0]->sujeto_tipo);

        // Un nulo se distingue de la cadena vacía.
        $this->assertSame('true', (string) $xml->cuenta_contables->fila[0]->cuenta_padre_id['nulo']);

        $this->assertStringContainsString('Todos los asientos exportados cuadran', $ficheros['indice.md']);
    }

    public function test_las_cuentas_salen_ordenadas_por_codigo_para_poder_reconstruir_la_jerarquia(): void
    {
        $nombreZip = app(ContabilidadExportador::class)->exportar($this->empresa);
        $xml       = simplexml_load_string($this->contenidoZip($nombreZip)['datos.xml']);

        $codigos = [];
        foreach ($xml->cuenta_contables->fila as $fila) {
            $codigos[] = (string) $fila->codigo;
        }

        $this->assertSame(['4', '43000001', '7', '70', '70500001'], $codigos);
    }

    public function test_el_zip_no_se_lleva_ni_el_plan_maestro_ni_la_otra_empresa(): void
    {
        CuentaContable::create([
            'empresa_contable_id'     => null,
            'tipo_cuenta_contable_id' => null,
            'codigo'                  => '6',
            'nombre'                  => 'Compras y gastos',
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);

        CuentaContable::create([
            'empresa_contable_id'     => $this->otraEmpresa->id,
            'tipo_cuenta_contable_id' => 1,
            'codigo'                  => '57200099',
            'nombre'                  => 'Banco de la vecina',
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);

        $xml = simplexml_load_string(
            $this->contenidoZip(app(ContabilidadExportador::class)->exportar($this->empresa))['datos.xml']
        );

        $this->assertCount(5, $xml->cuenta_contables->fila);
        $this->assertStringNotContainsString('Banco de la vecina', $xml->asXML());
        $this->assertStringNotContainsString('Compras y gastos', $xml->asXML());
    }

    public function test_el_indice_avisa_de_los_asientos_descuadrados(): void
    {
        ApunteContable::first()->update(['debe' => 9900]);

        $indice = $this->contenidoZip(app(ContabilidadExportador::class)->exportar($this->empresa))['indice.md'];

        $this->assertStringContainsString('Asientos que NO cuadran', $indice);
        $this->assertStringContainsString('9900 / 12000 céntimos', $indice);
    }

    public function test_borrar_se_lleva_la_empresa_entera_sin_tocar_a_la_vecina(): void
    {
        $cuentaVecina = CuentaContable::create([
            'empresa_contable_id'     => $this->otraEmpresa->id,
            'tipo_cuenta_contable_id' => 1,
            'codigo'                  => '57200099',
            'nombre'                  => 'Banco de la vecina',
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);

        app(ContabilidadEliminador::class)->eliminar($this->empresa);

        $this->assertDatabaseMissing('empresas_contables', ['id' => $this->empresa->id]);
        $this->assertDatabaseCount('apunte_contables', 0);
        $this->assertDatabaseCount('asiento_contables', 0);
        $this->assertDatabaseCount('tercero_contables', 0);
        $this->assertDatabaseCount('ejercicio_contables', 0);

        // De la vecina no se toca nada.
        $this->assertDatabaseHas('empresas_contables', ['id' => $this->otraEmpresa->id]);
        $this->assertDatabaseHas('cuenta_contables', ['id' => $cuentaVecina->id]);
    }

    public function test_borrar_se_lleva_el_rol_de_acceso_y_los_tokens_de_esa_empresa(): void
    {
        $usuario = User::forceCreate([
            'login'    => 'tester',
            'email'    => 'tester@example.test',
            'password' => bcrypt('secreto'),
        ]);

        $suyo  = $usuario->createToken('el de esta', [$this->empresa->habilidadToken()]);
        $ajeno = $usuario->createToken('el de la vecina', [$this->otraEmpresa->habilidadToken()]);

        $this->assertNotNull(Role::where('name', $this->empresa->nombreRol())->first());

        app(ContabilidadEliminador::class)->eliminar($this->empresa);

        $this->assertNull(Role::where('name', $this->empresa->nombreRol())->first());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $suyo->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $ajeno->accessToken->id]);
    }

    public function test_el_comando_borrar_deja_sin_enlace_a_las_comunidades_que_llevaban_sus_libros_ahi(): void
    {
        $comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        $comunidad->update(['empresa_contable_id' => $this->empresa->id]);

        $this->artisan('condominios:contabilidad-borrar', ['empresa' => $this->empresa->id])
            ->expectsConfirmation('¿Continuar?', 'yes')
            ->assertExitCode(0);

        $this->assertNull($comunidad->fresh()->empresa_contable_id);
    }
}
