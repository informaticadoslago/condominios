import Swal from "sweetalert2";
//import colores from './colores.js';

// or via CommonJS
//const Swal = require('sweetalert2')

//import Swal from 'sweetalert2/dist/sweetalert2.js'

// $this->dispatch('swal', [[
//     'title' => 'Hola!',
//     'text' => 'Esto viene desde Livewire!',
//     'icon' => 'success',
// ]]);


// console.log('SweetAlert2 importado:', Swal);

const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    },
});

// Ajusta el popup de SweetAlert al modo oscuro de la app (clase .dark en <html>/<body>)
function swalModoOscuro(opts) {
    const dark = document.documentElement.classList.contains("dark")
        || document.body.classList.contains("dark");
    return dark ? { ...opts, background: "#1f2937", color: "#e5e7eb" } : opts;
}

window.addEventListener("swal", function (e) {
    Swal.fire(swalModoOscuro(e.detail[0]));
    console.log(e.detail[0]);
});


window.addEventListener("swalConfirm", function (e) {
    Swal.fire(swalModoOscuro(e.detail[0])).then((result) => {
        if (result.isConfirmed) {
          console.log('Antes');          
            Livewire.dispatch(e.detail[0].confirmCallback, {id: e.detail[0].id});
        } else {
            Livewire.dispatch(e.detail[0].cancelCallback, {id: e.detail[0].id });
        }
    });
    console.log(e.detail[0]);
});

// Como swalConfirm pero con 3 opciones (Confirmar/Denegar/Cancelar), para cuando hay
// dos acciones posibles además de cancelar (p.ej. "¿activar o dejar en inicial?").
window.addEventListener("swalConfirmDeny", function (e) {
    Swal.fire(swalModoOscuro(e.detail[0])).then((result) => {
        if (result.isConfirmed) {
            Livewire.dispatch(e.detail[0].confirmCallback, {id: e.detail[0].id});
        } else if (result.isDenied) {
            Livewire.dispatch(e.detail[0].denyCallback, {id: e.detail[0].id});
        } else {
            Livewire.dispatch(e.detail[0].cancelCallback, {id: e.detail[0].id});
        }
    });
});

window.addEventListener("swalConfirmEditCliente", function (e) {
    Swal.fire(swalModoOscuro(e.detail[0])).then((result) => {
        if (result.isConfirmed) {
          console.log('Antes');          
            Livewire.dispatch("clienteEditConfirmAction", {id: e.detail[0].id});
        } else {
            Livewire.dispatch("clienteEditCancelAction", {id: e.detail[0].id });
        }
    });
    console.log(e.detail[0]);
});

window.addEventListener("toast-success", function (e) {
    Toast.fire({ icon: "success", title: e.detail[0].title });
    console.log(e.detail[0]);
});

window.addEventListener("toast-error", function (e) {
    Toast.fire({ icon: "error", title: e.detail[0].title });
    console.log(e.detail[0]);
});

// Livewire.on('redirect-a-url-origen', ({ url }) => {
//         window.location.href = url;
//     });

document.addEventListener('swalerta', e => {
    Swal.fire(swalModoOscuro(e.detail[0])).then(result => {
        if (result.isConfirmed && e.detail[0].redirectTo) {
            window.location.href = e.detail[0].redirectTo;
        }
    });
});