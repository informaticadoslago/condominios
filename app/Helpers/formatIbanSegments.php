<?php

if (! function_exists('iban2trozosx4')) {

function formatIbanSegments(?string $iban): string {
    // Sin IBAN (efectivo, transferencia…) no hay nada que trocear.
    if (blank($iban)) {
        return '';
    }

    $iban = strtoupper(str_replace(' ', '', $iban));

    $countryCode = substr($iban, 0, 2);
    $checkDigits = substr($iban, 2, 2);
    $bban = substr($iban, 4);

    // Segmentos del BBAN por país SEPA (sin contar país y dígitos de control)
    $countryFormats = [
        'IT' => [1, 5, 5, 12],
        'ES' => [4, 4, 4, 4, 4],
        'DE' => [8, 10],
        'FR' => [5, 5, 11, 2],
        'BE' => [4, 4, 4, 4],
        'AT' => [4, 5, 11],
        'NL' => [4, 10],
        'PT' => [4, 4, 11, 2],
        'IE' => [4, 6, 8],
        'FI' => [6, 7],
        'SE' => [3, 16],
        'DK' => [4, 9, 1],
        'NO' => [4, 9],
        'LU' => [3, 13],
        'MT' => [4, 5, 18],
        'CY' => [3, 5, 16],
        'GR' => [3, 4, 16],
        'BG' => [4, 4, 2, 4, 4, 4],
        'CZ' => [4, 6, 10],
        'HU' => [3, 4, 16],
        'SK' => [4, 6, 10],
        'SI' => [5, 8],
        'HR' => [4, 4, 4, 4],
        'IS' => [4, 2, 6, 10],
        'LV' => [4, 13],
        'LT' => [5, 11],
        'LI' => [5, 12],
        'GB' => [4, 6, 8],
    ];

    if (!isset($countryFormats[$countryCode])) {
        $segments = str_split($bban, 4);
    } else {
        $segments = [];
        $pos = 0;
        foreach ($countryFormats[$countryCode] as $len) {
            $segments[] = substr($bban, $pos, $len);
            $pos += $len;
        }
    }

    // Primer bloque: país + dígitos de control
    array_unshift($segments, $countryCode . $checkDigits);

    return implode(' ', $segments);
}

    function iban2trozosx4($iban)
    {
        return formatIbanSegments($iban);
        // if (strlen($iban) == 24) {
        //     $trozos = [];
        //     for ($i = 0; $i < 6; $i++) {
        //         $trozos[] = substr($iban, $i * 4, 4);
        //     }
        //     return implode(' ', $trozos);
        // }
        // return $iban;
    }
}

