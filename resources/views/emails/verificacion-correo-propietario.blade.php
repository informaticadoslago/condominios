{{--
    Correo que pide a un propietario que confirme su dirección de correo.

    El aspecto está en el fichero hermano verificacion-correo-propietario.css; aquí solo
    va el texto. Todo el texto pasa por __(), así que se traduce al idioma con el que se
    envíe el correo (ver VerificacionCorreoPropietario::__construct).
--}}
<x-emails.layout plantilla="verificacion-correo-propietario"
    :titulo="__('Confirma tu correo')">

    <p class="saludo">{{ __('Hola :nombre:', ['nombre' => $nombre]) }}</p>

    <p>{{ __('Esta dirección de correo consta como tuya en :comunidad. Para poder mandarte los recibos y los avisos de la comunidad necesitamos que confirmes que es correcta.', ['comunidad' => $comunidad]) }}</p>

    <p class="boton-caja">
        {{-- El color va también inline, no solo en la clase: Gmail y otros webmail
             suelen pintar los <a> de su azul de enlace por defecto, ignorando el color
             que venga del <style> del <head>. --}}
        <a href="{{ $enlace }}" class="boton" style="color:#ffffff;">{{ __('Confirmar mi correo') }}</a>
    </p>

    <p class="aviso">
        {{ __('Si el botón no funciona, copia y pega este enlace en tu navegador:') }}<br>
        <a href="{{ $enlace }}" class="enlace-plano">{{ $enlace }}</a>
    </p>

    <x-slot name="pie">
        {{ __('Si no esperabas este mensaje, puedes ignorarlo: sin confirmar, no se usará esta dirección.') }}<br><br>
        {{ __('Este es un correo automático: por favor no respondas a esta dirección.') }}
        @if ($correoContacto)
            {{ __('Para cualquier consulta, escribe a :correo.', ['correo' => $correoContacto]) }}
        @endif
    </x-slot>
</x-emails.layout>
