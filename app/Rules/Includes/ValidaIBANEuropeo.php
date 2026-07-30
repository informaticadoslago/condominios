<?php

// Chatgpt.es 13/02/2025

// // Ejemplo de uso
// $iban = "GB29NWBK60161331926819"; // Cambia esto por el IBAN que deseas validar
// if (validarIBAN($iban)) {
//     echo "El IBAN es válido.";
// } else {
//     echo "El IBAN no es válido.";
// }

function validarIBAN($iban)
{
    // Eliminar espacios y convertir a mayúsculas
    $iban = strtoupper(str_replace(' ', '', $iban));

    // Longitudes de IBAN para países europeos
    $longitudes = [
        'AL' => 28, 'AD' => 24, 'AT' => 20, 'AZ' => 28, 'BE' => 16,
        'BG' => 22, 'BH' => 22, 'HR' => 21, 'CY' => 28, 'CZ' => 24,
        'DK' => 18, 'EE' => 20, 'FI' => 18, 'FR' => 27, 'DE' => 22,
        'GI' => 23, 'GR' => 27, 'HU' => 28, 'IS' => 26, 'IE' => 22,
        'IT' => 27, 'LV' => 21, 'LI' => 21, 'LT' => 20, 'LU' => 20,
        'MT' => 31, 'MD' => 24, 'MC' => 27, 'ME' => 22, 'NL' => 18,
        'NO' => 15, 'PL' => 28, 'PT' => 25, 'RO' => 24, 'SM' => 27,
        'SK' => 24, 'SI' => 19, 'ES' => 24, 'SE' => 24, 'CH' => 21,
        'TR' => 26, 'GB' => 22, 'UA' => 29,
    ];

    // Verificar si el país es válido y la longitud es correcta
    $pais = substr($iban, 0, 2);
    if (! array_key_exists($pais, $longitudes) || strlen($iban) != $longitudes[$pais]) {
        return false; // País no válido o longitud incorrecta
    }

    // Mover los primeros cuatro caracteres al final
    $ibanReordenado = substr($iban, 4) . substr($iban, 0, 4);

    // Reemplazar letras por números (A=10, B=11, ..., Z=35)
    $ibanNumerico = '';
    for ($i = 0; $i < strlen($ibanReordenado); $i++) {
        $char = $ibanReordenado[$i];
        if (ctype_alpha($char)) {
            $ibanNumerico .= ord($char) - 55; // A=10, B=11, ..., Z=35
        } else {
            $ibanNumerico .= $char; // Números se mantienen igual
        }
    }

    // Realizar el cálculo de módulo 97
    return bcmod($ibanNumerico, '97') === '1';
}



// // Ejemplo de uso
// $pais         = 'ES'; // Cambia esto por el código de país que desees
// $ibanGenerado = generarIBAN($pais);
// echo "IBAN generado: " . $ibanGenerado;

function generarIBAN($pais)
{
    // Definimos la longitud del IBAN para algunos países (puedes agregar más)
    $longitudesIBAN = [
        'ES' => 24, // España
        'FR' => 27, // Francia
        'DE' => 22, // Alemania
        'IT' => 27, // Italia
        'GB' => 22, // Reino Unido
                    // Agrega más países según sea necesario
    ];

    // Verificamos si el país es válido
    if (! array_key_exists($pais, $longitudesIBAN)) {
        return "Código de país no válido.";
    }

    // Longitud total del IBAN
    $longitud = $longitudesIBAN[$pais];

    // Generamos el número de control (2 dígitos)
    $numeroControl = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);

                                     // Generamos el número de cuenta (restante de la longitud)
    $longitudCuenta = $longitud - 4; // 2 letras + 2 dígitos
    $numeroCuenta   = '';
    for ($i = 0; $i < $longitudCuenta; $i++) {
        $numeroCuenta .= rand(0, 9); // Generamos dígitos aleatorios
    }

    // Formamos el IBAN
    $iban = strtoupper($pais . $numeroControl . $numeroCuenta);

    return $iban;
}
