<?php
namespace App\Helpers;

class PersistConfigHelper {


function set_deep_index_value(&$data, $data_indexes, $new_value)
{
    foreach ($data_indexes as $index) {
        if ($index == sizeof($data_indexes) - 1) {
            $data[$index] = $new_value;
        } else {
            return set_deep_index_value($data[$index], array_slice($data_indexes, 1), $new_value);
        }

    }
}

function persist_config(array | string $target, $new_value = null) : null
{
    if (is_array($target) && sizeof($target) == 1) {
        $new_value = array_values($target)[0];
        $target = array_keys($target)[0];
    }
    $array = explode('.', $target);
    $filename = app()->getConfigurationPath() . $array[0] . '.php';
    $data_indexes = array_slice($array, 1);

    $data = config($array[0]);
    set_deep_index_value($data, $data_indexes, $new_value);

    file_put_contents($filename, "<?php\n return " . var_export($data, 1) . " ;");
    \Illuminate\Support\Facades\Artisan::call('cache:clear');

}

// persist_config(['customconfig.attribute'=>'newvalue'])
// or persist_config('customconfig.attribute','newvalue')
}