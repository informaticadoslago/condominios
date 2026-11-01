// iPhone con la web instalada en la pantalla de inicio (navigator.standalone): ahí lo que
// se abre en otra pestaña (target="_blank" o window.open) ocupa la app entera, sin ningún
// botón para volver. Los enlaces de la propia web se enseñan en cambio en el visor del
// layout (layouts/visor-pdf): los PDF dibujados con pdf.js, página a página, con "Cerrar"
// e "Imprimir / Compartir" (el menú de compartir del iPhone, que trae Imprimir y Guardar
// en Archivos); lo que no es PDF, en un iframe debajo de "Cerrar". En el PC y en Safari
// normal no cambia nada.

import trabajadorPdf from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url';

const enApp = navigator.standalone === true;

/** Lo que se está enseñando: { controlador, tarea, blob, nombre }. */
let abierto = null;

function visor() {
    return document.getElementById('visor-pdf');
}

function parte(nombre) {
    return visor().querySelector(`[data-visor-pdf="${nombre}"]`);
}

function mismoOrigen(url) {
    try {
        return new URL(url, location.href).origin === location.origin;
    } catch {
        return false;
    }
}

/** El nombre que manda el servidor (Content-Disposition), para el fichero que se comparte. */
function nombreFichero(cabecera) {
    const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(cabecera ?? '');
    if (utf8) return decodeURIComponent(utf8[1]);

    const simple = /filename="?([^";]+)"?/i.exec(cabecera ?? '');

    return simple ? simple[1] : 'documento.pdf';
}

function fichero(estado) {
    return new File([estado.blob], estado.nombre, { type: 'application/pdf' });
}

async function abrir(url) {
    cerrar();

    const estado = { controlador: new AbortController(), tarea: null, blob: null, nombre: null };
    abierto = estado;
    visor().hidden = false;
    document.documentElement.style.overflow = 'hidden';
    parte('cargando').hidden = false;

    try {
        const respuesta = await fetch(url, { credentials: 'same-origin', signal: estado.controlador.signal });
        const tipo = respuesta.headers.get('Content-Type') ?? '';

        if (! respuesta.ok || ! tipo.includes('application/pdf')) {
            // Una imagen, una página, un error del servidor…: tal cual, con "Cerrar" encima.
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.className = 'block w-full h-full bg-white';
            parte('paginas').append(iframe);

            return;
        }

        estado.blob = await respuesta.blob();
        estado.nombre = nombreFichero(respuesta.headers.get('Content-Disposition'));
        if (estado.controlador.signal.aborted) return;

        parte('compartir').hidden = ! navigator.canShare?.({ files: [fichero(estado)] });
        await dibujar(estado);
    } catch {
        if (! estado.controlador.signal.aborted) parte('paginas').textContent = visor().dataset.error;
    } finally {
        if (! estado.controlador.signal.aborted) parte('cargando').hidden = true;
    }
}

async function dibujar(estado) {
    // pdf.js solo se descarga la primera vez que se abre un PDF; la versión legacy, para
    // que también vaya en iPhones con un iOS antiguo.
    const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    pdfjs.GlobalWorkerOptions.workerSrc = trabajadorPdf;

    estado.tarea = pdfjs.getDocument({ data: new Uint8Array(await estado.blob.arrayBuffer()) });
    const documento = await estado.tarea.promise;
    const paginas = parte('paginas');
    parte('cargando').hidden = true;

    // A la resolución de la pantalla y no más: con muchas páginas, el iPhone se queda sin
    // la memoria que deja para los canvas.
    const ratio = Math.min(window.devicePixelRatio || 1, 3);

    for (let n = 1; n <= documento.numPages; n++) {
        if (estado.controlador.signal.aborted) return;

        const pagina = await documento.getPage(n);
        const ancho = paginas.clientWidth - 16; // menos el p-2 del contenedor
        const viewport = pagina.getViewport({ scale: (ancho / pagina.getViewport({ scale: 1 }).width) * ratio });
        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        canvas.className = 'block w-full h-auto bg-white shadow';
        paginas.append(canvas);

        await pagina.render({ canvas, viewport }).promise;
    }
}

function cerrar() {
    if (! abierto) return;

    abierto.controlador.abort();
    abierto.tarea?.destroy();
    abierto = null;

    const paginas = parte('paginas');
    // Soltar ya la memoria de los canvas: el iPhone no lo hace hasta mucho después.
    paginas.querySelectorAll('canvas').forEach((canvas) => {
        canvas.width = 0;
        canvas.height = 0;
    });
    paginas.replaceChildren();
    parte('cargando').hidden = true;
    parte('compartir').hidden = true;
    visor().hidden = true;
    document.documentElement.style.overflow = '';
}

if (enApp) {
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || ! visor()) return;

        if (e.target.closest('[data-visor-pdf="cerrar"]')) {
            cerrar();
        } else if (e.target.closest('[data-visor-pdf="compartir"]')) {
            // Cancelar el menú de compartir también rechaza la promesa: no es un error.
            if (abierto?.blob) navigator.share({ files: [fichero(abierto)] }).catch(() => {});
        } else {
            const enlace = e.target.closest('a[target="_blank"]');

            if (enlace && mismoOrigen(enlace.href)) {
                e.preventDefault();
                abrir(enlace.href);
            }
        }
    });

    // Los botones que abren el PDF con window.open (exámenes, movimientos contables…).
    const abrirVentana = window.open;
    window.open = function (url, ...resto) {
        if (url && mismoOrigen(url) && visor()) {
            abrir(new URL(url, location.href).href);

            return null;
        }

        return abrirVentana.call(window, url, ...resto);
    };
}
