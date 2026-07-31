<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provincias', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 2);
            $table->string('nombre', 50);
        });

        DB::unprepared(<<<'SQL'
INSERT INTO `provincias` (`id`, `codigo`, `nombre`) VALUES
(1,'02','Albacete'),
(2,'03','Alicante/Alacant'),
(3,'04','Almería'),
(4,'01','Araba/Álava'),
(5,'33','Asturias'),
(6,'05','Ávila'),
(7,'06','Badajoz'),
(8,'07','Balears, Illes'),
(9,'08','Barcelona'),
(10,'48','Bizkaia'),
(11,'09','Burgos'),
(12,'10','Cáceres'),
(13,'11','Cádiz'),
(14,'39','Cantabria'),
(15,'12','Castellón/Castelló'),
(16,'13','Ciudad Real'),
(17,'14','Córdoba'),
(18,'15','Coruńa, A'),
(19,'16','Cuenca'),
(20,'20','Gipuzkoa'),
(21,'17','Girona'),
(22,'18','Granada'),
(23,'19','Guadalajara'),
(24,'21','Huelva'),
(25,'22','Huesca'),
(26,'23','Jaén'),
(27,'24','León'),
(28,'25','Lleida'),
(29,'27','Lugo'),
(30,'28','Madrid'),
(31,'29','Málaga'),
(32,'30','Murcia'),
(33,'31','Navarra'),
(34,'32','Ourense'),
(35,'34','Palencia'),
(36,'35','Palmas, Las'),
(37,'36','Pontevedra'),
(38,'26','Rioja, La'),
(39,'37','Salamanca'),
(40,'38','Santa Cruz de Tenerife'),
(41,'40','Segovia'),
(42,'41','Sevilla'),
(43,'42','Soria'),
(44,'43','Tarragona'),
(45,'44','Teruel'),
(46,'45','Toledo'),
(47,'46','Valencia/València'),
(48,'47','Valladolid'),
(49,'49','Zamora'),
(50,'50','Zaragoza'),
(51,'51','Ceuta'),
(52,'52','Melilla');
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provincias');
    }
};
