<x-dosl.dialog-modal wire:model.live="abrirCrear" class="backdrop-blur" fullscreen>
    <x-slot name="title">
        {{ __('Nuevo usuario') }}
    </x-slot>

    <x-slot name="content">
        {{-- 1) Documento identificativo --}}
        <div class="flex w-full items-start">
            <div class="mt-2 w-1/5">
                <x-label for="select-crear-documento-pais" :value="__('Pais')" />
                <x-select id="select-crear-documento-pais" class="block mt-1 w-full mayusculas" name="documento-pais"
                    wire:model.live="formulario.documento_pais_id">
                    @foreach ($paises as $pais)
                        <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="formulario.documento_pais_id" class="mt-2" />
            </div>

            <div class="mt-2 ml-2 w-1/5">
                <x-label for="select-crear-tipo-doc" :value="__('Tipo documento')" />
                <x-select id="select-crear-tipo-doc" class="block mt-1 w-full mayusculas" name="tipo_documento"
                    wire:model.live="formulario.tipo_documento_id">
                    @foreach ($formulario->tipo_documento_identificativos as $tipo_documento_identificativo)
                        <option value="{{ $tipo_documento_identificativo->id }}">
                            {{ $tipo_documento_identificativo->nombre }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="formulario.tipo_documento_id" class="mt-2" />
            </div>

            <div class="mt-2 ml-2 w-2/5">
                <x-label for="input-crear-documento-identificativo" :value="__('Documento Id.')" />
                <x-input id="input-crear-documento-identificativo" class="block mt-1 w-full mayusculas" type="text"
                    name="documento-identificativo" wire:model.live="formulario.documento_identificativo" forzar-may
                    autofocus />
                <x-input-error for="formulario.documento_identificativo" class="mt-2" />
            </div>

            <div class="mt-2 ml-2 w-1/5">
                <x-label>&nbsp;</x-label>
                <button type="button" class="btn btn-nuevo w-full mt-1" wire:click="comprobar">
                    {{ __('Comprobar') }}
                </button>
            </div>
        </div>

        @if ($usuarioYaExiste)
            <div class="flex flex-col items-start p-4 mt-4 bg-amber-50 border border-amber-200 rounded">
                <div class="text-sm text-amber-800">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    {{ __('Esa persona ya tiene un usuario. ¿Quieres editarlo?') }}
                </div>
                <div class="flex mt-3">
                    <button type="button" class="btn btn-nuevo px-2 mr-2" wire:click="editarUsuarioExistente">
                        {{ __('Sí, editar') }}
                    </button>
                    <button type="button" class="btn btn-cerrar px-2" wire:click="noEditarUsuarioExistente">
                        {{ __('No') }}
                    </button>
                </div>
            </div>
        @endif

        @if ($comprobado)
            @if ($personaExiste)
                <div class="mt-4 text-sm text-green-700">
                    <i class="fa-solid fa-circle-check"></i>
                    {{ __('Persona existente: sus datos no se pueden modificar.') }}
                </div>
            @else
                <div class="mt-4 text-sm text-blue-700">
                    <i class="fa-solid fa-circle-info"></i>
                    {{ __('Persona nueva: completa el resto de datos.') }}
                </div>
            @endif

            {{-- 2) Datos de la persona (bloqueados si ya existe) --}}
            <div class="flex w-full">
                <div class="mt-2 mr-4 w-1/3">
                    <x-label for="input-crear-nombre" :value="__('Nombre')" />
                    <x-input id="input-crear-nombre" class="block mt-1 w-full" type="text" name="nombre"
                        wire:model="formulario.nombre" :readonly="$personaExiste" />
                    <x-input-error for="formulario.nombre" class="mt-2" />
                </div>
                <div class="mt-2 mr-4 w-1/3">
                    <x-label for="input-crear-apellido1" :value="__('Apellido 1')" />
                    <x-input id="input-crear-apellido1" class="block mt-1 w-full" type="text" name="apellido1"
                        wire:model="formulario.apellido1" :readonly="$personaExiste" />
                    <x-input-error for="formulario.apellido1" class="mt-2" />
                </div>
                <div class="mt-2 w-1/3">
                    <x-label for="input-crear-apellido2" :value="__('Apellido 2')" />
                    <x-input id="input-crear-apellido2" class="block mt-1 w-full" type="text" name="apellido2"
                        wire:model="formulario.apellido2" :readonly="$personaExiste" />
                    <x-input-error for="formulario.apellido2" class="mt-2" />
                </div>
            </div>

            <div class="flex w-full">
                <div class="mt-2 mr-4 w-1/3">
                    <x-label for="select-crear-genero" :value="__('Genero')" />
                    <x-select id="select-crear-genero" class="block mt-1 w-full mayusculas" name="genero"
                        wire:model="formulario.genero_id" :disabled="$personaExiste">
                        <option value="">{{ __('--') }}</option>
                        @foreach ($generos as $genero)
                            <option value="{{ $genero->id }}">{{ $genero->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="formulario.genero_id" class="mt-2" />
                </div>
                <div class="mt-2 w-2/5">
                    <x-label for="input-crear-fecha-nacimiento" :value="__('Fecha de nacimiento')" />
                    <x-input id="input-crear-fecha-nacimiento" class="block mt-1 w-full" type="date"
                        name="fecha-nacimiento" wire:model="formulario.fecha_nacimiento" :readonly="$personaExiste" />
                    <x-input-error for="formulario.fecha_nacimiento" class="mt-2" />
                </div>
            </div>

            <hr class="my-4" />

            {{-- 3) Credenciales de acceso --}}
            <strong>{{ __('Datos de acceso') }}:</strong>
            <div class="flex flex-wrap -mx-2">
                <div class="mt-2 px-2 w-full sm:w-1/2">
                    <x-label for="input-crear-login" :value="__('Login')" />
                    <x-input id="input-crear-login" class="block mt-1 w-full" type="text" name="login"
                        wire:model="login" autocomplete="off" />
                    <x-input-error for="login" class="mt-2" />
                </div>
                <div class="mt-2 px-2 w-full sm:w-1/2">
                    <x-label for="input-crear-email" :value="__('Email')" />
                    <x-input id="input-crear-email" class="block mt-1 w-full" type="email" name="email"
                        wire:model="email" autocomplete="off" />
                    <x-input-error for="email" class="mt-2" />
                </div>
                <div class="mt-2 px-2 w-full sm:w-1/2" x-data="{ verPassword: false }">
                    <x-label for="input-crear-password" :value="__('Contraseña')" />
                    <div class="mt-1 flex gap-1">
                        <div class="relative flex-1">
                            <x-input id="input-crear-password" class="block w-full pr-10"
                                x-bind:type="verPassword ? 'text' : 'password'" name="password"
                                wire:model="password" autocomplete="new-password" />
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500"
                                @click="verPassword = !verPassword" title="{{ __('Ver/ocultar contraseña') }}">
                                <i class="fa-solid" :class="verPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-cerrar px-3" wire:click="generarPassword"
                            title="{{ __('Generar contraseña') }}">
                            <i class="fa-solid fa-dice"></i>
                        </button>
                    </div>
                    <x-input-error for="password" class="mt-2" />
                </div>
                <div class="mt-2 px-2 w-full sm:w-1/2">
                    <x-label for="input-crear-password-confirmation" :value="__('Repetir contraseña')" />
                    <x-input id="input-crear-password-confirmation" class="block mt-1 w-full" type="password"
                        name="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" />
                </div>
            </div>

            <div class="mt-4 px-2">
                <strong>{{ __('Roles') }}:</strong>
                <div class="flex mt-1">
                    <table class="table-auto">
                        <tbody>
                            @foreach ($rolesDisponibles->chunk(3) as $chunk)
                                <tr>
                                    @foreach ($chunk as $rol)
                                        <td class="m-2 px-2">
                                            <label>
                                                <input type="checkbox" class="name" wire:model="roles"
                                                    value="{{ $rol->name }}" />
                                                {{ $rol->name }}
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-input-error for="roles" class="mt-2" />
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="close" />
        @if ($comprobado)
            <button type="button" class="btn btn-guardar px-2" id="btn-guardar-usuario" wire:click="guardar"
                title="Guardar">{{ __('Guardar') }}</button>
        @endif
    </x-slot>
</x-dosl.dialog-modal>
