    <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">        
        {{-- ===== CABECERA DEL SIDEBAR ===== --}}
        <flux:sidebar.header>
            <flux:sidebar.brand href="#" logo="https://fluxui.dev/img/demo/logo.png"
                logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png" name="Acme Inc." />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>
        

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" current>Inicio</flux:sidebar.item>
            <flux:sidebar.group icon="cog-6-tooth" expandable heading="{{ __('Escuela') }}" class="grid">
                <flux:sidebar.item icon="cog-6-tooth" href="#">{{ __('Matriculacion') }}</flux:sidebar.item>
                <flux:sidebar.item href="#">{{ __('Grupos') }}</flux:sidebar.item>                
            </flux:sidebar.group>

            <flux:sidebar.group expandable heading="{{ __('Administracion') }}" class="grid">
                <flux:sidebar.item href="#">{{ __('Profesores') }}</flux:sidebar.item>
                <flux:sidebar.item href="#">{{ __('Alumnos') }}</flux:sidebar.item>                
            </flux:sidebar.group>

            <flux:sidebar.group expandable expanded="false" heading="{{ __('Administracion del sistema') }}" class="grid">
                <flux:sidebar.item href="#">{{ __('Empresa') }}</flux:sidebar.item>
                <flux:sidebar.item href="#">{{ __('Personas') }}</flux:sidebar.item>
                <flux:sidebar.item href="#">{{ __('Usuarios') }}</flux:sidebar.item>
                <flux:sidebar.item href="#">{{ __('Roles') }}</flux:sidebar.item>
                <flux:sidebar.item href="#">{{ __('Permisos') }}</flux:sidebar.item>

            </flux:sidebar.group>

            
            {{-- <flux:sidebar.item icon="document-text" href="#">Administración</flux:sidebar.item>
            <flux:sidebar.item icon="calendar" href="#">Calendar</flux:sidebar.item> --}}

        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="#">{{ __('Configuracion') }}</flux:sidebar.item>
        </flux:sidebar.nav>

    </flux:sidebar>
