{{-- Página a la que llega el propietario tras pinchar el enlace del correo de
     verificación. No es usuario de la aplicación, así que no se le ofrece iniciar
     sesión: solo se le confirma que ya está. --}}
<x-guest-layout>
    <div class="pt-4 bg-gray-100 dark:bg-gray-900">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div>
                <x-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-md mt-6 p-6 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg text-center">
                <i class="fa-solid fa-circle-check text-4xl text-green-600"></i>
                <p class="mt-4 text-gray-800 dark:text-gray-200">
                    {{ __('Correo confirmado.') }}
                </p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $correo }}</p>
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('A partir de ahora recibirás aquí los recibos y los avisos de tu comunidad.') }}
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
