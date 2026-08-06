<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tipo_documentos')->insert([
            'id'     => 10,
            'nombre' => 'Mandato SEPA',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tipo_documentos')->where('id', 10)->delete();
    }
};
