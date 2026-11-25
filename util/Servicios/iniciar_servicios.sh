#!/bin/bash
set -e

php /var/www/html/Modulos/OpenDoors/Nucleo/util/procesar_aperturas.php &
aperturas_pid=$!
apache2-foreground &
apache_pid=$!

detener() {
    for pid in "$aperturas_pid" "$apache_pid"; do
        if kill -0 "$pid" 2>/dev/null; then
            kill -TERM "$pid"
        fi
    done
    wait "$aperturas_pid" "$apache_pid" || true
}
trap detener EXIT
trap 'exit 143' TERM
trap 'exit 130' INT

set +e
wait -n "$aperturas_pid" "$apache_pid"
status=$?
if [ "$status" -eq 0 ]; then
    status=1
fi
exit "$status"
