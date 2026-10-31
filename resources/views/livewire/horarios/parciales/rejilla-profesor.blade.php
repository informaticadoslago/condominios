{{-- Horario de profesor en escala de tiempo: cada sesión con asignatura se arrastra
     arriba o abajo dentro de su día, a saltos de 5 minutos. Puede pisar los recreos,
     pero se para al tocar otra sesión o el borde de su jornada (min/max, calculados en
     ColocacionSesiones; Rejilla::moverSesion lo vuelve a comprobar al guardar). --}}
<div class="flex text-xs">
    <div class="shrink-0" style="width: 3.5rem;">
        <div class="py-3 px-2 font-medium border-b">{{ __('Horas') }}</div>
        <div class="relative" style="height: {{ $escala['alto'] }}px;">
            @foreach ($escala['marcas'] as $marca)
                <div class="absolute right-2 font-semibold text-gray-500 dark:text-gray-400"
                    style="top: {{ $marca['top'] }}px; transform: translateY(-50%);">{{ $marca['hora'] }}</div>
            @endforeach
        </div>
    </div>

    @foreach ($escala['columnas'] as $dia => $columna)
        <div class="flex-1 border-l dark:border-gray-700" style="min-width: 8rem;" wire:key="columna-profesor-{{ $dia }}">
            <div class="py-3 px-4 text-sm font-medium border-b">{{ $columna['nombre'] }}</div>
            <div class="relative bg-gray-100 dark:bg-gray-900"
                style="height: {{ $escala['alto'] }}px; background-image: repeating-linear-gradient(to bottom, transparent 0, transparent {{ $escala['media'] - 1 }}px, rgba(128, 128, 128, 0.35) {{ $escala['media'] - 1 }}px, rgba(128, 128, 128, 0.35) {{ $escala['media'] }}px), repeating-linear-gradient(to bottom, transparent 0, transparent {{ $escala['cinco'] - 1 }}px, rgba(128, 128, 128, 0.12) {{ $escala['cinco'] - 1 }}px, rgba(128, 128, 128, 0.12) {{ $escala['cinco'] }}px);">
                @foreach ($columna['jornadas'] as $jornada)
                    <div class="absolute inset-x-0 bg-white/70 dark:bg-gray-800/70"
                        style="top: {{ $jornada['top'] }}px; height: {{ $jornada['alto'] }}px;"></div>
                @endforeach

                @foreach ($columna['recreos'] as $recreo)
                    <div class="absolute inset-x-0 bg-gray-300/70 dark:bg-gray-600/70 text-gray-600 dark:text-gray-300 text-center overflow-hidden"
                        style="top: {{ $recreo['top'] }}px; height: {{ $recreo['alto'] }}px;" title="{{ __('Recreo') }} {{ $recreo['horas'] }}">
                        {{ __('Recreo') }}
                    </div>
                @endforeach

                @foreach ($columna['sesiones'] as $sesion)
                    <div wire:key="sesion-profesor-{{ $dia }}-{{ $sesion['numero'] }}-{{ $sesion['inicio'] }}"
                        class="absolute rounded px-2 py-1 overflow-hidden select-none shadow-sm"
                        style="left: 4px; right: 4px; top: {{ $sesion['top'] }}px; height: {{ $sesion['alto'] }}px; background-color: {{ $sesion['fondo'] }}; color: {{ $sesion['texto'] }}; cursor: grab; touch-action: none;"
                        title="{{ __('Arrastra arriba o abajo para cambiar la hora') }}"
                        x-data="{
                            inicio: {{ $sesion['inicio'] }},
                            min: {{ $sesion['min'] }},
                            max: {{ $sesion['max'] }},
                            nuevo: {{ $sesion['inicio'] }},
                            arrastrando: false,
                            y0: 0,
                            empezar(e) {
                                if (this.max < this.min) return;
                                this.arrastrando = true;
                                this.y0 = e.clientY;
                                this.nuevo = this.inicio;
                                e.currentTarget.setPointerCapture(e.pointerId);
                            },
                            mover(e) {
                                if (! this.arrastrando) return;
                                const minutos = Math.round((this.inicio + (e.clientY - this.y0) / {{ $escala['ppm'] }}) / 5) * 5;
                                this.nuevo = Math.min(this.max, Math.max(this.min, minutos));
                            },
                            soltar() {
                                if (! this.arrastrando) return;
                                this.arrastrando = false;
                                if (this.nuevo !== this.inicio) $wire.moverSesion({{ $dia }}, {{ $sesion['numero'] }}, this.nuevo);
                            },
                            hora(m) {
                                return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
                            },
                        }"
                        x-on:pointerdown="empezar($event)"
                        x-on:pointermove="mover($event)"
                        x-on:pointerup="soltar()"
                        x-on:pointercancel="soltar()"
                        :style="arrastrando ? { transform: 'translateY(' + ((nuevo - inicio) * {{ $escala['ppm'] }}) + 'px)', zIndex: 20, cursor: 'grabbing' } : {}">
                        <div class="font-semibold" x-text="hora(nuevo) + '–' + hora(nuevo + {{ $sesion['fin'] - $sesion['inicio'] }})">
                            {{ \App\Services\Horarios\ColocacionSesiones::hora($sesion['inicio']) }}–{{ \App\Services\Horarios\ColocacionSesiones::hora($sesion['fin']) }}
                        </div>
                        <div class="font-medium">{{ $sesion['nombre'] }}</div>
                        @if ($sesion['modulo'])
                            <div class="italic">{{ $sesion['modulo'] }}</div>
                        @endif
                        @if ($sesion['aula'])
                            <div>{{ $sesion['aula'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
