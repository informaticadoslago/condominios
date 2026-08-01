<div class="p-4 sm:p-6">
    <div class="max-w-5xl mx-auto">
        <h1 class="mb-4 text-2xl font-medium">
            {{ $propietarioId ? __('Editar propietario') : __('Nuevo propietario') }}
        </h1>

        @livewire('propietarios.crear.crear-propietario',
            ['propietarioId' => $propietarioId, 'comunidadId' => $comunidadId],
            key('propietario-' . ($propietarioId ?? 'nuevo')))
    </div>
</div>
