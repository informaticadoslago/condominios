<div class="flex flex-col min-h-[calc(100vh-12rem)]">
    @include('livewire.inmuebles.crear.navigation')

    <div class="flex-1 space-y-4">
        @if ($historicoFormasPago->isNotEmpty())
            <div class="text-sm text-gray-500">
                <p>{{ __('Formas de pago anteriores') }}:</p>
                @foreach ($historicoFormasPago as $h)
                    <p>{{ $h->fecha_inicio?->format('d/m/Y') }} — {{ $h->formaDePago?->descripcion }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex w-2/3">
            <div class="w-1/2">
                <x-label for="select-inmueble-forma-pago" :value="__('Forma de pago')" />
                <x-select id="select-inmueble-forma-pago" class="block mt-1 w-full mayusculas" wire:model.live="forma_de_pago_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($formasDePago as $forma)
                        <option value="{{ $forma->id }}">{{ $forma->descripcion }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="forma_de_pago_id" class="mt-2" />
            </div>
            {{-- Con recibo bancario no se pide: la fecha es la de la firma del mandato
                 (se duplica ahí, ver terminar()), no una fecha aparte. --}}
            @unless ($esReciboBancario)
                <div class="ml-2 w-1/2">
                    <x-label for="input-forma-pago-fecha-inicio" :value="__('Vigente desde')" />
                    <x-input id="input-forma-pago-fecha-inicio" class="block mt-1 w-full" type="date"
                        wire:model="forma_pago_fecha_inicio" />
                    <x-input-error for="forma_pago_fecha_inicio" class="mt-2" />
                </div>
            @endunless
        </div>

        <div class="flex w-full">
            {{-- El responsable del pago se pide siempre, haya domiciliación o no: es a
                 quien se le emite el recibo y a quien se le avisa. Ordenados de mayor a
                 menor cuota. --}}
            <div class="w-1/2">
                <x-label for="select-inmueble-titular-pago" :value="__('Propietario responsable del pago')" />
                <x-select id="select-inmueble-titular-pago" class="block mt-1 w-full mayusculas" wire:model.live="persona_comunidad_id_pago">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($titulares as $titular)
                        <option value="{{ $titular['persona_comunidad_id'] }}">{{ $titular['nombre'] }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="persona_comunidad_id_pago" class="mt-2" />
            </div>

            @if ($esReciboBancario)
                <div class="ml-2 w-1/2">
                    <x-label for="select-inmueble-cuenta-pago" :value="__('Cuenta bancaria')" />
                    <x-select id="select-inmueble-cuenta-pago" class="block mt-1 w-full" wire:model.live="cuenta_bancaria_id">
                        <option value="">{{ __('--') }}</option>
                        @foreach ($cuentas as $cuenta)
                            <option value="{{ $cuenta['id'] }}">{{ $cuenta['texto'] }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="cuenta_bancaria_id" class="mt-2" />
                    @if ($persona_comunidad_id_pago && ! count($cuentas))
                        <p class="mt-1 text-sm text-amber-600">{{ __('Este propietario no tiene ninguna cuenta bancaria registrada.') }}</p>
                        <button type="button" wire:click="abrirModalPropietario" class="mt-1 text-sm text-indigo-600 hover:underline">
                            <i class="fa-solid fa-pen"></i> {{ __('Editar propietario para añadirle una cuenta bancaria') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Mandato SEPA de la cuenta. Va con la cuenta, no con el inmueble: sirve para
             todos los que paguen con ella. --}}
        @if ($esReciboBancario && $cuenta_bancaria_id)
            <div class="border-t pt-4">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ __('Mandato SEPA') }}</span>
                    @if ($urlPlantillaMandato)
                        <x-secondary-button type="button" wire:click="abrirPlantillaMandato">
                            <i class="fa-solid fa-file-signature mr-1"></i>{{ __('Ver plantilla para firmar') }}
                        </x-secondary-button>
                    @endif
                </div>

                @if ($editandoMandato)
                    <div class="flex w-full mt-2">
                        <div class="w-1/2">
                            <x-label for="input-mandato-referencia-editar" :value="__('Número de mandato')" />
                            <x-input id="input-mandato-referencia-editar" class="block mt-1 w-full mayusculas" type="text"
                                wire:model.blur="mandato_referencia" placeholder="P19..." autofocus />
                            <x-input-error for="mandato_referencia" class="mt-2" />
                        </div>
                        <div class="ml-2 w-1/2">
                            <x-label for="input-mandato-fecha-editar" :value="__('Fecha de firma')" />
                            <x-input id="input-mandato-fecha-editar" class="block mt-1 w-full" type="date"
                                wire:model.blur="mandato_fecha_firma" />
                            <x-input-error for="mandato_fecha_firma" class="mt-2" />
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <x-secondary-button type="button" wire:click="guardarEdicionMandato">
                            <i class="fa-solid fa-check mr-1"></i>{{ __('Guardar') }}
                        </x-secondary-button>
                        <button type="button" wire:click="cancelarEdicionMandato" class="text-sm text-gray-500 hover:text-gray-800">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                @elseif ($mandatoVigente)
                    <p class="mt-2 text-sm flex items-center gap-2">
                        <span>
                            {{ __('Esta cuenta ya tiene mandato') }}:
                            <span class="font-semibold">{{ $mandatoVigente->referencia }}</span>
                            — {{ __('firmado el') }} {{ $mandatoVigente->fecha_firma?->format('d/m/Y') }}
                        </span>
                        <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir número o fecha') }}"
                            wire:click="editarMandato">
                            <i class="fa-solid fa-pen text-xs"></i>
                        </button>
                        <button type="button" class="text-red-600 hover:text-red-800" title="{{ __('Cancelar este mandato para registrar uno nuevo') }}"
                            wire:click="confirmarCancelarMandato({{ $mandatoVigente->id }})">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    </p>
                @else
                    <p class="mt-2 text-sm text-gray-500">
                        {{ __('Obligatorio para poder remesar: hacen falta los dos datos.') }}
                    </p>
                    <div class="flex w-full mt-2">
                        <div class="w-1/2">
                            <x-label for="input-mandato-referencia" :value="__('Número de mandato')" />
                            <x-input id="input-mandato-referencia" class="block mt-1 w-full mayusculas" type="text"
                                wire:model.blur="mandato_referencia" placeholder="P19..." />
                            <x-input-error for="mandato_referencia" class="mt-2" />
                        </div>
                        <div class="ml-2 w-1/2">
                            <x-label for="input-mandato-fecha" :value="__('Fecha de firma')" />
                            <x-input id="input-mandato-fecha" class="block mt-1 w-full" type="date"
                                wire:model.blur="mandato_fecha_firma" />
                            <x-input-error for="mandato_fecha_firma" class="mt-2" />
                        </div>
                    </div>
                    <div class="mt-2 w-1/2">
                        <x-label for="input-mandato-documento" :value="__('Documento firmado (opcional)')" />
                        <input id="input-mandato-documento" type="file" wire:model="mandato_documento"
                            accept=".pdf,.jpg,.jpeg,.png" class="block mt-1 w-full text-sm" />
                        <x-input-error for="mandato_documento" class="mt-2" />
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- La plantilla en blanco, para imprimirla o descargarla y mandársela al
         propietario: la rellena y la firma el titular de la cuenta. --}}
    <x-dosl.dialog-modal wire:model.live="modalPlantillaMandatoAbierta" maxWidth="4xl">
        <x-slot name="title">
            {{ __('Plantilla del mandato SEPA') }}
        </x-slot>
        <x-slot name="content">
            @if ($modalPlantillaMandatoAbierta && $urlPlantillaMandato)
                <iframe src="{{ $urlPlantillaMandato }}" class="w-full" style="height: 70vh;"></iframe>
            @endif
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button type="button" wire:click="cerrarPlantillaMandato">
                {{ __('Cerrar') }}
            </x-secondary-button>
        </x-slot>
    </x-dosl.dialog-modal>

    {{-- Wizard completo de Propietario embebido: al terminar (o salir), dispara un
         evento (ver propietarioActualizado()/cerrarModalPropietario()) en vez de
         navegar a otra página. Con propietarioIdParaModal se edita al propietario ya
         existente; sin él, se da de alta (la persona ya existe, se detecta por
         documento y solo hace falta confirmarla y añadirle la cuenta). --}}
    <x-dosl.dialog-modal wire:model.live="modalPropietarioAbierto" class="backdrop-blur" maxWidth="7xl">
        <x-slot name="title">
            {{ __('Propietario') }}
        </x-slot>
        <x-slot name="content">
            @if ($modalPropietarioAbierto)
                @livewire('propietarios.crear.crear-propietario', [
                    'propietarioId' => $propietarioIdParaModal,
                    'comunidadId'   => $comunidadIdParaModal,
                    'embebido'      => true,
                ], key('propietario-embebido-financiero-'.$modalPropietarioContador))
            @endif
        </x-slot>
    </x-dosl.dialog-modal>

    <div class="flex items-center justify-between border-t pt-4 mt-4">
        <div>
            @if ($this->hasPreviousStep())
                <x-button type="button" wire:click="stepBack" class="bg-gray-500 hover:bg-gray-600 text-white">
                    {{ __('Anterior') }}
                </x-button>
            @endif
        </div>
        <div class="flex gap-2">
            <span x-data="{ shift: false }" @keydown.shift.window="shift = true" @keyup.shift.window="shift = false" x-on:blur.window="shift = false">
                <x-button type="button" tabindex="-1" wire:click="salir($event.shiftKey)"
                    x-bind:class="shift ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-500 hover:bg-gray-600'" class="text-white">
                    <i class="fa-solid" x-bind:class="shift ? 'fa-trash' : 'fa-arrow-right-from-bracket'"></i>
                    <span x-show="!shift">{{ __('Salir') }}</span>
                    <span x-show="shift" x-cloak>{{ __('Salir y eliminar borrador') }}</span>
                </x-button>
            </span>
            <x-button type="button" wire:click="terminar">{{ __('Terminar') }}</x-button>
        </div>
    </div>
</div>
