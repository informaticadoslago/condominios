{{--
    Correo que pide a un propietario que confirme su dirección de correo.

    El aspecto está en el fichero hermano verificacion-correo-propietario.css; aquí solo
    va el texto. Todo el texto pasa por __(), así que se traduce al idioma con el que se
    envíe el correo (ver VerificacionCorreoPropietario::__construct).
--}}
<x-emails.layout plantilla="verificacion-correo-propietario"
    :titulo="__('Confirma tu correo')">

    <p class="saludo">{{ __('Hola, :nombre:', ['nombre' => $nombre]) }}</p>

    <p>{{ __('Esta dirección de correo consta como tuya en :comunidad. Para poder mandarte los recibos y los avisos de la comunidad necesitamos que confirmes que es correcta.', ['comunidad' => $comunidad]) }}</p>

    <p class="boton-caja">
        <a href="{{ $enlace }}" class="boton">{{ __('Confirmar mi correo') }}</a>
    </p>

    <p class="aviso">
        {{ __('Si el botón no funciona, copia y pega este enlace en tu navegador:') }}<br>
        <a href="{{ $enlace }}" class="enlace-plano">{{ $enlace }}</a>
    </p>

    <x-slot name="pie">
        {{ __('Si no esperabas este mensaje, puedes ignorarlo: sin confirmar, no se usará esta dirección.') }}
    </x-slot>
</x-emails.layout>
