//import './bootstrap';

import Swal from "sweetalert2";

// Exponer SweetAlert2 globalmente para Alpine
window.Swal = Swal;
import './mysweetalert2';
import './tab-style';

// El menú lateral de "Comunidades accesibles" no es reactivo (es PHP suelto en
// el layout, no un componente Livewire): recargamos la página al crear,
// modificar, dar de baja o reactivar una comunidad para que se recalcule.
window.addEventListener('comunidad-guardada', () => window.location.reload());