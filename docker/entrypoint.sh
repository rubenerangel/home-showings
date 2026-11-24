#!/bin/sh
set -eu

# Generated once, kept in a volume, so nobody has to edit an environment file.
keys=/var/lib/home-showings

generate_keys() {
    umask 077
    if [ ! -s "$keys/app.key" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$keys/app.key.new"
        mv "$keys/app.key.new" "$keys/app.key"
    fi
    if [ ! -s "$keys/vapid" ]; then
        APP_KEY="$(cat "$keys/app.key")" php artisan webpush:vapid --show --no-ansi > "$keys/vapid.new"
        mv "$keys/vapid.new" "$keys/vapid"
    fi
    umask 022
}

load_keys() {
    APP_KEY="$(cat "$keys/app.key")"
    VAPID_PUBLIC_KEY="$(sed -n 's/^VAPID_PUBLIC_KEY=//p' "$keys/vapid")"
    VAPID_PRIVATE_KEY="$(sed -n 's/^VAPID_PRIVATE_KEY=//p' "$keys/vapid")"
    export APP_KEY VAPID_PUBLIC_KEY VAPID_PRIVATE_KEY
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
    generate_keys
    load_keys
    php artisan optimize
    php artisan migrate --force
    echo "database is listening and its schema is up to date"
    php artisan db:seed --force
    serve
fi

if [ "$1" = "worker" ]; then
    load_keys
    exec php artisan queue:work --sleep=1
fi

exec "$@"
