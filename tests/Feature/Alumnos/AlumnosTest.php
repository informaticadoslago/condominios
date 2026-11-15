<?php

namespace Tests\Feature\Alumnos;

use App\Livewire\Alumnos\Formulario;
use App\Livewire\Alumnos\Importar;
use App\Livewire\Alumnos\Lista;
use App\Livewire\Asignaturas\Lista as AsignaturasLista;
use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Horario;
use App\Models\TipoHorario;
use App\Services\Horarios\HorarioEliminador;
use App\Services\Horarios\HorarioExportador;
use App\Services\Horarios\ImportadorZipHorario;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlumnosTest extends TestCase
{
    use RefreshDatabase;

    private Horario $horario;
    private Asignatura $mates;
    private Asignatura $lengua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->horario = Horario::create(['nombre' => 'Profesor', 'tipo_horario_id' => TipoHorario::PROFESOR, 'duracion_sesion_minutos' => 50]);
        $this->mates = Asignatura::create(['horario_id' => $this->horario->id, 'nombre' => 'Matemáticas', 'estado_id' => Asignatura::ESTADO_ACTIVO]);
        $this->lengua = Asignatura::create(['horario_id' => $this->horario->id, 'nombre' => 'Lengua', 'estado_id' => Asignatura::ESTADO_ACTIVO]);

        session(['horario_actual_id' => $this->horario->id]);
    }

    private function alta(Asignatura $asignatura, string $fecha = '2010-05-04', string $grupo = 'a')
    {
        return Livewire::test(Formulario::class, ['asignaturaId' => $asignatura->id])
            ->call('crear')
            ->set('nombre', 'Ana')
            ->set('apellido1', 'García')
            ->set('apellido2', 'López')
            ->set('fecha_nacimiento', $fecha)
            ->set('grupo', $grupo)
            ->call('guardar');
    }

    public function test_el_boton_de_alumnos_solo_sale_en_horarios_de_profesor(): void
    {
        Livewire::test(AsignaturasLista::class)->assertSeeHtml('btn-alumnos-asignatura-'.$this->mates->id);

        $this->horario->update(['tipo_horario_id' => TipoHorario::ALUMNO]);

        Livewire::test(AsignaturasLista::class)->assertDontSeeHtml('btn-alumnos-asignatura-'.$this->mates->id);

        Livewire::test(Lista::class, ['asignatura' => $this->mates])->assertStatus(404);
    }

    public function test_la_fecha_de_nacimiento_y_el_grupo_son_obligatorios(): void
    {
        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('crear')
            ->set('nombre', 'Ana')
            ->set('apellido1', 'García')
            ->call('guardar')
            ->assertHasErrors(['fecha_nacimiento', 'grupo']);

        $this->assertSame(0, Alumno::count());
    }

    public function test_el_mismo_alumno_en_dos_asignaturas_es_un_solo_alumno(): void
    {
        $this->alta($this->mates)->assertHasNoErrors();
        $this->alta($this->lengua, grupo: 'B')->assertHasNoErrors();

        $this->assertSame(1, Alumno::count());
        $this->assertSame(2, AsignaturaAlumno::count());
        $this->assertSame('A', AsignaturaAlumno::where('asignatura_id', $this->mates->id)->value('grupo'));

        // Repetido en la misma asignatura: no se apunta dos veces.
        $this->alta($this->mates)->assertHasErrors(['nombre']);
        $this->assertSame(2, AsignaturaAlumno::count());

        // Con otra fecha de nacimiento es otro alumno.
        $this->alta($this->mates, '2011-01-01')->assertHasNoErrors();
        $this->assertSame(2, Alumno::count());
    }

    public function test_la_lista_pinta_los_alumnos_de_su_asignatura(): void
    {
        $this->alta($this->mates);

        Livewire::test(Lista::class, ['asignatura' => $this->mates])->assertSee('García')->assertSee('04/05/2010');
        Livewire::test(Lista::class, ['asignatura' => $this->lengua])->assertDontSee('García');
    }

    public function test_modificar_no_deja_convertirlo_en_otro_alumno_que_ya_existe(): void
    {
        $this->alta($this->mates);
        $this->alta($this->mates, '2011-01-01');
        $apunte = AsignaturaAlumno::orderByDesc('id')->first();

        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $apunte->id)
            ->set('fecha_nacimiento', '2010-05-04')
            ->call('guardar')
            ->assertHasErrors(['nombre']);

        Livewire::test(Formulario::class, ['asignaturaId' => $this->mates->id])
            ->call('editar', $apunte->id)
            ->set('grupo', 'c')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('C', $apunte->fresh()->grupo);
    }

    public function test_la_baja_deja_inactivo_y_el_borrado_fisico_del_ultimo_apunte_borra_al_alumno(): void
    {
        $this->alta($this->mates);
        $this->alta($this->lengua);
        $enMates = AsignaturaAlumno::where('asignatura_id', $this->mates->id)->first();
        $enLengua = AsignaturaAlumno::where('asignatura_id', $this->lengua->id)->first();

        // Activo: el borrado físico no hace nada.
        Livewire::test(Lista::class, ['asignatura' => $this->mates])->call('ejecutarEliminarFisico', $enMates->id);
        $this->assertSame(2, AsignaturaAlumno::count());

        Livewire::test(Lista::class, ['asignatura' => $this->mates])
            ->call('ejecutarBaja', $enMates->id)
            ->call('ejecutarEliminarFisico', $enMates->id);

        $this->assertSame(1, AsignaturaAlumno::count());
        $this->assertSame(1, Alumno::count());

        Livewire::test(Lista::class, ['asignatura' => $this->lengua])
            ->call('ejecutarBaja', $enLengua->id)
            ->call('ejecutarEliminarFisico', $enLengua->id);

        $this->assertSame(0, AsignaturaAlumno::count());
        $this->assertSame(0, Alumno::count());
    }

    public function test_borrar_el_horario_se_lleva_sus_alumnos(): void
    {
        $this->alta($this->mates);

        app(HorarioEliminador::class)->eliminar($this->horario);

        $this->assertSame(0, Alumno::count());
        $this->assertSame(0, AsignaturaAlumno::count());
    }

    public function test_el_zip_del_horario_lleva_los_alumnos_y_se_reconstruyen_al_importarlo(): void
    {
        Storage::fake('coms');
        Storage::fake('local');

        $this->alta($this->mates);
        $this->alta($this->lengua, grupo: 'B');

        $zip = app(HorarioExportador::class)->exportar($this->horario);
        Storage::disk('local')->put('tmp/horario.zip', Storage::disk('coms')->get($zip));

        app(ImportadorZipHorario::class)->importar('tmp/horario.zip');

        $copia = Horario::where('id', '!=', $this->horario->id)->firstOrFail();
        $alumno = Alumno::where('horario_id', $copia->id)->sole();

        $this->assertSame('García', $alumno->apellido1);
        $this->assertSame(
            ['Lengua' => 'B', 'Matemáticas' => 'A'],
            $alumno->apuntes()->with('asignatura')->get()
                ->mapWithKeys(fn ($apunte) => [$apunte->asignatura->nombre => $apunte->grupo])
                ->sortKeys()->all()
        );
        $this->assertSame($copia->id, $alumno->apuntes()->first()->asignatura->horario_id);
    }

    public function test_importar_saca_del_texto_pegado_nombres_fechas_y_grupos(): void
    {
        // Ana García López ya está en el horario (en Lengua): se añade, no se duplica.
        $this->alta($this->lengua);

        $texto = <<<'TXT'
        Acosta Bouzas, Marcos
        4º Ensinanza secundaria obrigatoria
        2026/2027
        E
        11/09/2011
        23045
        García López, Ana
        4º Ensinanza secundaria obrigatoria PDC
        2026/2027
        b
        04/05/2010
        22016
        De la Fuente Rey, María José
        4º Ensinanza secundaria obrigatoria
        2026/2027
        B
        31/02/2011
        23003
        Rey, Pablo
        2026/2027
        B
        17/02/2011
        TXT;

        $componente = Livewire::test(Importar::class, ['asignaturaId' => $this->mates->id])
            ->call('abrirModal')
            ->set('texto', $texto)
            ->call('revisar')
            ->assertSet('revisado', true)
            ->assertSee('La fecha 31/02/2011 no existe');

        // Revisar no guarda nada.
        $this->assertSame(1, Alumno::count());

        $componente->call('importar')->assertSet('abrir', false);

        $enMates = AsignaturaAlumno::with('alumno')->where('asignatura_id', $this->mates->id)->get()
            ->map(fn ($a) => "{$a->alumno->apellido1}|{$a->alumno->apellido2}|{$a->alumno->nombre}|{$a->alumno->fecha_nacimiento->toDateString()}|{$a->grupo}")
            ->sort()->values()->all();

        $this->assertSame([
            'Acosta|Bouzas|Marcos|2011-09-11|E',
            'García|López|Ana|2010-05-04|B',
            'Rey||Pablo|2011-02-17|B',
        ], $enMates);
        $this->assertSame(3, Alumno::count());

        // Repetir la importación no duplica nada.
        Livewire::test(Importar::class, ['asignaturaId' => $this->mates->id])
            ->call('abrirModal')->set('texto', $texto)->call('revisar')->call('importar');

        $this->assertSame(3, Alumno::count());
        $this->assertSame(4, AsignaturaAlumno::count());
    }

    public function test_importar_un_texto_sin_alumnos_avisa(): void
    {
        Livewire::test(Importar::class, ['asignaturaId' => $this->mates->id])
            ->call('abrirModal')
            ->set('texto', "hola\n1234")
            ->call('revisar')
            ->assertHasErrors(['texto'])
            ->assertSet('revisado', false);
    }
}
