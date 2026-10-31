<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('webpush.database_connection'))->table(config('webpush.table_name'), function (Blueprint $table) {
            // Nombre del dispositivo para que el usuario sepa cuál es cuál ("iPhone",
            // "Mac · Chrome"…). Se pone solo al activarlas y el usuario lo puede cambiar.
            $table->string('nombre', 60)->nullable()->after('subscribable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))->table(config('webpush.table_name'), function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
};
