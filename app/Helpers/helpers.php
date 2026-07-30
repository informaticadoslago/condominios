<?php

use Carbon\Carbon;

if (! function_exists('formatCurr')){
    function formatCurr(float $number, string $currency = 'EUR', string $locale='es_ES'): string | false
    {
        if (is_numeric($number)) {
            $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            return $fmt->formatCurrency($number, $currency);
        }
        return false;
    }
}

if (! function_exists('convertYmdToDmy')) {
    function convertYmdToDmy($date)
    {
        try {
            return \Carbon\Carbon::parse($date)->format('d-m-Y');
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (! function_exists('convertDmyToYmd')) {
    function convertDmyToYmd($date)
    {
        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (! function_exists('isMayorDeEdad')) {
    function isMayorDeEdad($date): bool
    {
        $dt = new Carbon($date);
        return $dt->diffInYears(Carbon::now()) > 18;
    }
}

// if (! function_exists('set_deep_index_value')) {
//     function set_deep_index_value(&$data, $data_indexes, $new_value)
//     {
//         dd([$data, $data_indexes, $new_value]);
//         foreach ($data_indexes as $index) {
//             if ($index == sizeof($data_indexes) - 1) {
//                 $data[$index] = $new_value;
//             } else {
//                 return set_deep_index_value($data[$index], array_slice($data_indexes, 1), $new_value);
//             }
//         }
//     }
// }

// if (! function_exists('persist_config')) {
// // persist_config(['customconfig.attribute'=>'newvalue'])
// // or persist_config('customconfig.attribute','newvalue')
//     function persist_config(array | string $target, $new_value = null)
//     {
//         if (is_array($target) && sizeof($target) == 1) {
//             $new_value = array_values($target)[0];
//             $target = array_keys($target)[0];
//         }
//         $array = explode('.', $target);
//         $filename = config_path($array[0] . '.php');        
//         $data_indexes = array_slice($array, 1);
//         $data = config($array[0]);
//         set_deep_index_value($data, $data_indexes, $new_value);
//         dd(var_export($data, 1));
//         file_put_contents($filename, "<?php\n return " . var_export($data, 1) . " ;");
//         \Illuminate\Support\Facades\Artisan::call('cache:clear');

//     }
// }
