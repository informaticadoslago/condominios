<?php
namespace Database\Seeders;

use App\Models\TipoGenero;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class GeneroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        TipoGenero::truncate();
        Schema::enableForeignKeyConstraints();

        $estados = [
            ['id' => 1, 'nombre' => 'Hombre'],
            ['id' => 2, 'nombre' => 'Mujer'],
            ['id' => 3, 'nombre' => 'Otro'],
        ];
        foreach ($estados as $estado) {
            TipoGenero::create($estado);
        }

    }
}
