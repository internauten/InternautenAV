# internautenav (PrestaShop 1.7.8+)

[![Release on Tag](https://github.com/internauten/InternautenAV/actions/workflows/release-on-tag.yml/badge.svg)](https://github.com/internauten/InternautenAV/actions/workflows/release-on-tag.yml)
[![Latest Release](https://img.shields.io/github/v/release/internauten/InternautenAV)](https://github.com/internauten/InternautenAV/releases)

Modul zur Alters- und Identitätsprüfung für ausgewählte Versandarten via MRZ-Scan oder Dokumenten-Upload.

## Features

- Modulname: `internautenav`
- Unterstützte Dokumenttypen im Checkout:
  - **MRZ-Scan:** CH ID (TD1, 3 Zeilen), CH Pass / EU Pass (TD3, 2 Zeilen)
  - **Dokumenten-Upload:** Bild des Ausweises hochladen (JPG, JPEG, PNG, BMP, GIF, WMF – max. 10 MB)
- Pflichtprüfung nur für ausgewählte Versandarten (Konfiguration im Backoffice)
- Verifikation von Geburtsdatum, Name/Vorname gegen Lieferadresse, Alter >= 18 (MRZ)
- Verifikation wird gespeichert:
  - **Registrierte Kunden:** In DB, bei Folgebestellungen nicht mehr erforderlich
  - **Gäste:** Session der aktuellen Bestellung
- Admin-Panel in der Bestellübersicht zeigt hochgeladene Dokumente mit Download-Link
- Admin kann Prüfung manuell als **bestanden** oder **abgelehnt** markieren – löscht Dokumente sofort DSGVO-konform
- Status-Badge oben auf der Bestellseite zeigt den aktuellen Prüfungsstatus auf einen Blick
- DSGVO-konformer Upload-Retention-Cleanup (90 Tage Aufbewahrung)
- Automatischer GitHub-Release bei Tag-Push (`vX.Y.Z`) inkl. ZIP-Artefakt aus `internautenav/`
- Datenschutzerklärung im Checkout verknüpfbar: konfigurierbare CMS-Seite mit Fallback auf Modul-Beispielseite

## GitHub Release Automation

Der Workflow liegt unter `.github/workflows/release-on-tag.yml`.

Bei jedem Push eines Tags im Format `v1.0.0` wird automatisch ein GitHub-Release erstellt.

### Was der Workflow macht

- Trigger: `push` auf Tags nach Muster `v*.*.*`
- Release-Titel: `Release vX.Y.Z`
- Release-Text:
  - Commit-Liste zwischen letztem Tag und aktuellem Tag (inkl. Commit-Subject und Commit-Body)
  - Zusätzlich automatisch generierte GitHub Release Notes
- Release-Asset: ZIP-Datei `internautenav-vX.Y.Z.zip` aus dem Unterverzeichnis `internautenav/`

### Verwendung

```bash
git tag v1.0.0
git push origin v1.0.0
```

## Installation

1. Ordner `internautenav` in den PrestaShop-Ordner `modules/` kopieren.
2. Modul im Backoffice installieren.
3. Unter Modul-Konfiguration die Versandarten auswählen, die Altersverifikation erfordern.
4. Optional: Datenschutzerklärung als CMS-Seite hinterlegen (siehe unten).

## DSGVO Upload-Cleanup / Cron

## Datenschutzerklärung im Checkout

Im Checkout-Formular wird ein Link zur Datenschutzerklärung angezeigt. Dieser Link kann im Modul-Backend konfiguriert werden.

### Konfiguration (Backoffice)

Im Backoffice unter **Module → Internautenav AV → Konfiguration** findest du das Dropdown **„Datenschutzerklärung (CMS-Seite)"**:

| Auswahl                           | Verhalten                                               |
| --------------------------------- | ------------------------------------------------------- |
| — Modul-Beispielseite verwenden — | Link zeigt auf die integrierte Beispielseite des Moduls |
| CMS-Seite #ID – Titel             | Link zeigt direkt auf die gewählte PrestaShop CMS-Seite |

Unterhalb des Dropdowns wird ein **Statusindikator** eingeblendet:

| Farbe | Bedeutung                                                          |
| ----- | ------------------------------------------------------------------ |
| grün  | CMS-Seite gültig und aktiv                                         |
| rot   | CMS-Seite nicht gefunden oder inaktiv – Fallback auf Beispielseite |
| grau  | Keine CMS-Seite gewählt – Beispielseite aktiv                      |

### Beispielseite (Entwicklung / Platzhalter)

Das Modul enthält eine integrierte Beispiel-Datenschutzseite unter:

```
modules/internautenav/views/templates/front/privacy.tpl
```

Sie ist über den Modul-Frontcontroller erreichbar:

```
https://shop.example.com/module/internautenav/privacy
```

Die Seite dient nur als Platzhalter für die Entwicklung. Für den Produktivbetrieb bitte eine eigene CMS-Seite im PrestaShop-Backend anlegen und dort im Modul verknüpfen.

### Umstellung auf CMS-Seite

1. Im PrestaShop-Backend unter **Design → Seiten** eine neue CMS-Seite mit der finalen Datenschutzerklärung anlegen.
2. Im Modul-Backend die neue CMS-Seite im Dropdown auswählen.
3. Speichern – der Statusindikator wechselt auf grün, der Checkout-Link zeigt ab sofort auf die CMS-Seite.

Hochgeladene Dokumente werden nach **90 Tagen** automatisch gelöscht. Der Cleanup-Mechanismus läuft auf zwei Wegen:

### 1. Automatisch über PrestaShop Cronjobs-Modul (`ps_cronjobs`)

Das Modul registriert den Hook `actionCronJob`. Wenn das offizielle PS-Cronjobs-Modul installiert ist, wird der Cleanup täglich über dessen Cron-Aufruf ausgelöst.

### 2. Direkter HTTP/CLI-Aufruf via `cron.php`

Die genaue Cron-URL inkl. Token findest du im Backoffice unter:  
**Module → InternautenAV → Konfiguration → DSGVO Upload-Cleanup → Cron-URL**

Eintragen solltest du sie unter dem Benutzer www-data:

```bash
sudo crontab -u www-data -e
```

**Webserver (wget/curl, täglich um 03:00 Uhr):**

```bash
0 3 * * * wget -q "https://shop.example.com/modules/internautenav/cron.php?token=TOKEN" -O /dev/null
```

**PHP-CLI:**

```bash
0 3 * * * php /var/www/html/modules/internautenav/cron.php --token=TOKEN
```

> Der Token basiert auf `_COOKIE_KEY_` und ändert sich nicht, solange der PS-Sicherheitsschlüssel gleich bleibt.

**Fallback:** Solange kein Cron eingerichtet ist, läuft der Cleanup gedrosselt (max. alle 6 Stunden) beim Seitenaufruf durch Kunden mit.

### Manueller Cleanup im Backoffice

Unter Modul-Konfiguration → DSGVO Upload-Cleanup stehen zwei Buttons zur Verfügung:

- **Cleanup jetzt ausführen** – löscht alle Uploads älter als 90 Tage
- **Alle Dateien ohne Altersprüfung löschen** – löscht alle Uploads, die keiner abgeschlossenen Bestellung zugeordnet sind

### Status-Badge in der Bestellansicht

Oben auf jeder Bestellseite im Backoffice wird automatisch ein farbiges Status-Badge eingeblendet:

| Badge                           | Farbe | Wann                                                     |
| ------------------------------- | ----- | -------------------------------------------------------- |
| ✓ Prüfung automatisch bestanden | grün  | MRZ oder Upload wurde automatisch validiert              |
| ✓ Prüfung manuell bestanden     | grün  | Admin hat „Prüfung bestanden" geklickt                   |
| ⚠ Prüfung manuell erledigen     | gelb  | Dokument hochgeladen, aber noch keine Admin-Entscheidung |
| ✗ Prüfung abgelehnt             | rot   | Admin hat „Prüfung abgelehnt" geklickt                   |
| ? Keine Prüfung vorhanden       | grau  | Versandart ist pflichtig, aber kein Eintrag gefunden     |
| 🚚 Prüfung bei Übergabe         | blau  | Verwendete Versandart erfordert keine Online-Prüfung     |

### Manuelle Prüfungsentscheidung in der Bestellansicht

In der Backoffice-Bestellansicht erscheint unterhalb der Dokumenten-Liste ein Entscheidungsbereich mit zwei Buttons:

- **✓ Prüfung bestanden** – loggt die Entscheidung, trägt den Kunden als verifiziert in `customer_verification` ein (nur bei registrierten Kunden, sodass bei Folgebestellungen keine erneute Prüfung erforderlich ist) und löscht alle Dokumente sofort DSGVO-konform
- **✗ Prüfung abgelehnt** – loggt die Ablehnung, löscht alle Dokumente sofort DSGVO-konform (kein Eintrag in `customer_verification`)

Beide Aktionen sind token-gesichert und erfordern eine Bestätigung via Browser-Dialog.

## Troubleshooting

### Modul wird nicht angezeigt im Checkout

1. **Konfiguration prüfen:**
   - Im Backoffice unter Module > Internautenav AV
   - Mindestens eine Versandart auswählen und speichern

2. **Debug-Information:**
   - Aufruf: `http://dein-shop.de/modules/internautenav/debug.php`
   - Zeigt Modul-Status, Konfiguration und Carrier

3. **Checkout-Anforderungen:**
   - Versandart muss in der Konfiguration ausgewählt sein
   - Funktioniert für: registrierte Kunden UND Gäste
   - Registrierte Kunden: Verifikation wird nur einmalig pro Kunde geprüft
   - Gäste: Verifikation wird pro Gast-Bestellung geprüft (Session-basiert)

4. **Datenbankprüfung:**
   - Tabellen `internautenav_customer_verification`, `internautenav_verification_log` und `internautenav_uploaded_documents` müssen existieren
   - Falls nicht: Modul neu installieren

## Technische Hinweise

- Kompatibel ab PrestaShop `1.7.8.0`
- Registrierte Hooks:
  - `actionFrontControllerSetMedia` – JS/CSS laden
  - `displayPaymentTop` – Modal-Anzeige im Checkout
  - `actionCarrierProcess` / `actionValidateStepComplete` – MRZ/Upload-Prüfung
  - `actionValidateOrder` – Upload-Datei der Bestellung zuordnen
  - `displayAdminOrderMainBottom` / `displayAdminOrder` – Upload-Panel im Backoffice
  - `displayAdminOrderTop` – Status-Badge oben auf der Bestellseite
  - `actionCronJob` – DSGVO-Cleanup via PS-Cronjobs-Modul
- Dateispeicherung: `modules/internautenav/uploads/` (abgeschlossen) bzw. `uploads/pending/` (temporär bis Bestellabschluss)
- Download-Links sind token-gesichert (kein Employee-Session-Zugriff erforderlich)

## Develop

Damit die Container bei jedem neuen Modul nicht jedesmal neu erstellt werden müssen, versuchen wir es mit Symlinks.

Voraussetzungen: im Compose hat es unter volumes einen Eintrag `- /home/youruser/internauten:/internauten`

1. Bash ins WSL2 und holen des Repos aus dem Fork:
   ```bash
   cd ~/internauten
   git clone https://github.com/yourgithub/InternautenAV.git
   ```
2. Owner, Group und Rights setzen:

   ```bash
   sudo chmod -R g+s .
   sudo chgrp -R www-data .
   sudo setfacl -R -d -m g:www-data:rwx .
   sudo setfacl -R -m g:www-data:rwx .

   oder

   cd ~/internauten/InternautenAV/internautenav/controllers
   sudo chown -R www-data:www-data .
   ```

3. Bash in den Container und Symlink erstellen:
   ```bash
   ln -s /internauten/InternautenAV/internautenav /var/www/html/modules/internautenav
   chown -h www-data:www-data /var/www/html/modules/internautenav
   ```
4. Modul im PrestaShop-Backoffice unter Modul-Manager installieren und konfigurieren.

## Cron-Erklaerung

Falls der Cleanup als System-Cron eingerichtet wird, helfen die folgenden Kurzerklaerungen:

### `sudo crontab -u www-data -e`

- `sudo` fuehrt den Befehl mit erweiterten Rechten aus
- `crontab` bearbeitet die Cronjobs eines Benutzers
- `-u www-data` meint: bearbeite den Cron des Benutzers `www-data`
- `-e` oeffnet den Cronjob-Editor

Der Benutzer `www-data` ist auf Debian/Ubuntu-Systemen ueblicherweise der Webserver-Benutzer. Der Cronjob laeuft damit mit denselben Dateirechten wie PrestaShop.

### `0 3 * * *`

Das ist der Zeitplan im Cron-Format und bedeutet: taeglich um `03:00` Uhr.

Die fuenf Felder von links nach rechts sind:

```text
┌ Minute
│ ┌ Stunde
│ │ ┌ Tag im Monat
│ │ │ ┌ Monat
│ │ │ │ ┌ Wochentag
│ │ │ │ │
0 3 * * *
```

- `0` bei Minute: zur Minute 0
- `3` bei Stunde: um 3 Uhr nachts
- `*` bei Tag im Monat: an jedem Tag des Monats
- `*` bei Monat: in jedem Monat
- `*` bei Wochentag: an jedem Wochentag

### `2>&1`

Das ist Shell-Syntax fuer die Umleitung von Fehlerausgaben:

- `1` ist `stdout`, also normale Ausgabe
- `2` ist `stderr`, also Fehlermeldungen
- `>> logfile` haengt die normale Ausgabe an eine Datei an
- `2>&1` leitet die Fehlerausgabe auf dasselbe Ziel wie die normale Ausgabe um

Beispiel:

```bash
0 3 * * * PS_ROOT_DIR=/var/www/wow9.internaut.ch/html /usr/bin/php /var/www/wow9.internaut.ch/html/modules/internautenav/cron.php --token=DEIN_TOKEN >> /var/www/wow9.internaut.ch/html/var/logs/internautenav-cron.log 2>&1
```

Damit landen sowohl normale Ausgaben als auch Fehlermeldungen in derselben Logdatei.

### Logrotate-Vorlage

Wenn die Cron-Ausgabe in eine Datei geschrieben wird, kann Ubuntu sie mit `logrotate` automatisch bereinigen. Eine passende Vorlage fuer diesen Server liegt als Beispiel unter:

```text
deploy/logrotate/internautenav-cron
```

Auf dem Server aktivierst du sie einmalig mit dem mitgelieferten Script:

```bash
sudo bash deploy/setup-logrotate.sh
```

Das Script legt `/etc/logrotate.d/internautenav-cron` an, setzt die Rechte und fuehrt direkt einen Dry-Run aus, um die Konfiguration zu pruefen. Die Regel rotiert die Datei monatlich, behaelt 12 komprimierte Staende und rotiert bei Bedarf frueher, falls die Datei groesser als 5 MB wird.

### Hybrid-Strategie fuer PrestaShop-Logs

PrestaShop erzeugt oft datierte Tages-Logs (z.B. mit `YYYY-MM-DD` im Namen). Diese sollten nicht erneut mit `logrotate` rotiert werden. Stattdessen nutzt das Setup-Script eine Hybrid-Strategie:

- Nicht-datierte Logs: Rotation ueber `logrotate` (monatlich, komprimiert)
- Datierte Logs: taeglicher Cleanup per `find` und Loeschung nach 30 Tagen

Installierte Bestandteile:

```text
/etc/logrotate.d/internautenav-cron
/usr/local/sbin/prestashop-dated-logs-cleanup
/etc/cron.daily/prestashop-log-cleanup
```

Vorlagen im Repository:

```text
deploy/logrotate/internautenav-cron
deploy/cleanup/prestashop-dated-logs-cleanup.sh
deploy/cleanup/prestashop-log-cleanup.cron.daily
```

### Vorlage testen

```bash
sudo logrotate -d /etc/logrotate.conf
sudo logrotate -d /etc/logrotate.d/internautenav-cron
sudo /usr/local/sbin/prestashop-dated-logs-cleanup /var/www/wow9.internaut.ch/html/var/logs 30
```

## License

This project is licensed under the MIT License. See details [`LICENSE`](LICENSE).

Copyright (c) 2026 die.internauten.ch GmbH
