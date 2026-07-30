<x-guest-layout>
    <div class="pt-4 bg-gray-100 dark:bg-gray-900">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div>
                <x-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-md mt-6 p-6 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg text-center">
                <i class="fa-solid fa-circle-check text-4xl text-green-600"></i>
                <p class="mt-4 text-gray-800 dark:text-gray-200">
                    {{ __('Correo confirmado. Ya puedes iniciar sesión.') }}
                </p>
                <a href="{{ route('login') }}" class="btn btn-nuevo inline-block mt-4 px-4 py-2">
                    {{ __('Ir a iniciar sesión') }}
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
