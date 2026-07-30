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

        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\CreateSuperUserSeeder::class,
            '--force' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Los roles/permisos y los usuarios creados se van con las tablas
        // (roles, permissions, users, personas) al revertir sus propias migraciones.
    }
};
