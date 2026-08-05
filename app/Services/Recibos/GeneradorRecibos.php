<?php

namespace App\Services\Recibos;

use App\Exceptions\RecibosNoGenerablesException;
use App\Models\Presupuesto;
use App\Models\Recibo;
use App\Models\TipoEstadoRecibo;
use App\Models\TipoEstadoPresupuesto;
use App\Services\Presupuestos\CalculadorReparto;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Vuelca a `recibos` el reparto de un presupuesto al aprobarse.
 *
 * Se ejecuta ANTES de que avanzarRotacionReparto() mueva la rotación de céntimos: los
 * recibos tienen que llevar el reparto que se aprobó, no el que saldría con la rotación
 * ya avanzada.
 *
 * Es idempotente: la clave única (presupuesto, inmueble, número de pago) hace que volver
 * a aprobar no duplique nada.
 */
final class GeneradorRecibos
{
    public function __construct(private readonly CalculadorReparto $calculador)
    {
    }

    /** @return int recibos creados */
    public function generar(Presupuesto $presupuesto): int
    {
        if ($presupuesto->estado_id != TipoEstadoPresupuesto::APROBADO) {
            throw new RecibosNoGenerablesException('Solo se generan recibos de un presupuesto aprobado.');
        }

        $reparto = $this->calculador->calcular($presupuesto);

        if (! $reparto['datosPagoCompletos']) {
            throw new RecibosNoGenerablesException(
                'El presupuesto no tiene fecha de primer pago, periodicidad y número de pagos; sin eso no hay vencimientos que emitir.'
            );
        }

        if ($reparto['global']->isEmpty()) {
            throw new RecibosNoGenerablesException('El presupuesto no reparte nada entre inmuebles.');
        }

        // Colección de Eloquent, no la de Support que devuelve pluck(): hace falta
        // load() para traer titulares y formas de pago de una vez y no una consulta por
        // inmueble.
        $inmuebles = new EloquentCollection($reparto['global']->pluck('inmueble')->all());
        $inmuebles->load('formaPagoVigente');

        // Se comprueba todo antes de crear nada: un juego de recibos a medias dejaría
        // fuera de la remesa a inmuebles que sí deben pagar, y eso no se ve hasta que
        // falta el dinero.
        $this->comprobarDatosDeCobro($inmuebles);

        return DB::transaction(function () use ($presupuesto, $reparto, $inmuebles): int {
            $creados = 0;

            foreach ($reparto['global'] as $fila) {
                $inmueble      = $inmuebles->firstWhere('id', $fila['inmueble']->id);
                $propietarioId = $this->propietarioQuePaga($inmueble);
                $formaPago     = $inmueble->formaPagoVigente;

                foreach ($fila['pagos'] as $i => $importe) {
                    $recibo = Recibo::firstOrCreate(
                        [
                            'presupuesto_id' => $presupuesto->id,
                            'inmueble_id'    => $inmueble->id,
                            'numero_pago'    => $i + 1,
                        ],
                        [
                            'propietario_id'     => $propietarioId,
                            'fecha_vencimiento'  => $reparto['fechasPagos'][$i],
                            'importe'            => $importe,
                            'importe_pagado'     => 0,
                            'forma_de_pago_id'   => $formaPago->forma_de_pago_id,
                            'cuenta_bancaria_id' => $formaPago->cuenta_bancaria_id,
                            'estado_id'          => TipoEstadoRecibo::GENERADO,
                        ],
                    );

                    if ($recibo->wasRecentlyCreated) {
                        $creados++;
                    }
                }
            }

            return $creados;
        });
    }

    /**
     * Un inmueble sin titular vigente o sin forma de pago vigente no se puede cobrar.
     * Se acumulan todos y se avisa de una vez, no del primero que falle: quien tenga que
     * corregir los datos prefiere la lista entera.
     */
    private function comprobarDatosDeCobro($inmuebles): void
    {
        $sinPropietario = [];
        $sinFormaPago   = [];

        foreach ($inmuebles as $inmueble) {
            if ($this->propietarioQuePaga($inmueble) === null) {
                $sinPropietario[] = $this->nombre($inmueble);
            }

            if ($inmueble->formaPagoVigente === null) {
                $sinFormaPago[] = $this->nombre($inmueble);
            }
        }

        $problemas = [];

        if ($sinPropietario !== []) {
            $problemas[] = 'sin propietario que pague en sus datos financieros: '.implode(', ', $sinPropietario);
        }

        if ($sinFormaPago !== []) {
            $problemas[] = 'sin forma de pago vigente: '.implode(', ', $sinFormaPago);
        }

        if ($problemas !== []) {
            throw new RecibosNoGenerablesException(
                'No se pueden generar los recibos. Inmuebles '.implode('; ', $problemas).'.'
            );
        }
    }

    /**
     * El recibo va a nombre del propietario responsable del pago, el elegido en Datos
     * financieros del inmueble. No se deduce de la titularidad: con titularidad
     * compartida el que paga y el de mayor cuota son cosas distintas, y suponer uno por
     * otro emite el recibo a nombre de quien no toca.
     *
     * Si el dato no está, falta: devuelve null y la generación se para.
     */
    private function propietarioQuePaga($inmueble): ?int
    {
        return $inmueble->formaPagoVigente?->propietario_id;
    }

    private function nombre($inmueble): string
    {
        return trim(($inmueble->planta ?? '').' '.($inmueble->puerta ?? '')) ?: "#{$inmueble->id}";
    }
}
