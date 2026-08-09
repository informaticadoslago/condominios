<?php

namespace Tests\Feature\Presupuestos;

use App\Livewire\Presupuestos\Conceptos;
use App\Models\Presupuesto;
use App\Services\Presupuestos\CalculadorReparto;
use Carbon\Carbon;
use Tests\TestCase;

class ConceptosFechasPagoTest extends TestCase
{
    public function test_el_componente_expone_la_programacion_de_fechas_de_pago(): void
    {
        $reflection = new \ReflectionClass(Conceptos::class);

        $this->assertTrue($reflection->hasProperty('fechas_pago'));
        $this->assertTrue($reflection->hasProperty('bloqueado'));

        $rules = $reflection->getMethod('rules');
        $rules->setAccessible(true);
        $clin = new Conceptos();
        $reglas = $rules->invoke($clin);

        $this->assertArrayHasKey('fechas_pago', $reglas);
        $this->assertArrayHasKey('fechas_pago.*', $reglas);
    }

    public function test_el_reparto_lee_las_fechas_persistidas_del_presupuesto_si_existen(): void
    {
        $presupuesto = new Presupuesto();
        $presupuesto->estado_id = null;
        $presupuesto->periodicidad_id = 1;
        $presupuesto->fecha_primer_pago = Carbon::parse('2026-01-01');
        $presupuesto->numero_pagos = 3;
        $presupuesto->fechas_pago = ['2026-02-01', '2026-03-01', '2026-04-01'];

        $calculo = new CalculadorReparto();
        $fechas = $calculo->fechasPagos($presupuesto);

        $this->assertCount(3, $fechas);
        $this->assertSame('2026-02-01', $fechas[0]->toDateString());
        $this->assertSame('2026-03-01', $fechas[1]->toDateString());
        $this->assertSame('2026-04-01', $fechas[2]->toDateString());
    }
}
