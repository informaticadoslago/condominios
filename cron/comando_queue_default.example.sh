#!/bin/bash
/usr/bin/php8.1 /var/www/doslagomusica/artisan queue:work --rest=2 --stop-when-empty --tries=2
