{{-- Campanitas de contorno de los avisos push del usuario en una sesión ($avisosSesion:
     [momento => minutos antes]): arriba a la derecha el de inicio y abajo a la derecha el
     de fin, como en la escala de tiempo, donde arriba es cuando empieza y abajo cuando
     acaba. La caja de la sesión tiene que ser relative y dejarles hueco a la derecha. --}}
@foreach ([
    \App\Models\HorarioSesionAviso::MOMENTO_INICIO => ['top-0.5', __('Aviso de inicio')],
    \App\Models\HorarioSesionAviso::MOMENTO_FIN => ['bottom-0.5', __('Aviso de fin')],
] as $momento => [$posicion, $etiqueta])
    @isset($avisosSesion[$momento])
        <i class="fa-regular fa-bell absolute right-1 {{ $posicion }} text-[10px]"
            title="{{ $etiqueta }}: {{ \App\Models\HorarioSesionAviso::opciones()[$avisosSesion[$momento]] ?? '' }}"></i>
    @endisset
@endforeach
