<?php

namespace Tests\Feature\Pruebas;

use App\Livewire\Evaluaciones\Lista as EvaluacionesLista;
use App\Livewire\Pruebas\Formulario;
use App\Livewire\Pruebas\Lista;
use App\Livewire\Pruebas\Notas;
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

        // Hacia abajo: queda después de la fila donde se suelta. Hacia arriba: antes.
        $lista->call('mover', $a, $c)->assertSeeInOrder(['B', 'C', 'A']);
        $this->assertSame(['B', 'C', 'A', 'Otra evaluación'], $orden());

        $lista->call('mover', $a, $b)->assertSeeInOrder(['A', 'B', 'C']);
        $this->assertSame(['A', 'B', 'C', 'Otra evaluación'], $orden());

        // Con otro orden en pantalla no se arrastra.
        $lista->call('ordenar', 'fecha')->call('mover', $a, $c);
        $this->assertSame(['A', 'B', 'C', 'Otra evaluación'], $orden());
    }
}
