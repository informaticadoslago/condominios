#!/bin/bash
set -euo pipefail

if [ $# -ne 1 ]; then
    echo "Uso: $0 <dominio>" >&2
    exit 1
fi

dominio="$1"
directorio="$(cd "$(dirname "$0")" && pwd)"
plantilla="$directorio/xestionmusical.conf"
destino="$directorio/${dominio}.conf"

# Ruta de sites-available y carpeta base de los vhosts: se pueden forzar con
# APACHE_SITES_AVAILABLE/APACHE_WWW (p.ej. en otra distro); si no, se deducen del SO
# (Mac con Homebrew vs. Ubuntu/Debian).
if [ -n "${APACHE_SITES_AVAILABLE:-}" ]; then
    sites_available="$APACHE_SITES_AVAILABLE"
elif [ "$(uname)" = "Darwin" ]; then
    sites_available="/opt/homebrew/etc/httpd/sites-available"
else
    sites_available="/etc/apache2/sites-available"
fi

if [ -n "${APACHE_WWW:-}" ]; then
    www_base="$APACHE_WWW"
elif [ "$(uname)" = "Darwin" ]; then
    www_base="/opt/homebrew/var/www"
else
    www_base="/var/www"
fi

if [ -n "${APACHE_SSL:-}" ]; then
    ssl_base="$APACHE_SSL"
elif [ "$(uname)" = "Darwin" ]; then
    ssl_base="/opt/homebrew/etc/httpd/ssl"
else
    ssl_base="/etc/apache2/ssl"
fi

# ${APACHE_LOG_DIR} lo define /etc/apache2/envvars en Ubuntu/Debian, así que ahí se deja
# literal en el .conf; Homebrew httpd no lo define, así que en Mac hay que resolverlo a
# una ruta real.
if [ -n "${APACHE_LOG_BASE:-}" ]; then
    log_base="$APACHE_LOG_BASE"
elif [ "$(uname)" = "Darwin" ]; then
    log_base="/opt/homebrew/var/log/httpd"
else
    log_base=""
fi

# En Ubuntu, sites-available/, la carpeta www y a2ensite son de root: hace falta sudo.
# En Mac, /opt/homebrew es del usuario (Homebrew), así que no.
sudo_cmd=""
if [ "$(uname)" != "Darwin" ]; then
    sudo_cmd="sudo"
fi

if [ ! -f "$plantilla" ]; then
    echo "No existe $plantilla" >&2
    exit 1
fi

if [ ! -d "$sites_available" ]; then
    echo "No existe $sites_available" >&2
    exit 1
fi

if [ ! -d "$www_base" ]; then
    echo "No existe $www_base" >&2
    exit 1
fi

if [ ! -d "$ssl_base" ]; then
    echo "No existe $ssl_base" >&2
    exit 1
fi

if [ -n "$log_base" ] && [ ! -d "$log_base" ]; then
    echo "No existe $log_base" >&2
    exit 1
fi

# Handler de PHP-FPM: en Ubuntu es el socket unix de php-fpm; en Mac (Homebrew) php-fpm no
# escucha por socket, sino por TCP en 127.0.0.1 (ver /opt/homebrew/etc/php/*/php-fpm.d/www.conf).
if [ -n "${APACHE_PHP_FPM:-}" ]; then
    php_fpm="$APACHE_PHP_FPM"
elif [ "$(uname)" = "Darwin" ]; then
    php_fpm="127.0.0.1:9184"
else
    php_fpm=""
fi

sed_args=(-e "s/xestionmusical/${dominio}/g" -e "s#/var/www/#${www_base}/#g" -e "s#/etc/apache2/ssl/#${ssl_base}/#g")

if [ -n "$log_base" ]; then
    sed_args+=(-e "s#\${APACHE_LOG_DIR}#${log_base}#g")
fi

if [ -n "$php_fpm" ]; then
    sed_args+=(-e "s#proxy:unix:/run/php/php8\.4-fpm\.sock|fcgi://localhost#proxy:fcgi://${php_fpm}#g")
fi

# Homebrew httpd (Mac) no escucha en 80/443 sino en 8080/8443 (ver Listen en httpd.conf),
# y usa vhosts *:8443 por nombre en vez de _default_:443 (que solo admite uno por puerto,
# y aquí conviven varios dominios). Basado en un .conf de Mac que ya funciona
# (sites-available/v3xestionmusical.conf).
if [ "$(uname)" = "Darwin" ]; then
    sed_args+=(
        -e 's#<VirtualHost \*:80>#<VirtualHost *:8080>#'
        -e 's#<VirtualHost _default_:443>#<VirtualHost *:8443>#'
        -e 's#https://%{SERVER_NAME}/\$1#https://%{SERVER_NAME}:8443/\$1#'
    )
fi

sed "${sed_args[@]}" "$plantilla" > "$destino"

$sudo_cmd mv "$destino" "$sites_available/${dominio}.conf"

echo "Creado $sites_available/${dominio}.conf"

# DocumentRoot del .conf: /var/www/<dominio> (o su equivalente) es un symlink a la
# carpeta del proyecto (este mismo directorio), no una copia.
enlace="$www_base/$dominio"

if [ -e "$enlace" ] || [ -L "$enlace" ]; then
    if [ -L "$enlace" ]; then
        echo "Ya existe el symlink $enlace -> $(readlink "$enlace")"
        read -r -p "¿Borrarlo y apuntarlo a este proyecto? [s/N] " respuesta

        if [[ "$respuesta" =~ ^[sS]$ ]]; then
            $sudo_cmd rm "$enlace"
        else
            echo "Se conserva el symlink existente, no se toca."
        fi
    else
        echo "$enlace ya existe y no es un symlink: no se toca." >&2
        exit 1
    fi
fi

if [ ! -e "$enlace" ] && [ ! -L "$enlace" ]; then
    $sudo_cmd ln -s "$directorio" "$enlace"
    echo "Symlink $enlace -> $directorio"
fi

# Para que ServerName/ServerAlias (<dominio>.virtual.lago) resuelvan en local.
entrada_hosts="127.0.0.1	${dominio}.virtual.lago"

if grep -qF "${dominio}.virtual.lago" /etc/hosts; then
    echo "Ya está en /etc/hosts: ${dominio}.virtual.lago"
else
    echo "$entrada_hosts" | sudo tee -a /etc/hosts > /dev/null
    echo "Añadido a /etc/hosts: $entrada_hosts"
fi

if command -v a2ensite > /dev/null 2>&1; then
    $sudo_cmd a2ensite "$dominio"
else
    echo "Aviso: a2ensite no encontrado, se omite." >&2
fi

# Recarga Apache para que sirva el sitio ya activado.
if [ "$(uname)" = "Darwin" ]; then
    brew services restart httpd
else
    $sudo_cmd systemctl reload apache2
fi
