        <flux:navbar class="lg:hidden w-full">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            @if (config('flux.appearance.enabled'))                
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" x-show="!$flux.dark" icon="moon"
                        variant="subtle" aria-label="Toggle dark mode" />
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" x-show="$flux.dark" icon="sun"
                        variant="subtle" aria-label="Toggle dark mode" />                
            @endif

            {{-- Perfil móvil --}}
            <flux:dropdown position="top" align="end">
                
                <flux:profile initials="{{ Auth()->user()->persona->iniciales }}" />

                <flux:navmenu class="max-w-[12rem]">
                    <div class="px-2 py-1.5">
                        <flux:text size="sm">Signed in as</flux:text>
                        <flux:heading class="mt-1! truncate">{{ Auth()->user()->email }}</flux:heading>
                    </div>

                    <flux:navmenu.separator />
                    <flux:navmenu.item href="{{ route('profile.show') }}" icon="user" class="text-zinc-800 dark:text-white">Account
                    </flux:navmenu.item>

                    <flux:navmenu.separator />

                    <x-dosl.logout-button label="Salir" />

                </flux:navmenu>
            </flux:dropdown>
        </flux:navbar>
