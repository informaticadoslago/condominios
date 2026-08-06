<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Avisos por correo
    |--------------------------------------------------------------------------
    | Los avisos NUNCA se mandan solos: estos interruptores deciden si aparece el
    | botón que los envía. Con la variable a false el botón no sale y la pantalla
    | queda como si no existiera el aviso, sin que nadie pueda darle sin querer.
    */

    // Botón para avisar a los propietarios incluidos en una remesa de que se les va
    // a cargar el recibo.
    'enviar_email_al_enviar_remesa' => (bool) env('ENVIAR_EMAIL_AL_ENVIAR_REMESA', false),

    // Botón para avisar a los que pagan por transferencia de que les toca ingresar.
    'enviar_email_transferencias' => (bool) env('ENVIAR_EMAIL_TRANSFERENCIAS', false),

];
