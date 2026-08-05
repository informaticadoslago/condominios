<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seguro contra vaciar la base de trabajo.
     *
     * Los tests de este proyecto no pueden correr sobre SQLite —las migraciones crean
     * disparadores con SQL crudo—, así que van contra MariaDB de verdad. Eso significa
     * que un RefreshDatabase mal apuntado borraría la base de producción entera.
     *
     * Se comprueba con env() y antes de arrancar la aplicación, porque para cuando
     * parent::setUp() termina, RefreshDatabase ya ha hecho su trabajo.
     */
    protected function setUp(): void
    {
        $base = (string) env('DB_DATABASE');

        if (! str_ends_with($base, '_test')) {
            throw new RuntimeException(
                "Los tests apuntan a la base «{$base}», que no es una base de pruebas. "
                .'Revisa DB_DATABASE en phpunit.xml antes de seguir: ejecutarlos así vaciaría los datos reales.'
            );
        }

        parent::setUp();
    }
}
