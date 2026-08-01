// RLAU_TAB_STYLE (config/doslago.php -> tab_style): cuando está activo, Enter
// y las flechas izquierda/derecha navegan entre campos del formulario en vez
// de comportarse de forma nativa (Enter = botón por defecto, flechas = mover
// el cursor). Se activa vía el atributo data-tab-style="1" en <body>
// (layouts/app.blade.php y layouts/guest.blade.php).
//
// - En los <textarea>, Enter siempre sigue siendo salto de línea.
// - Las flechas solo saltan de campo cuando el cursor ya está al principio
//   (izda) o al final (dcha) del texto; si no, mueven el cursor con normalidad.
// - Con flechas se da la vuelta (del último campo se salta al primero y
//   viceversa); con Enter no: en el último campo se queda ahí.
// - Los campos con tabindex="-1" se saltan.
// - Si un campo ya declara su propio wire:keydown para esa tecla (p.ej. el
//   autocompletado o el filtro), no interferimos: se respeta esa acción.
// - Los botones (submit/button/reset) también son una parada más de la
//   secuencia: se puede llegar a ellos con Enter o flecha. Pero si el foco YA
//   está en un botón, Enter lo activa (submit nativo) en vez de seguir
//   saltando; para saltárselo sin activarlo se usa la flecha (o Tab, que
//   sigue funcionando de forma nativa).

const SELECTOR_CAMPOS =
    'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])';

const TIPOS_TEXTO = ['text', 'search', 'tel', 'url', 'email', 'password', 'number'];

function esTextoEditable(el) {
    if (el.tagName === 'TEXTAREA') return true;
    if (el.tagName !== 'INPUT') return false;
    return TIPOS_TEXTO.includes(el.type);
}

function esBoton(el) {
    if (el.tagName === 'BUTTON') return true;
    if (el.tagName === 'INPUT') return ['submit', 'button', 'reset'].includes(el.type);
    return false;
}

function esNavegable(el) {
    if (!el.matches(SELECTOR_CAMPOS)) return false;
    if (el.tabIndex === -1) return false;
    if (el.offsetParent === null) return false;
    return true;
}

function tieneWireKeydownPropio(el, nombreEvento) {
    return Array.prototype.some.call(
        el.attributes,
        (attr) => attr.name === `wire:keydown.${nombreEvento}` || attr.name.startsWith(`wire:keydown.${nombreEvento}.`)
    );
}

document.addEventListener('keydown', (e) => {
    if (document.body.dataset.tabStyle !== '1') return;

    const el = e.target;
    if (!(el instanceof HTMLElement) || !esNavegable(el)) return;

    const tecla = e.key;
    if (tecla !== 'Enter' && tecla !== 'ArrowLeft' && tecla !== 'ArrowRight') return;
    if (tecla === 'Enter' && el.tagName === 'TEXTAREA') return;
    if (tecla === 'Enter' && esBoton(el)) return; // deja que el Enter active este botón (submit/click nativo)

    const nombreEvento = tecla === 'Enter' ? 'enter' : tecla === 'ArrowLeft' ? 'arrow-left' : 'arrow-right';
    if (tieneWireKeydownPropio(el, nombreEvento)) return;

    if ((tecla === 'ArrowLeft' || tecla === 'ArrowRight') && esTextoEditable(el)) {
        const alFinal = el.selectionEnd === el.value.length;
        const alPrincipio = el.selectionStart === 0;
        if (tecla === 'ArrowRight' && !alFinal) return;
        if (tecla === 'ArrowLeft' && !alPrincipio) return;
    }

    const contenedor = el.closest('form') || document;
    const campos = Array.prototype.filter.call(contenedor.querySelectorAll(SELECTOR_CAMPOS), esNavegable);
    const i = campos.indexOf(el);
    if (i === -1) return;

    let j;
    if (tecla === 'Enter') {
        j = i + 1;
        if (j >= campos.length) return;
    } else if (tecla === 'ArrowRight') {
        j = (i + 1) % campos.length;
    } else {
        j = (i - 1 + campos.length) % campos.length;
    }

    e.preventDefault();
    campos[j].focus();
});
