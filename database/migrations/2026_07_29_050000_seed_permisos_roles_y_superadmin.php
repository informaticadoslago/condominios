<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\PermisosYRolesInicialSeeder::class,
            '--force' => true,
        ]);

        // El superadmin NO se crea aquí: lo hace, una sola vez, el paso
        // opcional del instalador (condominios:install), que es quien decide
        // si toca crearlo/sobrescribirlo.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Los roles/permisos se van con las tablas (roles, permissions) al
        // revertir sus propias migraciones.
    }
};
