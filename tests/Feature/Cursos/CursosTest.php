<?php

namespace Tests\Feature\Cursos;

use App\Livewire\Cursos\Formulario;
use App\Livewire\Cursos\Lista;
use App\Livewire\Evaluaciones\Formulario as EvaluacionFormulario;
use App\Livewire\Evaluaciones\Lista as EvaluacionesLista;
use App\Models\Curso;
use App\Models\Evaluacion;
use App\Models\Horario;
use App\Models\TipoHorario;
use App\Services\Horarios\HorarioEliminador;
use App\Services\Horarios\HorarioExportador;
use App\Services\Horarios\ImportadorZipHorario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CursosTest extends TestCase
{
    use RefreshDatabase;

    private Horario $horario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->horario = Horario::create(['nombre' => 'Profesor', 'tipo_horario_id' => TipoHorario::PROFESOR, 'duracion_sesion_minutos' => 50]);

        session(['horario_actual_id' => $this->horario->id]);
    }

    private function curso(string $nombre, string $inicio, string $fin)
    {
        return Livewire::test(Formulario::class)
            ->call('crear')
            ->set('nombre', $nombre)
            ->set('fecha_inicio', $inicio)
            ->set('fecha_fin', $fin)
            ->call('guardar');
    }

    private function evaluacion(Curso $curso, string $nombre, string $inicio, string $fin)
    {
        return Livewire::test(EvaluacionFormulario::class, ['cursoId' => $curso->id])
            ->call('crear')
            ->set('nombre', $nombre)
            ->set('fecha_inicio', $inicio)
            ->set('fecha_fin', $fin)
            ->call('guardar');
    }

    public function test_las_pantallas_solo_existen_en_horarios_de_profesor(): void
    {
        $this->curso('2026/2027', '2026-09-01', '2027-06-30')->assertHasNoErrors();
        $curso = Curso::firstOrFail();

        Livewire::test(Lista::class)->assertSee('2026/2027')->assertSee('01/09/2026');
        Livewire::test(EvaluacionesLista::class, ['curso' => $curso])->assertSee('2026/2027');

        $this->horario->update(['tipo_horario_id' => TipoHorario::ALUMNO]);

        Livewire::test(Lista::class)->assertStatus(404);
        // fresh(): el modelo de arriba ya tiene cargado el horario cuando era de profesor.
        Livewire::test(EvaluacionesLista::class, ['curso' => $curso->fresh()])->assertStatus(404);
    }

    public function test_los_cursos_no_repiten_nombre_ni_solapan_fechas(): void
    {
        $this->curso('2026/2027', '2026-09-01', '2027-06-30')->assertHasNoErrors();

        $this->curso('2026/2027', '2027-09-01', '2028-06-30')->assertHasErrors(['nombre']);
        $this->curso('Otro', '2027-06-30', '2028-06-30')->assertHasErrors(['fecha_inicio']);
        $this->curso('Otro', '2026-01-01', '2028-01-01')->assertHasErrors(['fecha_inicio']);
        $this->curso('Otro', '2027-06-30', '2027-06-01')->assertHasErrors(['fecha_fin']);
        $this->curso('2027/2028', '2027-07-01', '2028-06-30')->assertHasNoErrors();

        $this->assertSame(2, Curso::count());

        // Modificar un curso no choca consigo mismo.
        Livewire::test(Formulario::class)
            ->call('editar', Curso::where('nombre', '2026/2027')->value('id'))
            ->set('fecha_inicio', '2026-09-10')
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_las_evaluaciones_caen_dentro_del_curso_y_no_se_solapan(): void
    {
        $this->curso('2026/2027', '2026-09-01', '2027-06-30');
        $curso = Curso::firstOrFail();

        $this->evaluacion($curso, '1ª', '2026-09-01', '2026-12-20')->assertHasNoErrors();
        $this->evaluacion($curso, '1ª', '2027-01-08', '2027-03-20')->assertHasErrors(['nombre']);
        $this->evaluacion($curso, '2ª', '2026-12-20', '2027-03-20')->assertHasErrors(['fecha_inicio']);
        $this->evaluacion($curso, '2ª', '2027-01-08', '2027-07-01')->assertHasErrors(['fecha_inicio']);
        $this->evaluacion($curso, '2ª', '2027-01-08', '2027-03-20')->assertHasNoErrors();

        $this->assertSame(2, Evaluacion::count());

        // El curso no puede encogerse dejando fuera una evaluación.
        Livewire::test(Formulario::class)
            ->call('editar', $curso->id)
            ->set('fecha_inicio', '2026-10-01')
            ->call('guardar')
            ->assertHasErrors(['fecha_inicio']);
    }

    public function test_baja_y_borrado_definitivo_solo_si_nada_lo_usa(): void
    {
        $this->curso('2026/2027', '2026-09-01', '2027-06-30');
        $curso = Curso::firstOrFail();
        $this->evaluacion($curso, '1ª', '2026-09-01', '2026-12-20');
        $evaluacion = Evaluacion::firstOrFail();

        Livewire::test(Lista::class)->call('ejecutarBaja', $curso->id);
        $this->assertSame(Curso::ESTADO_BAJA, $curso->fresh()->estado_id);

        // Con evaluaciones, el curso no se borra.
        Livewire::test(Lista::class)->call('ejecutarEliminarFisico', $curso->id);
        $this->assertSame(1, Curso::count());

        Livewire::test(EvaluacionesLista::class, ['curso' => $curso])->call('ejecutarEliminarFisico', $evaluacion->id);
        $this->assertSame(0, Evaluacion::count());

        Livewire::test(Lista::class)->call('ejecutarEliminarFisico', $curso->id);
        $this->assertSame(0, Curso::count());
    }

    public function test_el_zip_y_el_borrado_del_horario_llevan_cursos_y_evaluaciones(): void
    {
        Storage::fake('coms');
        Storage::fake('local');

        $this->curso('2026/2027', '2026-09-01', '2027-06-30');
        $this->evaluacion(Curso::firstOrFail(), '1ª', '2026-09-01', '2026-12-20');

        $zip = app(HorarioExportador::class)->exportar($this->horario);
        Storage::disk('local')->put('tmp/horario.zip', Storage::disk('coms')->get($zip));
        app(ImportadorZipHorario::class)->importar('tmp/horario.zip');

        $copia = Horario::where('id', '!=', $this->horario->id)->firstOrFail();
        $cursoCopia = Curso::where('horario_id', $copia->id)->sole();
        $this->assertSame('1ª', $cursoCopia->evaluaciones()->sole()->nombre);
        $this->assertSame('2026-09-01', $cursoCopia->fecha_inicio->toDateString());

        app(HorarioEliminador::class)->eliminar($this->horario);

        $this->assertSame(1, Curso::count());
        $this->assertSame(1, Evaluacion::count());
    }
}
