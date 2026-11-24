#!/bin/sh
set -eu

# Generated once, kept in a volume, so nobody has to edit an environment file.
key_file=/var/lib/home-showings/app.key

load_key() {
    if [ ! -s "$key_file" ]; then
        (umask 077 && php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$key_file")
    fi
    APP_KEY="$(cat "$key_file")"
    export APP_KEY
}

# The container ends as soon as nginx or php-fpm does.
serve() {
    php-fpm84 &
    fpm=$!
    nginx -e /dev/stderr -g 'daemon off;' &
    web=$!
    stopping=0
    trap 'stopping=1; kill -TERM "$fpm" "$web" 2>/dev/null || true' TERM INT
    # Polling, because "wait -n" in BusyBox misses a child killed by a signal.
    while kill -0 "$fpm" 2>/dev/null && kill -0 "$web" 2>/dev/null; do
        sleep 1
    done
    if [ "$stopping" -eq 1 ]; then
        wait "$fpm" "$web" || true
        exit 0
    fi
    # No wait here: a killed master leaves orphans behind, and they end with this process.
    echo "nginx or php-fpm stopped, ending the container" >&2
    exit 1
}

if [ "$1" = "serve" ]; then
    load_key
    php artisan optimize
    php artisan migrate --force
    php artisan db:seed --force
    serve
fi

exec "$@"
