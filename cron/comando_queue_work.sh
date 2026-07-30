#!/bin/bash

# php /var/www/doslagomusica/artisan queue:work --rest=2 --stop-when-empty --tries=2 --queue=backup >> /var/www/doslagomusica/storage/logs/queue_backup.log 2>&1 &

PHP=/usr/bin/php8.4
ART=/var/www/xestionmusical/artisan
LOGS=/var/www/xestionmusical/storage/logs

# Opción 1: procesa informes y backup secuencialmente en un solo proceso PHP.
# backup espera a que terminen los informes antes de empezar.
# php /var/www/doslagomusica/artisan queue:work --rest=2 --stop-when-empty --tries=2 --queue=default,informes,backup >> /var/www/doslagomusica/storage/logs/queue_informes.log 2>&1

# Opción 2 (activa): procesa cada cola EN PARALELO, cada una en su propio proceso PHP y con log separado.
# El & lanza cada proceso en background; el script sale inmediatamente y cada PHP termina solo.
# La cola database bloquea los jobs en curso, por lo que no hay riesgo de doble ejecución entre ticks del cron.
# $PHP $ART queue:work --rest=2 --stop-when-empty --tries=2 --queue=informes >> $LOGS/queue_informes.log 2>&1 &

# $PHP $ART queue:work --rest=2 --stop-when-empty --tries=2 >> $LOGS/queue_default.log 2>&1 &

$PHP $ART queue:work --rest=2 --stop-when-empty --tries=2 --queue=backup >> $LOGS/queue_backup.log 2>&1 &

$PHP $ART queue:work --rest=2 --stop-when-empty --tries=2 --queue=EnviarCorreo >> $LOGS/queue_enviar_correo.log 2>&1 &


wait