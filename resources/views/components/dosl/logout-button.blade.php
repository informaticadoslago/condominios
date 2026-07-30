@props(['label' => __('Logout')])

<form method="POST" action="{{ route('logout') }}" x-data>
    @csrf

    <flux:navmenu.item 
        href="#" 
        icon="arrow-right-start-on-rectangle"
        class="text-zinc-800 dark:text-white w-full text-left"
        x-on:click.prevent="
            Swal.fire({
                title: '¿Desea salir?',
                text: 'Se cerrará su sesión actual',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#f1c40f',
                confirmButtonText: 'Sí, salir',
                cancelButtonText: 'No, cancelar',
                background: document.documentElement.classList.contains('dark') ? '#1f2937' : undefined,
                color: document.documentElement.classList.contains('dark') ? '#e5e7eb' : undefined
            }).then((result) => {
                if (result.isConfirmed) $root.submit();
            });
        ">
        {{ $label }}
    </flux:navmenu.item>
</form>
