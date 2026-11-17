<?php

namespace Tests\Feature\Pruebas;

use App\Livewire\Evaluaciones\Lista as EvaluacionesLista;
use App\Livewire\Pruebas\Formulario;
use App\Livewire\Pruebas\ImportarNotas;
use App\Livewire\Pruebas\Lista;
use App\Livewire\Pruebas\Notas;
use App\Livewire\Pruebas\NotasPartes;
use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Curso;
use App\Models\Evaluacion;
use App\Models\Horario;
use App\Models\Prueba;
use App\Models\PruebaNota;
use App\Models\TipoHorario;
use App\Services\Horarios\HorarioEliminador;
use App\Services\Horarios\HorarioExportador;
use App\Services\Horarios\ImportadorZipHorario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PruebasTest extends TestCase
{
    use RefreshDatabase;

    private Horario $horario;
    private Asignatura $mates;
    private Curso $curso;
    private Evaluacion $primera;
    private Evaluacion $segunda;
    private AsignaturaAlumno $apunte;

    protected function setUp(): void
    {
        parent::setUp();

        // Hoy cae en el curso y en la primera evaluación.
        $this->travelTo('2026-11-15 10:00:00');

        $this->horario = Horario::create(['nombre' => 'Profesor', 'tipo_horario_id' => TipoHorario::PROFESOR, 'duracion_sesion_minutos' => 50]);
        $this->mates = Asignatura::create(['horario_id' => $this->horario->id, 'nombre' => 'Matemáticas', 'estado_id' => Asignatura::ESTADO_ACTIVO]);
        $this->curso = Curso::create(['horario_id' => $this->horario->id, 'nombre' => '2026/2027', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-06-30', 'estado_id' => 1]);
        $this->primera = Evaluacion::create(['curso_id' => $this->curso->id, 'nombre' => '1ª', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-20', 'estado_id' => 1]);
        $this->segunda = Evaluacion::create(['curso_id' => $this->curso->id, 'nombre' => '2ª', 'fecha_inicio' => '2027-01-08', 'fecha_fin' => '2027-03-20', 'estado_id' => 1]);

        $alumno = Alumno::create(['horario_id' => $this->horario->id, 'nombre' => 'Ana', 'apellido1' => 'García', 'apellido2' => 'López', 'fecha_nacimiento' => '2010-05-04']);
        $this->apunte = AsignaturaAlumno::create(['asignatura_id' => $this->mates->id, 'alumno_id' => $alumno->id, 'grupo' => 'A', 'estado_id' => 1]);

        session(['horario_actual_id' => $this->horario->id]);
    }

    private function prueba(string $nombre, Evaluacion $evaluacion, string $fecha, string $maxima = '30', string $peso = '40')
    {
        return Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('crear', $evaluacion->id)
            ->set('nombre', $nombre)
            ->set('fecha', $fecha)
            ->set('puntuacion_maxima', $maxima)
            ->set('peso', $peso)
            ->call('guardar');
    }

    public function test_la_prueba_valida_fecha_peso_y_nombre(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10')->assertHasNoErrors();
        $this->prueba('Tema 1', $this->primera, '2026-10-11')->assertHasErrors(['nombre']);
        $this->prueba('Tema 2', $this->primera, '2027-02-01')->assertHasErrors(['fecha']);
        $this->prueba('Tema 2', $this->primera, '2026-10-11', peso: '101')->assertHasErrors(['peso']);
        $this->prueba('Tema 2', $this->primera, '2026-10-11', maxima: '0')->assertHasErrors(['puntuacion_maxima']);
        // El mismo nombre en otra evaluación sí vale, y la coma decimal también.
        $this->prueba('Tema 1', $this->segunda, '2027-02-01', '7,5', '12,5')->assertHasNoErrors();

        $this->assertSame(2, Prueba::count());
        $this->assertSame(7.5, Prueba::where('evaluacion_id', $this->segunda->id)->first()->puntuacion_maxima);
    }

    public function test_la_lista_arranca_y_se_restablece_con_el_curso_y_la_evaluacion_de_hoy(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        $this->prueba('Tema 5', $this->segunda, '2027-02-01');

        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSet('filtros.curso_id', $this->curso->id)
            ->assertSet('filtros.evaluacion_id', $this->primera->id)
            ->assertSee('Tema 1')
            ->assertDontSee('Tema 5')
            ->set('filtros.evaluacion_id', $this->segunda->id)
            ->assertSee('Tema 5')
            ->assertDontSee('Tema 1')
            // Cambiar de curso vacía la evaluación.
            ->set('filtros.curso_id', '')
            ->assertSet('filtros.evaluacion_id', '')
            ->assertSee('Tema 1')
            ->assertSee('Tema 5')
            ->call('borrarFiltro')
            ->assertSet('filtros.evaluacion_id', $this->primera->id);

        // Lo elegido no se recuerda: se vuelve a entrar con lo de hoy.
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->set('filtros.evaluacion_id', $this->segunda->id);
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSet('filtros.evaluacion_id', $this->primera->id);

        // Entre evaluaciones hay curso pero no evaluación; fuera de todo curso, nada.
        $this->travelTo('2026-12-28');
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSet('filtros.curso_id', $this->curso->id)
            ->assertSet('filtros.evaluacion_id', '');

        $this->travelTo('2027-08-03');
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSet('filtros.curso_id', '')
            ->assertSet('filtros.evaluacion_id', '');
    }

    public function test_la_suma_de_pesos_sale_en_rojo_solo_si_pasa_de_100(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10', peso: '60');
        $this->prueba('Tema 2', $this->primera, '2026-10-20', peso: '40');

        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSeeInOrder(['Suma de pesos de la evaluación', '100 %'])
            ->assertDontSeeHtml('text-red-600');

        $this->prueba('Tema 3', $this->primera, '2026-10-30', peso: '10')->assertHasNoErrors();

        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSeeInOrder(['Suma de pesos de la evaluación', '110 %'])
            ->assertSeeHtml('text-red-600');
    }

    public function test_las_notas_se_calculan_sobre_10_y_se_retocan(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        $prueba = Prueba::firstOrFail();
        $id = $this->apunte->id;

        $notas = Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $prueba])
            ->assertSee('García López, Ana')
            ->set("notas.{$id}.nota", '20')
            ->assertSet("notas.{$id}.nota10", '6,67');

        $this->assertSame(6.67, PruebaNota::firstOrFail()->nota_sobre_10);

        // Flechas: al entero siguiente y al anterior.
        $notas->call('redondear', $id, 1)->assertSet("notas.{$id}.nota10", '7');
        $notas->call('redondear', $id, -1)->assertSet("notas.{$id}.nota10", '6');

        // A mano, y se queda así.
        $notas->set("notas.{$id}.nota10", '6,5')->assertHasNoErrors();
        $this->assertSame(6.5, PruebaNota::firstOrFail()->nota_sobre_10);
        $this->assertSame(20.0, PruebaNota::firstOrFail()->nota);

        // Tocar la nota sobre 30 recalcula la de 10.
        $notas->set("notas.{$id}.nota", '22,5')->assertSet("notas.{$id}.nota10", '7,5');

        // Fuera de rango no se guarda.
        $notas->set("notas.{$id}.nota", '31')->assertHasErrors(["notas.{$id}.nota"]);
        $notas->set("notas.{$id}.nota10", '11')->assertHasErrors(["notas.{$id}.nota10"]);
        $this->assertSame(22.5, PruebaNota::firstOrFail()->nota);
        $this->assertSame(7.5, PruebaNota::firstOrFail()->nota_sobre_10);

        // Vaciar la nota la quita.
        $notas->set("notas.{$id}.nota", '')->assertSet("notas.{$id}.nota10", '');
        $this->assertSame(0, PruebaNota::count());
    }

    public function test_cambiar_la_puntuacion_maxima_recalcula_las_notas(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        $prueba = Prueba::firstOrFail();
        PruebaNota::create(['prueba_id' => $prueba->id, 'asignatura_alumno_id' => $this->apunte->id, 'nota' => 20, 'nota_sobre_10' => 7]);

        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $prueba->id)
            ->assertSet('tieneNotas', true)
            ->assertSee('se pierden las retocadas a mano')
            ->set('puntuacion_maxima', '15')
            ->call('guardar')
            ->assertHasErrors(['puntuacion_maxima']);

        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $prueba->id)
            ->set('puntuacion_maxima', '40')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(5.0, PruebaNota::firstOrFail()->nota_sobre_10);
    }

    public function test_lo_que_tiene_notas_o_pruebas_no_se_borra(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        $prueba = Prueba::firstOrFail();
        $nota = PruebaNota::create(['prueba_id' => $prueba->id, 'asignatura_alumno_id' => $this->apunte->id, 'nota' => 20, 'nota_sobre_10' => 6.67]);

        Livewire::test(Lista::class, ['asignatura' => $this->mates])->call('ejecutarEliminarFisico', $prueba->id);
        Livewire::test(EvaluacionesLista::class, ['curso' => $this->curso])->call('ejecutarEliminarFisico', $this->primera->id);
        $this->apunte->update(['estado_id' => AsignaturaAlumno::ESTADO_BAJA]);
        Livewire::test(\App\Livewire\Alumnos\Lista::class, ['asignatura' => $this->mates])->call('ejecutarEliminarFisico', $this->apunte->id);

        $this->assertSame(1, Prueba::count());
        $this->assertSame(2, Evaluacion::count());
        $this->assertSame(1, AsignaturaAlumno::count());

        // El alumno de baja con nota sigue saliendo en las notas de la prueba.
        Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $prueba])->assertSee('García López, Ana');

        $nota->delete();
        Livewire::test(Lista::class, ['asignatura' => $this->mates])->call('ejecutarEliminarFisico', $prueba->id);
        $this->assertSame(0, Prueba::count());
    }

    public function test_el_zip_y_el_borrado_del_horario_llevan_pruebas_y_notas(): void
    {
        Storage::fake('coms');
        Storage::fake('local');

        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        PruebaNota::create(['prueba_id' => Prueba::firstOrFail()->id, 'asignatura_alumno_id' => $this->apunte->id, 'nota' => 20, 'nota_sobre_10' => 7]);

        $zip = app(HorarioExportador::class)->exportar($this->horario);
        Storage::disk('local')->put('tmp/horario.zip', Storage::disk('coms')->get($zip));
        app(ImportadorZipHorario::class)->importar('tmp/horario.zip');

        $copia = Horario::where('id', '!=', $this->horario->id)->firstOrFail();
        $pruebaCopia = Prueba::whereHas('asignatura', fn ($q) => $q->where('horario_id', $copia->id))->sole();
        $notaCopia = $pruebaCopia->notas()->sole();

        $this->assertSame($copia->id, $pruebaCopia->evaluacion->curso->horario_id);
        $this->assertSame($copia->id, $notaCopia->apunte->alumno->horario_id);
        $this->assertSame(7.0, $notaCopia->nota_sobre_10);

        app(HorarioEliminador::class)->eliminar($this->horario);

        $this->assertSame(1, Prueba::count());
        $this->assertSame(1, PruebaNota::count());
    }

    public function test_las_pruebas_se_ordenan_arrastrando(): void
    {
        $this->prueba('A', $this->primera, '2026-10-10', peso: '10');
        $this->prueba('B', $this->primera, '2026-10-20', peso: '10');
        $this->prueba('C', $this->primera, '2026-10-30', peso: '10');
        $this->prueba('Otra evaluación', $this->segunda, '2027-02-01', peso: '10');
        [$a, $b, $c] = Prueba::where('evaluacion_id', $this->primera->id)->orderBy('orden')->pluck('id')->all();

        $orden = fn () => Prueba::where('asignatura_id', $this->mates->id)->orderBy('orden')->pluck('nombre')->all();
        $this->assertSame(['A', 'B', 'C', 'Otra evaluación'], $orden());

        $lista = Livewire::test(Lista::class, ['asignatura' => $this->mates])->assertSeeInOrder(['A', 'B', 'C']);

        // Queda antes o después de la fila donde se suelta, según la mitad en la que se suelte.
        $lista->call('soltar', $a, $c, 'despues')->assertSeeInOrder(['B', 'C', 'A']);
        $this->assertSame(['B', 'C', 'A', 'Otra evaluación'], $orden());

        $lista->call('soltar', $a, $b, 'antes')->assertSeeInOrder(['A', 'B', 'C']);
        $this->assertSame(['A', 'B', 'C', 'Otra evaluación'], $orden());

        // Con otro orden en pantalla no se arrastra.
        $lista->call('ordenar', 'fecha')->call('soltar', $a, $c, 'despues');
        $this->assertSame(['A', 'B', 'C', 'Otra evaluación'], $orden());
    }

    /** Una prueba con partes «UD1» en la primera evaluación, con dos partes de 40 % y 60 %. */
    private function ud1ConPartes(): array
    {
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('crear', $this->primera->id)
            ->set('nombre', 'UD1')->set('fecha', '2026-10-30')->set('peso', '15')
            ->set('con_partes', true)
            ->call('guardar')->assertHasNoErrors();
        $ud1 = Prueba::where('nombre', 'UD1')->firstOrFail();

        foreach ([['UD1 Teoría', '20', '40'], ['UD1 Práctica', '10', '60']] as [$nombre, $maxima, $peso]) {
            Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
                ->call('crear', $this->primera->id, $ud1->id)
                ->set('nombre', $nombre)->set('fecha', '2026-10-20')->set('puntuacion_maxima', $maxima)->set('peso', $peso)
                ->call('guardar')->assertHasNoErrors();
        }

        return [$ud1, Prueba::where('nombre', 'UD1 Teoría')->firstOrFail(), Prueba::where('nombre', 'UD1 Práctica')->firstOrFail()];
    }

    private function nota(Prueba $prueba, float $sobre10, ?AsignaturaAlumno $apunte = null): void
    {
        PruebaNota::updateOrCreate(
            ['prueba_id' => $prueba->id, 'asignatura_alumno_id' => ($apunte ?? $this->apunte)->id],
            ['nota' => $sobre10 * $prueba->puntuacion_maxima / 10, 'nota_sobre_10' => $sobre10]
        );
    }

    public function test_la_prueba_con_partes_no_tiene_puntuacion_y_sus_partes_cuelgan_de_ella(): void
    {
        [$ud1, $teoria, $practica] = $this->ud1ConPartes();

        $this->assertTrue($ud1->con_partes);
        $this->assertNull($ud1->puntuacion_maxima);
        $this->assertSame(5.0, $ud1->nota_minima);
        $this->assertSame([$teoria->id, $practica->id], $ud1->partes->modelKeys());
        $this->assertSame($this->primera->id, $teoria->evaluacion_id);

        // En la suma de la evaluación solo cuenta la prueba (15), no sus partes; las partes suman 100.
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSeeInOrder(['UD1', 'UD1 Teoría', 'UD1 Práctica'])
            ->assertSeeHtml('<span class="font-semibold">15 %</span>')
            ->assertSeeHtml('<span class="font-semibold">100 %</span>');

        // Con una parte de baja suman 40: en rojo.
        $practica->update(['estado_id' => Prueba::ESTADO_BAJA]);
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->set('filtros.estado_id', '')
            ->assertSeeHtml('<span class="font-semibold text-red-600">40 %</span>');

        // Con partes dentro no deja de ser «con partes», y no tiene pantalla de notas propia.
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $ud1->id)
            ->set('con_partes', false)->set('puntuacion_maxima', '10')
            ->call('guardar')->assertHasErrors(['con_partes']);
        Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $ud1])->assertStatus(404);
        Livewire::test(NotasPartes::class, ['asignatura' => $this->mates, 'prueba' => $teoria])->assertStatus(404);
    }

    public function test_la_nota_de_la_prueba_con_partes_es_la_media_con_minimo_por_parte(): void
    {
        [$ud1, $teoria, $practica] = $this->ud1ConPartes();
        $final = fn () => $ud1->fresh()->notasFinales()[$this->apunte->id]['final'] ?? null;

        // Sin nota en ninguna parte no hay nota final, pero el alumno sale en la lista.
        $this->assertNull($final());
        Livewire::test(NotasPartes::class, ['asignatura' => $this->mates, 'prueba' => $ud1])
            ->assertSee('García')
            ->assertDontSeeHtml('id="final-'.$this->apunte->id.'"');

        // Las dos partes aprobadas: la media con sus pesos (40 % y 60 %).
        $this->nota($teoria, 6);
        $this->nota($practica, 8);
        $this->assertSame(7.2, $final());

        // Una parte suspensa y media aprobada: 4. Media suspensa: se queda como sale.
        $this->nota($teoria, 4);
        $this->assertSame(4.0, $final());           // 4·0,4 + 8·0,6 = 6,4
        $this->nota($practica, 3);
        $this->assertSame(3.4, $final());
        $this->nota($teoria, 4.5);
        $this->nota($practica, 4.5);
        $this->assertSame(4.5, $final());

        // La parte sin nota cuenta como 0: 10 y vacío da 4.
        $this->nota($teoria, 10);
        PruebaNota::where('prueba_id', $practica->id)->delete();
        $this->assertSame(4.0, $final());           // media 4, y suspensa

        $this->nota($practica, 10);
        PruebaNota::where('prueba_id', $teoria->id)->delete();
        $this->assertSame(4.0, $final());           // media 6, aprobada con una parte a 0

        // Sin mínimo, la media tal cual; con otro mínimo, el que se ponga.
        $ud1->update(['nota_minima' => 0]);
        $this->assertSame(6.0, $final());
        $this->nota($teoria, 5);
        $ud1->update(['nota_minima' => 6]);
        $this->assertSame(4.0, $final());           // media 8, pero la teoría no llega a 6

        // Si falta una parte entera, se pondera con el peso que hay.
        $ud1->update(['nota_minima' => 5]);
        $practica->update(['estado_id' => Prueba::ESTADO_BAJA]);
        $this->nota($teoria, 7);
        $this->assertSame(7.0, $final());
    }

    public function test_la_nota_final_se_retoca_y_el_retoque_vale_mientras_no_cambie_la_media(): void
    {
        [$ud1, $teoria, $practica] = $this->ud1ConPartes();
        $this->nota($teoria, 6);
        $this->nota($practica, 8);
        $id = $this->apunte->id;
        $final = fn () => $ud1->fresh()->notasFinales()[$id];

        $pantalla = Livewire::test(NotasPartes::class, ['asignatura' => $this->mates, 'prueba' => $ud1])
            ->assertSet("finales.{$id}", '7,2');

        $pantalla->call('redondear', $id, 1)->assertSet("finales.{$id}", '8');
        $this->assertSame([7.2, 8.0, true], [$final()['calculada'], $final()['final'], $final()['retocada']]);
        $pantalla->call('redondear', $id, -1)->assertSet("finales.{$id}", '7');

        $pantalla->set("finales.{$id}", '7,5')->assertHasNoErrors();
        $this->assertSame(7.5, $final()['final']);
        $pantalla->set("finales.{$id}", '11')->assertHasErrors(["finales.{$id}"]);
        $this->assertSame(7.5, $final()['final']);

        // Vacía vuelve a la media.
        $pantalla->set("finales.{$id}", '')->assertSet("finales.{$id}", '7,2');
        $this->assertFalse($final()['retocada']);
        $this->assertSame(0, $ud1->notas()->count());

        // Al cambiar la nota de una parte, el retoque deja de valer.
        $pantalla->set("finales.{$id}", '9');
        $this->nota($teoria, 7);
        $this->assertSame(7.6, $final()['final']);
        $this->assertFalse($final()['retocada']);
    }

    public function test_arrastrando_se_meten_y_se_sacan_partes(): void
    {
        [$ud1, $teoria, $practica] = $this->ud1ConPartes();
        $this->prueba('Suelta', $this->primera, '2026-11-01', peso: '10');
        $this->prueba('De la segunda', $this->segunda, '2027-02-01', peso: '10');
        $suelta = Prueba::where('nombre', 'Suelta')->firstOrFail();
        $segunda = Prueba::where('nombre', 'De la segunda')->firstOrFail();
        $this->nota($suelta, 7);

        $arbol = fn () => Prueba::where('asignatura_id', $this->mates->id)->orderBy('orden')->get()
            ->map(fn (Prueba $p) => ($p->prueba_id ? '  ' : '').$p->nombre)->all();

        $lista = Livewire::test(Lista::class, ['asignatura' => $this->mates]);

        // Dentro de la prueba con partes: entra la última, con sus notas.
        $lista->call('soltar', $suelta->id, $ud1->id, 'dentro');
        $this->assertSame(['UD1', '  UD1 Teoría', '  UD1 Práctica', '  Suelta', 'De la segunda'], $arbol());
        $this->assertSame(1, $suelta->notas()->count());

        // Al lado de una parte: se queda dentro, en ese sitio.
        $lista->call('soltar', $suelta->id, $teoria->id, 'antes');
        $this->assertSame(['UD1', '  Suelta', '  UD1 Teoría', '  UD1 Práctica', 'De la segunda'], $arbol());

        // Al lado de una prueba suelta: sale de la prueba.
        $lista->call('soltar', $suelta->id, $ud1->id, 'antes');
        $this->assertSame(['Suelta', 'UD1', '  UD1 Teoría', '  UD1 Práctica', 'De la segunda'], $arbol());
        $this->assertNull($suelta->fresh()->prueba_id);

        // La prueba con partes se mueve con sus partes detrás.
        $lista->call('soltar', $ud1->id, $segunda->id, 'despues');
        $this->assertSame(['Suelta', 'De la segunda', 'UD1', '  UD1 Teoría', '  UD1 Práctica'], $arbol());

        // No entra: otra evaluación, una prueba sin partes como destino, o una prueba con partes dentro de otra.
        $lista->call('soltar', $segunda->id, $ud1->id, 'dentro');
        $lista->call('soltar', $teoria->id, $suelta->id, 'dentro');
        $lista->call('soltar', $ud1->id, $teoria->id, 'antes');
        $this->assertSame(['Suelta', 'De la segunda', 'UD1', '  UD1 Teoría', '  UD1 Práctica'], $arbol());

        // Con partes dentro no se borra; vacía sí, y se lleva sus retoques.
        $this->nota($teoria, 6);
        Livewire::test(NotasPartes::class, ['asignatura' => $this->mates, 'prueba' => $ud1])->call('redondear', $this->apunte->id, 1);
        $lista->call('ejecutarEliminarFisico', $ud1->id);
        $this->assertNotNull($ud1->fresh());
        $lista->call('soltar', $teoria->id, $suelta->id, 'despues')->call('soltar', $practica->id, $suelta->id, 'despues');
        $lista->call('ejecutarEliminarFisico', $ud1->id);
        $this->assertNull($ud1->fresh());
    }

    public function test_el_zip_y_el_pdf_llevan_las_partes(): void
    {
        [$ud1, $teoria, $practica] = $this->ud1ConPartes();
        // La parte, con un id menor que el de su prueba: en el ZIP va antes que ella.
        $this->prueba('Vieja', $this->primera, '2026-10-05', peso: '10');
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('crear', $this->primera->id)
            ->set('nombre', 'UD2')->set('fecha', '2026-11-30')->set('peso', '15')->set('con_partes', true)
            ->call('guardar')->assertHasNoErrors();
        $vieja = Prueba::where('nombre', 'Vieja')->firstOrFail();
        $ud2 = Prueba::where('nombre', 'UD2')->firstOrFail();
        Livewire::test(Lista::class, ['asignatura' => $this->mates])->call('soltar', $vieja->id, $ud2->id, 'dentro');
        $this->assertSame($ud2->id, $vieja->fresh()->prueba_id);

        $this->nota($teoria, 6);
        $this->nota($practica, 8);

        $pdf = app(\App\Http\Controllers\PruebasNotasPdfController::class)(
            \Illuminate\Http\Request::create('/', 'GET', ['evaluacion_id' => $this->primera->id]),
            $this->mates
        );
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        Storage::fake('coms');
        Storage::fake('local');
        $zip = app(HorarioExportador::class)->exportar($this->horario);
        Storage::disk('local')->put('tmp/horario.zip', Storage::disk('coms')->get($zip));
        app(ImportadorZipHorario::class)->importar('tmp/horario.zip');

        $copia = Horario::where('id', '!=', $this->horario->id)->firstOrFail();
        $copiadas = Prueba::whereIn('asignatura_id', Asignatura::where('horario_id', $copia->id)->select('id'))->get()->keyBy('nombre');
        $this->assertCount(5, $copiadas);
        $this->assertSame($copiadas['UD1']->id, $copiadas['UD1 Teoría']->prueba_id);
        $this->assertSame($copiadas['UD2']->id, $copiadas['Vieja']->prueba_id);
        $this->assertTrue($copiadas['UD1']->con_partes);
        $this->assertSame(7.2, $copiadas['UD1']->notasFinales()[AsignaturaAlumno::whereIn('asignatura_id', Asignatura::where('horario_id', $copia->id)->select('id'))->firstOrFail()->id]['final']);

        app(HorarioEliminador::class)->eliminar($copia);
        $this->assertSame(5, Prueba::count());
    }

    public function test_importar_notas_de_un_csv(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        $prueba = Prueba::firstWhere('nombre', 'Tema 1');

        $apunte = function (string $nombre, string $apellido1, string $apellido2) {
            $alumno = Alumno::create(['horario_id' => $this->horario->id, 'nombre' => $nombre, 'apellido1' => $apellido1, 'apellido2' => $apellido2, 'fecha_nacimiento' => '2010-01-01']);

            return AsignaturaAlumno::create(['asignatura_id' => $this->mates->id, 'alumno_id' => $alumno->id, 'grupo' => 'A', 'estado_id' => 1]);
        };
        $yerai = $apunte('Yerai', 'de la Torre', 'Gómez');
        $sergio = $apunte('Sergio', 'García', 'Orvich');
        PruebaNota::create(['prueba_id' => $prueba->id, 'asignatura_alumno_id' => $sergio->id, 'nota' => 3, 'nota_sobre_10' => 1]);

        $csv = <<<'CSV'
            Apelido(s),Nome,"Enderezo de correo",Departamento,Estado,"Arrancado o",Completado,"Tempo levado","Cualificación/10,00","P. 1 /0,33","P. 2 /0,33"
            "de la Torre Gómez",Yerai,y@example.com,,Finalizada,"22 de outubro de 2026  1:02 PM","22 de outubro de 2026  1:42 PM","39 mins 59 segs.","3,50","'-0,17","0,33"
            "Garcia Orvich",Sergio,s@example.com,,Finalizada,"22 de outubro de 2026  1:03 PM","22 de outubro de 2026  1:16 PM","13 mins 42 segs.","8,17","0,33",-
            "Pardo Rey",Kevin,k@example.com,,Finalizada,"22 de outubro de 2026  1:04 PM","22 de outubro de 2026  1:58 PM","53 mins 45 segs.","9,00","0,33","0,33"
            "García López",Ana,a@example.com,,"En curso","22 de outubro de 2026  1:04 PM",-,-,-,-,-
            "Media xeral",,,,,,,,"5,90","0,24","0,24"
            CSV;

        $modal = Livewire::test(ImportarNotas::class, ['pruebaId' => $prueba->id])
            ->call('abrirModal')
            ->set('texto', 'esto no es un csv')
            ->call('revisar')
            ->assertHasErrors(['texto'])
            ->set('texto', $csv)
            ->call('revisar')
            ->assertHasNoErrors()
            ->assertSet('revisado', true);

        // La media no cuenta; Kevin no está en la asignatura y Ana no tiene nota.
        $filas = $modal->get('filas');
        $this->assertCount(4, $filas);
        $this->assertSame([null, null, 'No está en la asignatura', 'Sin nota'], array_column($filas, 'error'));
        $this->assertEquals(10.5, $filas[0]['nota_prueba']);
        $this->assertEquals(3, $filas[1]['nota_actual']);

        $modal->call('importar')->assertDispatched('notas-importadas')->assertSet('abrir', false);

        // La prueba es sobre 30: 3,50 sobre 10 son 10,5; la nota que había se sustituye.
        $this->assertEquals([10.5, 3.5], [$yerai->notas()->first()->nota, $yerai->notas()->first()->nota_sobre_10]);
        $this->assertEquals([24.51, 8.17], [$sergio->notas()->first()->nota, $sergio->notas()->first()->nota_sobre_10]);
        $this->assertSame(2, PruebaNota::where('prueba_id', $prueba->id)->count());

        // La pantalla de notas recoge lo importado y enseña el botón.
        Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $prueba])
            ->assertSeeHtml('btn-importar-notas')
            ->assertSet("notas.{$yerai->id}.nota", '10,5')
            ->dispatch('notas-importadas')
            ->assertSet("notas.{$sergio->id}.nota10", '8,17');
    }

    public function test_importar_notas_de_un_listado_copiado_de_la_pantalla(): void
    {
        $this->prueba('Tema 1', $this->primera, '2026-10-10');
        $prueba = Prueba::firstWhere('nombre', 'Tema 1');

        // Mariana acaba igual que Ana: la línea «Ana García López» es de Ana, que coincide entera.
        $alumna = Alumno::create(['horario_id' => $this->horario->id, 'nombre' => 'Mariana', 'apellido1' => 'García', 'apellido2' => 'López', 'fecha_nacimiento' => '2010-01-01']);
        $mariana = AsignaturaAlumno::create(['asignatura_id' => $this->mates->id, 'alumno_id' => $alumna->id, 'grupo' => 'A', 'estado_id' => 1]);

        $listado = "Nome / Apelido(s)\nEnderezo de correo\nDepartamento\nArdora[Eliminación en curso] MEDIDAS\nTarefaTarefa de aula\nProbaExamen TEST - UD1\n"
            ."Ana Garcia López\nana@example.com\n-\n 5,50\n 3,83\n"
            ."MGMariana García López\nmariana@example.com\n-\n-\n 10,00\n"
            ."KPKevin Pardo Rey\nkevin@example.com\n-\n 5,00\n-\n"
            ."Media xeral\t\n-\n5,66 (32)\n";

        $modal = Livewire::test(ImportarNotas::class, ['pruebaId' => $prueba->id])
            ->call('abrirModal')
            ->set('texto', $listado)
            ->call('revisar')
            ->assertHasNoErrors()
            // Solo las columnas con alguna nota; la elige quien importa, y hasta entonces no hay nada que importar.
            ->assertSet('columnas', [2 => 'Tarefa de aula', 3 => 'Examen TEST - UD1'])
            ->assertSet('columna', null)
            ->assertSeeHtml('btn-columna-notas-2')
            ->assertDontSeeHtml('btn-confirmar-importar-notas')
            // Sin columna elegida, Importar no guarda nada.
            ->call('importar')
            ->assertSet('abrir', true)
            ->set('columna', 3);

        $this->assertSame(0, PruebaNota::where('prueba_id', $prueba->id)->count());

        $this->assertSame([null, null, 'Sin nota'], array_column($modal->get('filas'), 'error'));
        $this->assertSame([$this->apunte->id, $mariana->id, null], array_column($modal->get('filas'), 'apunte_id'));

        $modal->set('columna', 2);
        $this->assertSame([null, 'Sin nota', 'No está en la asignatura'], array_column($modal->get('filas'), 'error'));

        $modal->call('importar');

        // Sobre 10 en una prueba sobre 30.
        $this->assertSame(1, PruebaNota::where('prueba_id', $prueba->id)->count());
        $this->assertEquals([16.5, 5.5], [$this->apunte->notas()->first()->nota, $this->apunte->notas()->first()->nota_sobre_10]);
    }

    public function test_el_informe_de_notas_de_la_evaluacion_se_abre_como_pdf(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $this->prueba('Tema 1', $this->primera, '2026-10-10');

        // El botón solo sale con una evaluación elegida en el filtro.
        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->assertSeeHtml('btn-imprimir-notas')
            ->set('filtros.evaluacion_id', '')
            ->assertDontSeeHtml('btn-imprimir-notas');

        $respuesta = $this->get(route('asignaturas.pruebas.pdf', ['asignatura' => $this->mates->id, 'evaluacion_id' => $this->primera->id]));
        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', $respuesta->headers->get('Content-Disposition'));

        // Una evaluación de otro horario no vale.
        $otro = Horario::create(['nombre' => 'Otro', 'tipo_horario_id' => TipoHorario::PROFESOR, 'duracion_sesion_minutos' => 50]);
        $curso = Curso::create(['horario_id' => $otro->id, 'nombre' => 'X', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-06-30', 'estado_id' => 1]);
        $ajena = Evaluacion::create(['curso_id' => $curso->id, 'nombre' => '1ª', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-20', 'estado_id' => 1]);
        $this->get(route('asignaturas.pruebas.pdf', ['asignatura' => $this->mates->id, 'evaluacion_id' => $ajena->id]))->assertNotFound();
    }

    /** Una prueba que se califica con palabras: 2 = Apto / No apto, 3 = Entregado / No entregado. */
    private function conPalabras(string $nombre, int $tipo, ?Prueba $contenedora = null, string $peso = '40'): Prueba
    {
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('crear', $this->primera->id, $contenedora?->id)
            ->set('nombre', $nombre)->set('fecha', '2026-10-20')->set('peso', $peso)
            ->set('tipo_calificacion', $tipo)
            ->call('guardar')->assertHasNoErrors();

        return Prueba::where('nombre', $nombre)->firstOrFail();
    }

    public function test_apto_y_entregado_se_guardan_como_1_o_0_y_se_ven_con_palabras(): void
    {
        $id = $this->apunte->id;
        $apto = $this->conPalabras('Exposición', Prueba::CALIFICACION_APTO);
        $entrega = $this->conPalabras('Actividad 1', Prueba::CALIFICACION_ENTREGADO);

        // Son pruebas sobre 1, sin que nadie lo escriba.
        $this->assertSame(1.0, $apto->puntuacion_maxima);
        $this->assertSame(Prueba::CALIFICACION_ENTREGADO, $entrega->tipo_calificacion);
        Livewire::test(Lista::class, ['asignatura' => $this->mates])->assertSee('Apto / No apto')->assertSee('Entregado / No entregado');

        // Apto / No apto: sin calificar no hay nota; no hay casillas de números ni Importar.
        $notas = Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $apto])
            ->assertSet("notas.{$id}.valor", '')
            ->assertDontSeeHtml("nota10-{$id}")
            ->assertDontSeeHtml('btn-importar-notas')
            ->set("notas.{$id}.valor", '1');
        $this->assertSame([1.0, 10.0], [PruebaNota::firstOrFail()->nota, PruebaNota::firstOrFail()->nota_sobre_10]);
        $notas->set("notas.{$id}.valor", '0');
        $this->assertSame([0.0, 0.0], [PruebaNota::firstOrFail()->nota, PruebaNota::firstOrFail()->nota_sobre_10]);
        $this->assertSame('No apto', $apto->notaVisible(0));
        // Un número no entra en una prueba de palabras, ni las flechas la tocan.
        $notas->set("notas.{$id}.nota", '0,5')->call('redondear', $id, 1);
        $this->assertSame(0.0, PruebaNota::firstOrFail()->nota_sobre_10);
        $notas->set("notas.{$id}.valor", '');
        $this->assertSame(0, PruebaNota::count());
        $this->assertSame('', $apto->notaVisible(null));

        // Entregado / No entregado: nunca vacío.
        $notas = Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $entrega])
            ->assertSet("notas.{$id}.valor", false)
            // (El subtítulo dice «Entregado / No entregado»: lo de la fila se distingue por el rojo.)
            ->assertSeeHtml('text-red-600')
            ->set("notas.{$id}.valor", true)
            ->assertDontSeeHtml('text-red-600');
        $this->assertSame(10.0, PruebaNota::firstOrFail()->nota_sobre_10);
        Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $entrega])->assertSet("notas.{$id}.valor", true);

        // Con notas, no se cambia cómo se califica.
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $entrega->id)
            ->set('tipo_calificacion', Prueba::CALIFICACION_NUMERICA)->set('puntuacion_maxima', '10')
            ->call('guardar')->assertHasErrors(['tipo_calificacion']);

        $notas->set("notas.{$id}.valor", false)->assertSeeHtml('text-red-600');
        $this->assertSame(0, PruebaNota::count());
        $this->assertSame('No entregado', $entrega->notaVisible(null));

        // Sin notas sí: pasa a numérica con su puntuación.
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $entrega->id)
            ->assertSet('puntuacion_maxima', '')
            ->set('tipo_calificacion', Prueba::CALIFICACION_NUMERICA)
            ->call('guardar')->assertHasErrors(['puntuacion_maxima'])
            ->set('puntuacion_maxima', '20')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame(20.0, $entrega->fresh()->puntuacion_maxima);

        // No se importan notas a una prueba de palabras.
        Livewire::test(ImportarNotas::class, ['pruebaId' => $apto->id])->call('abrirModal')->assertSet('abrir', false);
    }

    public function test_las_entregas_como_partes_cuentan_0_si_no_se_entregan(): void
    {
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('crear', $this->primera->id)
            ->set('nombre', 'Actividades')->set('fecha', '2026-10-30')->set('peso', '15')
            ->set('con_partes', true)->set('nota_minima', '0')
            ->call('guardar')->assertHasNoErrors();
        $actividades = Prueba::where('nombre', 'Actividades')->firstOrFail();
        $a1 = $this->conPalabras('A1', Prueba::CALIFICACION_ENTREGADO, $actividades, '50');
        $a2 = $this->conPalabras('A2', Prueba::CALIFICACION_ENTREGADO, $actividades, '25');
        $a3 = $this->conPalabras('A3', Prueba::CALIFICACION_APTO, $actividades, '25');
        $id = $this->apunte->id;

        // Sin entregar nada ya hay nota: un 0.
        $this->assertSame(0.0, $actividades->notasFinales()[$id]['final']);

        Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $a1])->set("notas.{$id}.valor", true);
        Livewire::test(Notas::class, ['asignatura' => $this->mates, 'prueba' => $a3])->set("notas.{$id}.valor", '1');
        $finales = $actividades->notasFinales()[$id];
        $this->assertSame([$a1->id => 10.0, $a2->id => 0.0, $a3->id => 10.0], $finales['partes']);
        $this->assertSame(7.5, $finales['final']);

        Livewire::test(NotasPartes::class, ['asignatura' => $this->mates, 'prueba' => $actividades])
            ->assertSee('No entregado')->assertSee('Apto')->assertSet("finales.{$id}", '7,5');

        // El ZIP lleva el tipo de calificación.
        Storage::fake('coms');
        Storage::fake('local');
        $zip = app(HorarioExportador::class)->exportar($this->horario);
        Storage::disk('local')->put('tmp/horario.zip', Storage::disk('coms')->get($zip));
        app(ImportadorZipHorario::class)->importar('tmp/horario.zip');
        $copia = Horario::where('id', '!=', $this->horario->id)->firstOrFail();
        $copiada = Prueba::whereIn('asignatura_id', Asignatura::where('horario_id', $copia->id)->select('id'))->where('nombre', 'A3')->firstOrFail();
        $this->assertSame(Prueba::CALIFICACION_APTO, $copiada->tipo_calificacion);

        $pdf = app(\App\Http\Controllers\PruebasNotasPdfController::class)(
            \Illuminate\Http\Request::create('/', 'GET', ['evaluacion_id' => $this->primera->id]),
            $this->mates
        );
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }
}
