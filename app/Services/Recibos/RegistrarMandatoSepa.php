<?php

namespace App\Services\Recibos;

use App\Exceptions\MandatoSepaInvalidoException;
use App\Models\CuentaBancaria;
use App\Models\MandatoSepa;

/**
 * Registra el mandato firmado de una cuenta: su referencia (RUM) y su fecha de firma.
 *
 * Ninguno de los dos se genera. El RUM lo teclea quien registra el papel, y aquí solo se
 * comprueba que lo tecleado es coherente:
 *
 * - Tiene la forma `P19` + NIF del titular de la cuenta + contador. Si el NIF del código
 *   no es el del titular, el mandato no vale para esta cuenta.
 * - Ese RUM no está ya registrado con OTRA cuenta en la misma comunidad. Un mandato va
 *   casado con una cuenta; el contador existe precisamente para no reutilizarlo.
 * - Si la cuenta ya tiene mandato, no se pide otro: se devuelve el que hay.
 */
final class RegistrarMandatoSepa
{
    public function ejecutar(int $comunidadId, CuentaBancaria $cuenta, string $referencia, string $fechaFirma): MandatoSepa
    {
        $referencia = $this->normalizar($referencia);

        // Esa cuenta ya tiene mandato en esta comunidad: se enlaza al mismo, con su
        // número y su fecha. Si lo tecleado es otro número, es un error de quien lo
        // escribe, no una orden de cambiarlo.
        if ($existente = $this->mandatoDeLaCuenta($comunidadId, $cuenta->id)) {
            if ($existente->referencia !== $referencia) {
                throw new MandatoSepaInvalidoException(
                    "Esa cuenta ya tiene el mandato «{$existente->referencia}». Un mandato va casado con una cuenta y no se cambia."
                );
            }

            return $existente;
        }

        $this->comprobarFormato($referencia, $cuenta);

        // El mismo RUM con otra cuenta: o está mal tecleado, o se está reciclando el de
        // una cuenta abandonada. Ni una cosa ni la otra valen.
        $deOtraCuenta = MandatoSepa::where('comunidad_id', $comunidadId)
            ->where('referencia', $referencia)
            ->first();

        if ($deOtraCuenta) {
            throw new MandatoSepaInvalidoException(
                "El mandato «{$referencia}» ya está registrado con otra cuenta ({$deOtraCuenta->cuentaBancaria?->iban}). "
                .'Cada cuenta necesita su propio mandato: avanza el contador.'
            );
        }

        return MandatoSepa::create([
            'comunidad_id'       => $comunidadId,
            'cuenta_bancaria_id' => $cuenta->id,
            'referencia'         => $referencia,
            'fecha_firma'        => $fechaFirma,
        ]);
    }

    /** El mandato ya registrado de esa cuenta, si lo hay. */
    public function mandatoDeLaCuenta(int $comunidadId, int $cuentaBancariaId): ?MandatoSepa
    {
        return MandatoSepa::where('comunidad_id', $comunidadId)
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->first();
    }

    private function comprobarFormato(string $referencia, CuentaBancaria $cuenta): void
    {
        $nif = $this->normalizar((string) $cuenta->nifTitular());

        if ($nif === '') {
            throw new MandatoSepaInvalidoException(
                'El titular de esa cuenta no tiene documento identificativo, y el mandato se numera con él.'
            );
        }

        $esperado = MandatoSepa::PREFIJO.$nif;

        if (! str_starts_with($referencia, $esperado)) {
            throw new MandatoSepaInvalidoException(
                "El mandato tiene que empezar por «{$esperado}»: se numera con el NIF del titular de la cuenta."
            );
        }

        $contador = substr($referencia, strlen($esperado));

        if ($contador === '' || ! ctype_digit($contador)) {
            throw new MandatoSepaInvalidoException(
                "Después de «{$esperado}» falta el contador del mandato, que son solo dígitos."
            );
        }
    }

    private function normalizar(string $valor): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($valor)) ?? '');
    }
}
