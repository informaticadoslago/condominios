<?php

namespace Tests\Feature\L9_to_L12;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class DireccionesTriggersTest extends TestCase
{
    // No uses RefreshDatabase porque necesitas los datos reales y los triggers

    protected $connection = 'mysql'; // Forzar conexión MySQL
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Usar la base de datos real, no SQLite en memoria
        config(['database.default' => 'mysql']);
        
        // Asegurarse de que existe España
        $this->paisEspanaId = DB::table('paises')->where('nombre', 'España')->value('id');
        $this->assertNotNull($this->paisEspanaId, 'El país España debe existir');
        
        // Obtener IDs válidos para las pruebas
        $this->personaId = DB::table('personas')->value('id');
        $this->assertNotNull($this->personaId, 'Debe existir al menos una persona');
        
        $this->viaId = DB::table('vias')->value('id');
        $this->poblacionId = DB::table('poblaciones')->value('id');
    }

    public function test_insert_direcciones_crea_en_dosl_direcciones()
    {
        $datos = [
            'persona_id' => $this->personaId,
            'tipo_direccion_id' => 184,
            'via_id' => $this->viaId,
            'numero' => '123',
            'portal' => 'A',
            'piso' => '2',
            'puerta' => 'B',
            'barrio' => 'Centro',
            'poblacion_id' => $this->poblacionId,
            'codigopostal' => '08001',
            'estado_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $idDireccion = DB::table('direcciones')->insertGetId($datos);

        $doslDireccion = DB::table('dosl_direcciones')
            ->where('direccionable_id', $this->personaId)
            ->where('direccionable_type', 'App\\Models\\Persona')
            ->where('numero', '123')
            ->first();

        $this->assertNotNull($doslDireccion, 'Debe existir la dirección en dosl_direcciones');
        $this->assertEquals('123', $doslDireccion->numero);
        $this->assertEquals('Centro', $doslDireccion->barrio);
        $this->assertNotNull($doslDireccion->direccion1, 'direccion1 debe contener el nombre de la vía');

        // Limpieza
        DB::table('direcciones')->where('id', $idDireccion)->delete();
    }

    public function test_update_direcciones_actualiza_dosl_direcciones()
    {
        // Crear dirección
        $idDireccion = DB::table('direcciones')->insertGetId([
            'persona_id' => $this->personaId,
            'tipo_direccion_id' => 184,
            'numero' => '100',
            'piso' => '1',
            'poblacion_id' => $this->poblacionId,
            'estado_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $doslOriginal = DB::table('dosl_direcciones')
            ->where('direccionable_id', $this->personaId)
            ->latest('id')
            ->first();

        // Actualizar
        DB::table('direcciones')->where('id', $idDireccion)->update([
            'numero' => '200',
            'piso' => '5',
            'updated_at' => now(),
        ]);

        $doslActualizada = DB::table('dosl_direcciones')
            ->where('id', $doslOriginal->id)
            ->first();

        $this->assertEquals('200', $doslActualizada->numero);
        $this->assertEquals('5', $doslActualizada->piso);

        // Limpieza
        DB::table('direcciones')->where('id', $idDireccion)->delete();
    }

    public function test_delete_direcciones_elimina_de_dosl_direcciones()
    {
        // Crear dirección
        $idDireccion = DB::table('direcciones')->insertGetId([
            'persona_id' => $this->personaId,
            'tipo_direccion_id' => 184,
            'numero' => '999',
            'estado_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $doslId = DB::table('dosl_direcciones')
            ->where('direccionable_id', $this->personaId)
            ->where('numero', '999')
            ->value('id');

        $this->assertNotNull($doslId);

        // Eliminar
        DB::table('direcciones')->where('id', $idDireccion)->delete();

        $doslEliminada = DB::table('dosl_direcciones')->where('id', $doslId)->first();
        $this->assertNull($doslEliminada, 'La dirección debe eliminarse de dosl_direcciones');
    }

    public function test_insert_dosl_direcciones_crea_en_direcciones()
    {
        $idDosl = DB::table('dosl_direcciones')->insertGetId([
            'direccionable_id' => $this->personaId,
            'direccionable_type' => 'App\\Models\\Persona',
            'tipo_direccion_id' => 185,
            'via_id' => $this->viaId,
            'numero' => '555',
            'poblacion_id' => $this->poblacionId,
            'codigo_postal' => '08002',
            'pais_id' => $this->paisEspanaId,
            'estado_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $direccion = DB::table('direcciones')
            ->where('persona_id', $this->personaId)
            ->where('numero', '555')
            ->first();

        $this->assertNotNull($direccion, 'Debe crearse la dirección en direcciones');
        $this->assertEquals('555', $direccion->numero);

        // Limpieza
        DB::table('dosl_direcciones')->where('id', $idDosl)->delete();
    }

    protected function tearDown(): void
    {
        // Limpieza adicional por si acaso
        DB::table('direcciones')->where('numero', 'LIKE', '%TEST%')->delete();
        DB::table('dosl_direcciones')->where('numero', 'LIKE', '%TEST%')->delete();
        
        parent::tearDown();
    }
}