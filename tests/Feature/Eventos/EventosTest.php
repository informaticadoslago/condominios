<?php

namespace Tests\Feature\Eventos;

use App\Livewire\Eventos\Formulario;
use App\Livewire\Eventos\Lista;
use App\Models\Asignatura;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\TipoEvento;
use App\Models\TipoHorario;
use App\Services\Horarios\CalendarioEventosIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventosTest extends TestCase
{
    use RefreshDatabase;

    private Horario $horario;
    private Asignatura $asignatura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->horario = Horario::create(['nombre' => 'Horario de prueba', 'tipo_horario_id' => TipoHorario::ALUMNO, 'duracion_sesion_minutos' => 50]);
        $this->asignatura = Asignatura::create(['horario_id' => $this->horario->id, 'nombre' => 'Matemáticas', 'estado_id' => Asignatura::ESTADO_ACTIVO]);

        session(['horario_actual_id' => $this->horario->id]);
    }

    public function test_el_examen_pide_asignatura(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('titulo', 'Examen')
            ->set('fecha', today()->addDay()->toDateString())
            ->set('eleccionHora', Formulario::HORA_PERSONALIZADA)
            ->set('hora', '10:00')
            ->call('guardar')
            ->assertHasErrors(['asignatura_id']);

        $this->assertSame(0, Evento::count());
    }

    public function test_un_examen_con_asignatura_propone_el_texto_y_se_guarda(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('asignatura_id', $this->asignatura->id)
            ->assertSet('titulo', 'Examen de Matemáticas')
            ->set('fecha', today()->addDay()->toDateString())
            ->set('eleccionHora', Formulario::HORA_PERSONALIZADA)
            ->set('hora', '10:00')
            ->assertSet('hora_fin', '10:50')
            ->call('guardar')
            ->assertHasNoErrors();

        $evento = Evento::firstOrFail();
        $this->assertTrue($evento->esExamen());
        $this->assertSame($this->asignatura->id, $evento->asignatura_id);
    }

    public function test_una_junta_se_guarda_sin_asignatura_y_con_la_hora_escrita(): void
    {
        $junta = TipoEvento::where('descripcion', 'Junta de evaluación')->firstOrFail();

        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('tipo_evento_id', $junta->id)
            ->assertSet('titulo', 'Junta de evaluación')
            ->assertSet('eleccionHora', Formulario::HORA_PERSONALIZADA)
            ->set('fecha', today()->addDay()->toDateString())
            ->set('hora', '16:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $evento = Evento::firstOrFail();
        $this->assertFalse($evento->esExamen());
        $this->assertNull($evento->asignatura_id);
        $this->assertSame('Junta de evaluación', $evento->titulo);

        // Se puede volver a abrir y guardar tal cual.
        Livewire::test(Formulario::class)
            ->call('editar', $evento->id)
            ->assertSet('tipo_evento_id', $junta->id)
            ->assertSet('asignatura_id', '')
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_la_lista_filtra_por_tipo_y_el_calendario_los_trae_todos(): void
    {
        $junta = TipoEvento::where('descripcion', 'Junta de evaluación')->firstOrFail();
        $base  = ['horario_id' => $this->horario->id, 'fecha' => today()->addDay()->toDateString(), 'hora' => '10:00', 'hora_fin' => '11:00'];

        Evento::create($base + ['tipo_evento_id' => TipoEvento::EXAMEN, 'asignatura_id' => $this->asignatura->id, 'titulo' => 'Examen de Matemáticas']);
        Evento::create($base + ['tipo_evento_id' => $junta->id, 'titulo' => 'Junta de la primera evaluación']);

        Livewire::test(Lista::class)
            ->call('marcarTodasColumnas')
            ->assertSee('Examen de Matemáticas')
            ->assertSee('Junta de la primera evaluación')
            ->set('filtros.tipo_evento_id', $junta->id)
            ->assertDontSee('Examen de Matemáticas')
            ->assertSee('Junta de la primera evaluación');

        $ics = app(CalendarioEventosIcs::class)->generar($this->horario);
        $this->assertStringContainsString('SUMMARY:Examen de Matemáticas', $ics);
        $this->assertStringContainsString('SUMMARY:Junta de la primera evaluación', $ics);

        // La dirección de cuando solo había exámenes sigue respondiendo.
        $this->get('/calendario/'.$this->horario->token_calendario.'/examenes.ics')->assertOk()->assertSee('Junta de la primera evaluación');
        $this->get('/calendario/'.$this->horario->token_calendario.'/eventos.ics')->assertOk();

        $vista = view('pdf.eventos', ['horario' => $this->horario, 'eventosPorMes' => Evento::porMes($this->horario->id)])->render();
        $this->assertStringContainsString('Matemáticas', $vista);
        $this->assertStringContainsString('Junta de la primera evaluación', $vista);
    }

    public function test_un_tipo_dado_de_baja_solo_se_borra_si_no_tiene_eventos(): void
    {
        $conEventos = TipoEvento::create(['descripcion' => 'Con eventos', 'estado_id' => TipoEvento::ESTADO_BAJA]);
        $sinEventos = TipoEvento::create(['descripcion' => 'Sin eventos', 'estado_id' => TipoEvento::ESTADO_BAJA]);

        Evento::create(['horario_id' => $this->horario->id, 'tipo_evento_id' => $conEventos->id, 'titulo' => 'Reunión',
            'fecha' => today()->addDay()->toDateString(), 'hora' => '10:00', 'hora_fin' => '11:00']);

        Livewire::test(\App\Livewire\Catalogos\Lista::class, ['clave' => 'tipo-eventos'])
            ->assertSee('Mayús+clic: borrar definitivamente')
            // Con eventos ni se pregunta: avisa la aplicación, sin llegar a la base de datos.
            ->call('confirmarEliminarFisico', $conEventos->id)
            ->assertNotDispatched('swalConfirm')
            ->assertDispatched('toast-error')
            ->call('ejecutarEliminarFisico', $conEventos->id)
            ->call('confirmarEliminarFisico', $sinEventos->id)
            ->assertDispatched('swalConfirm')
            ->call('ejecutarEliminarFisico', $sinEventos->id)
            ->assertDispatched('toast-success');

        $this->assertNull(TipoEvento::find($sinEventos->id));
        $this->assertNotNull(TipoEvento::find($conEventos->id));
    }
}
