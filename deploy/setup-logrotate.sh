#!/usr/bin/env bash
# Installiert die logrotate-Regel fuer internautenav-cron.log auf dem Server.
# Aufruf: sudo bash deploy/setup-logrotate.sh
set -euo pipefail

TARGET=/etc/logrotate.d/internautenav-cron
LOGDIR=/var/www/wow9.internaut.ch/html/var/logs
CONF="$(cd "$(dirname "$0")" && pwd)/logrotate/internautenav-cron"
CLEANUP_SRC="$(cd "$(dirname "$0")" && pwd)/cleanup/prestashop-dated-logs-cleanup.sh"
CLEANUP_DST=/usr/local/sbin/prestashop-dated-logs-cleanup
CRON_SRC="$(cd "$(dirname "$0")" && pwd)/cleanup/prestashop-log-cleanup.cron.daily"
CRON_DST=/etc/cron.daily/prestashop-log-cleanup

if [[ $EUID -ne 0 ]]; then
    echo "Bitte als root ausfuehren: sudo bash $0"
    exit 1
fi

# Logverzeichnis anlegen falls nicht vorhanden
if [[ ! -d "$LOGDIR" ]]; then
    mkdir -p "$LOGDIR"
    chown www-data:www-data "$LOGDIR"
    echo "Verzeichnis $LOGDIR erstellt."
fi

# Regel kopieren und Rechte setzen
cp "$CONF" "$TARGET"
chown root:root "$TARGET"
chmod 644 "$TARGET"
echo "Regel installiert: $TARGET"

# Datierten Log-Cleanup installieren
cp "$CLEANUP_SRC" "$CLEANUP_DST"
chown root:root "$CLEANUP_DST"
chmod 755 "$CLEANUP_DST"
echo "Cleanup-Script installiert: $CLEANUP_DST"

cp "$CRON_SRC" "$CRON_DST"
chown root:root "$CRON_DST"
chmod 755 "$CRON_DST"
echo "Cron-Daily-Wrapper installiert: $CRON_DST"

# Syntax prüfen
logrotate -d "$TARGET"
echo ""
echo "logrotate-Konfiguration ist gueltig. Die Datei wird monatlich rotiert (oder frueher bei > 5M)."
echo "Aktuelle Regel:"
echo "  Datei:    /var/www/wow9.internaut.ch/html/var/logs/internautenav-cron.log"
echo "  Rotation: monatlich, 12 komprimierte Staende, maxsize 5M, leere Logs ueberspringen"
echo ""
echo "Datierte PrestaShop-Logs werden zusaetzlich taeglich bereinigt:"
echo "  Job:      /etc/cron.daily/prestashop-log-cleanup"
echo "  Script:   /usr/local/sbin/prestashop-dated-logs-cleanup"
echo "  Strategie: Loesche Dateien mit Datum im Namen nach 30 Tagen"
