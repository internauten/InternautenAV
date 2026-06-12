#!/usr/bin/env bash
# Loescht datierte PrestaShop-Logdateien aelter als N Tage.
# Erwartete Dateinamen enthalten ein Datum wie YYYY-MM-DD und enden auf .log oder .log.gz.
set -euo pipefail

LOGDIR="${1:-/path/to/prestashop/var/logs}"
KEEP_DAYS="${2:-30}"

if [[ ! -d "$LOGDIR" ]]; then
    echo "Logverzeichnis nicht gefunden: $LOGDIR" >&2
    exit 1
fi

if ! [[ "$KEEP_DAYS" =~ ^[0-9]+$ ]]; then
    echo "KEEP_DAYS muss numerisch sein." >&2
    exit 1
fi

# Nur datierte Logdateien bereinigen, z.B. 2026-06-12.log, error-2026-06-12.log, ... .log.gz
find "$LOGDIR" \
    -maxdepth 1 \
    -type f \
    \( -name "*.log" -o -name "*.log.gz" \) \
    -regextype posix-extended \
    -regex ".*[0-9]{4}-[0-9]{2}-[0-9]{2}.*\\.log(\\.gz)?$" \
    -mtime "+$KEEP_DAYS" \
    -print \
    -delete
