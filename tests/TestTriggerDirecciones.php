<?php

use Illuminate\Support\Facades\DB;

// Probar INSERT en direcciones -> dosl_direcciones
echo "=== TEST 1: INSERT en direcciones ===\n";

$nuevaDireccion = [
    'persona_id' => 1, // Ajusta con un ID válido de tu tabla personas
    'tipo_direccion_id' => 184,
    'via_id' => 1, // Ajusta con un ID válido
    'numero' => '123',
    'portal' => 'A',
    'piso' => '2',
    'puerta' => 'B',
    'barrio' => 'Centro',
    'poblacion_id' => 1, // Ajusta con un ID válido
    'codigopostal' => '08001',
    'estado_id' => 1,
    'created_at' => now(),
    'updated_at' => now(),
];

$idDireccion = DB::table('direcciones')->insertGetId($nuevaDireccion);
echo "✓ Dirección insertada con ID: {$idDireccion}\n";

// Verificar si se creó en dosl_direcciones
$doslDireccion = DB::table('dosl_direcciones')
    ->where('direccionable_id', $nuevaDireccion['persona_id'])
    ->where('direccionable_type', 'App\\Models\\Persona')
    ->latest('id')
    ->first();

if ($doslDireccion) {
    echo "✓ Dirección creada en dosl_direcciones con ID: {$doslDireccion->id}\n";
    echo "  - direccion1: {$doslDireccion->direccion1}\n";
    echo "  - provincia: {$doslDireccion->provincia}\n";
    echo "  - municipio: {$doslDireccion->municipio}\n";
} else {
    echo "✗ ERROR: No se creó en dosl_direcciones\n";
}

echo "\n=== TEST 2: UPDATE en direcciones ===\n";

DB::table('direcciones')->where('id', $idDireccion)->update([
    'numero' => '456',
    'piso' => '3',
    'updated_at' => now(),
]);
echo "✓ Dirección actualizada\n";

$doslActualizada = DB::table('dosl_direcciones')->where('id', $doslDireccion->id)->first();
if ($doslActualizada && $doslActualizada->numero == '456' && $doslActualizada->piso == '3') {
    echo "✓ Cambios sincronizados en dosl_direcciones\n";
} else {
    echo "✗ ERROR: No se actualizó en dosl_direcciones\n";
}

echo "\n=== TEST 3: DELETE en direcciones ===\n";

DB::table('direcciones')->where('id', $idDireccion)->delete();
echo "✓ Dirección eliminada\n";

$doslEliminada = DB::table('dosl_direcciones')->where('id', $doslDireccion->id)->first();
if (!$doslEliminada) {
    echo "✓ Dirección eliminada también de dosl_direcciones\n";
} else {
    echo "✗ ERROR: No se eliminó de dosl_direcciones\n";
}

echo "\n=== TEST 4: INSERT en dosl_direcciones ===\n";

$nuevaDoslDireccion = [
    'direccionable_id' => 1,
    'direccionable_type' => 'App\\Models\\Persona',
    'tipo_direccion_id' => 185,
    'via_id' => 2,
    'numero' => '789',
    'poblacion_id' => 1,
    'codigo_postal' => '08002',
    'pais_id' => DB::table('paises')->where('nombre', 'España')->value('id'),
    'estado_id' => 1,
    'created_at' => now(),
    'updated_at' => now(),
];

$idDoslDireccion = DB::table('dosl_direcciones')->insertGetId($nuevaDoslDireccion);
echo "✓ Dirección insertada en dosl_direcciones con ID: {$idDoslDireccion}\n";

$direccionCreada = DB::table('direcciones')
    ->where('persona_id', $nuevaDoslDireccion['direccionable_id'])
    ->latest('id')
    ->first();

if ($direccionCreada) {
    echo "✓ Dirección creada en direcciones con ID: {$direccionCreada->id}\n";
} else {
    echo "✗ ERROR: No se creó en direcciones\n";
}

echo "\n=== Limpieza ===\n";
if (isset($direccionCreada->id)) {
    DB::table('direcciones')->where('id', $direccionCreada->id)->delete();
    echo "✓ Registros de prueba eliminados\n";
}

echo "\n=== TESTS COMPLETADOS ===\n";