<?php

namespace App\Http\Traits;

trait ValidatesIbanSepa
{
    function validarIbanSepa(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        $iban = preg_replace('/[\s_]+/', '', $iban);

        $ibanLength = [
            'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20,
            'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IS' => 26,
            'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28,
            'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'VA' => 22,
        ];

        $country = substr($iban, 0, 2);

        if (! isset($ibanLength[$country])) {
            return false; // País fuera de SEPA
        }

        if (strlen($iban) !== $ibanLength[$country]) {
            return false; // Longitud incorrecta
        }

        // Verificar checksum IBAN (módulo 97)
        $reordered = substr($iban, 4) . substr($iban, 0, 4);
        $numeric   = '';
        foreach (str_split($reordered) as $char) {
            $numeric .= ctype_alpha($char) ? (ord($char) - 55) : $char;
        }

        return bcmod($numeric, 97) == 1;
    }
}
