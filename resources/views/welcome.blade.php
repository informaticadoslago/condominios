<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ config('app.name', 'Condominios dosLago') }}</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo/logo-circulo-blanco.png') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
    :root {
        /* ===== PARÁMETROS ===== */
        --logo-width: 320px;
        --logo-drop-duration: 1.6s;

        --text-delay: 1s;
        --text-font-size: 48px;
        --text-font-family: Arial, Helvetica, sans-serif;
        --text-color: #333;
        --text-offset-y: -50px;

        --button-margin-top: 24px;
        --exit-duration: 0.6s;
    }

    html, body {
        height: 100%;
        margin: 0;
    }

    body {
        background: #ffffff;
        overflow: hidden;
        font-family: Arial, Helvetica, sans-serif;
        opacity: 1;
    }

    /* CONTENEDOR CENTRAL */
    .container {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        z-index: 1;
    }

    /* LOGO */
    .logo {
        animation: dropToCenter var(--logo-drop-duration) ease-out forwards;
    }

    .logo img {
        width: var(--logo-width);
        max-width: 80vw;
        height: auto;
        display: block;
        margin: 0 auto;
    }

    @keyframes dropToCenter {
        from {
            transform: translateY(-300px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* TEXTO SUPERIOR */
    .subtitle {
        position: absolute;
        left: 50%;
        top: 0;
        transform: translate(-50%, var(--text-offset-y));
        font-size: var(--text-font-size);
        font-family: var(--text-font-family);
        color: var(--text-color);
        opacity: 0;
        white-space: nowrap;
        animation: fadeInText 0.6s ease-out forwards;
        animation-delay: calc(var(--logo-drop-duration) + var(--text-delay));
    }

    @keyframes fadeInText {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    /* BOTÓN */
    .enter-button {
        margin-top: var(--button-margin-top);
        padding: 12px 28px;
        font-size: 18px;
        border: 1px solid #999;
        background: #f5f5f5;
        opacity: 0;
        animation: fadeInText 0.6s ease-out forwards;
        animation-delay: calc(var(--logo-drop-duration) + var(--text-delay) + 0.4s);
    }

    /* OVERLAY (desactivado al inicio) */
    .overlay {
        position: fixed;
        inset: 0;
        z-index: 10;
        cursor: pointer;
        pointer-events: none;
        background: transparent;
    }

    /* SALIDA */
    body.exit {
        animation: fadeOut var(--exit-duration) ease-in forwards;
    }

    @keyframes fadeOut {
        to {
            opacity: 0;
            transform: scale(0.98);
        }
    }
</style>
</head>

<body>

@php
    $targetUrl = null;
    if (Route::has('login')) {
        $targetUrl = auth()->check()
            ? url('/dashboard')
            : route('login');
    }
@endphp

<div class="container">
    <div class="subtitle">{{ config('app.name') }}</div>

    <div class="logo">
        <img src="{{ asset('images/logo/dosLago.png') }}" alt="dosLago">
    </div>

    <button class="enter-button">Pulse para entrar</button>
</div>

@if ($targetUrl)
    <div class="overlay" data-url="{{ $targetUrl }}"></div>
@endif

<script>
(function () {
    const overlay = document.querySelector('.overlay');
    if (!overlay) return;

    const url = overlay.dataset.url;

    const styles = getComputedStyle(document.documentElement);
    const logoDuration = parseFloat(styles.getPropertyValue('--logo-drop-duration')) || 1.6;
    const textDelay = parseFloat(styles.getPropertyValue('--text-delay')) || 1;
    const exitDuration = parseFloat(styles.getPropertyValue('--exit-duration')) || 0.6;

    const activationDelay = (logoDuration + textDelay + 0.5) * 1000;

    // activar overlay tras animaciones
    setTimeout(() => {
        overlay.style.pointerEvents = 'auto';
    }, activationDelay);

    let locked = false;

    function go(e) {
        if (locked) return;

        // click solo botón izquierdo
        if (e.type === 'click' && e.button !== 0) return;

        locked = true;
        document.body.classList.add('exit');

        setTimeout(() => {
            window.location.href = url;
        }, exitDuration * 1000);
    }

    overlay.addEventListener('click', go);
    document.addEventListener('keydown', go);
})();
</script>

</body>
</html>
