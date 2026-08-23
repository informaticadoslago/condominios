<div>
    <x-dosl.dialog-modal wire:model.live="show" maxWidth="full">
        <x-slot name="title">
            {{ __('Configuración') }}
        </x-slot>

        <x-slot name="content">
            {{-- Pestañas: agrupan las variables del .env por prefijo. --}}
            <div class="flex flex-wrap items-center justify-between gap-1 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-1">
                    @foreach ($pestanas as $id => $etiqueta)
                        <button type="button" wire:click="$set('tab', '{{ $id }}')"
                            @class([
                                'px-3 py-2 text-sm font-medium border-b-2 -mb-px transition',
                                'border-indigo-500 text-indigo-600 dark:text-indigo-400' => $tab === $id,
                                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $tab !== $id,
                            ])>
                            {{ __($etiqueta) }}
                            <span class="ml-1 text-xs text-gray-400">({{ count($grupos[$id]) }})</span>
                        </button>
                    @endforeach
                </div>

                <button type="button" wire:click="abrirImportar" class="btn btn-nuevo px-2 mb-1">
                    {{ __('Import') }}
                </button>
            </div>

            {{-- Panel de la pestaña activa: las claves editables llevan input, el resto solo lectura. --}}
            <div class="mt-4 max-h-[55vh] overflow-y-auto pr-1">
                @php($vars = $grupos[$tab] ?? [])

                @if (empty($vars))
                    <p class="text-sm text-gray-500 py-4">{{ __('Sin variables en este grupo.') }}</p>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach ($vars as $clave => $valor)
                            <div class="rounded border border-gray-100 dark:border-gray-700 px-2 py-1.5">
                                <dt class="font-mono text-xs text-gray-600 dark:text-gray-300 break-all">
                                    {{ $clave }}
                                </dt>

                                @if ($this->esEditable($clave) && $this->esSecreto($clave))
                                    <dd class="font-mono text-xs text-gray-900 dark:text-gray-100 break-all flex items-center gap-2">
                                        {{ $valor }}
                                        <button type="button" wire:click="abrirPassword('{{ $clave }}')"
                                            class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                            title="{{ __('Cambiar') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                    </dd>
                                @elseif ($this->esEditable($clave))
                                    <dd>
                                        <x-dosl.input-ts wire:model="form.{{ $clave }}" />
                                    </dd>
                                @else
                                    <dd class="font-mono text-xs text-gray-900 dark:text-gray-100 break-all">
                                        @if ($valor === '')
                                            <span class="text-gray-400 italic">{{ __('(vacío)') }}</span>
                                        @else
                                            {{ $valor }}
                                        @endif
                                    </dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar accion="close">{{ __('Cerrar') }}</x-dosl.boton-cerrar>

            @if (! empty($form))
                <button type="button" wire:click="guardar" class="btn btn-guardar px-2">
                    {{ __('Guardar') }}
                </button>
            @endif
        </x-slot>
    </x-dosl.dialog-modal>

    {{-- Modal secundario: cambiar una clave secreta (nunca muestra el valor actual). --}}
    <x-dosl.dialog-modal wire:model.live="passwordAbierto" maxWidth="lg">
        <x-slot name="title">
            <span class="whitespace-nowrap">{{ __('Cambiar') }} {{ $passwordClave }}</span>
        </x-slot>

        <x-slot name="content">
            <div class="space-y-3">
                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">{{ __('Nueva contraseña') }}</label>
                    <x-dosl.input-ts type="password" wire:model="passwordNueva" />
                </div>
                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">{{ __('Confirmar contraseña') }}</label>
                    <x-dosl.input-ts type="password" wire:model="passwordConfirmacion" />
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar accion="$set('passwordAbierto', false)">{{ __('Cancelar') }}</x-dosl.boton-cerrar>

            <button type="button" wire:click="guardarPassword" class="btn btn-guardar px-2">
                {{ __('Guardar') }}
            </button>
        </x-slot>
    </x-dosl.dialog-modal>

    {{-- Modal secundario: pegar varias líneas CLAVE=VALOR y aplicarlas al formulario en memoria
         (el guardado a disco sigue haciéndose con el botón "Guardar" principal). --}}
    <x-dosl.dialog-modal wire:model.live="importarAbierto" maxWidth="lg">
        <x-slot name="title">
            {{ __('Importar variables') }}
        </x-slot>

        <x-slot name="content">
            <div class="flex w-full max-w-full flex-col gap-1 text-slate-700 dark:text-slate-300">
                <label for="importar-texto" class="w-fit pl-0.5 text-sm">
                    {{ __('Una variable por línea, en formato CLAVE=VALOR') }}
                </label>
                <textarea id="importar-texto" wire:model="importarTexto"
                    class="w-full rounded-xl border border-slate-300 bg-slate-100 px-2.5 py-2 font-mono text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 disabled:cursor-not-allowed disabled:opacity-75 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"
                    rows="10" placeholder="MAIL_HOST=smtp.example.com"></textarea>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar accion="$set('importarAbierto', false)">{{ __('Cancelar') }}</x-dosl.boton-cerrar>

            <button type="button" wire:click="importar" class="btn btn-guardar px-2">
                {{ __('Aplicar') }}
            </button>
        </x-slot>
    </x-dosl.dialog-modal>
</div>
