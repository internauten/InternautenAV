# internautenav (PrestaShop 1.7.8+)

Modul zur MRZ-basierten Alters- und Identitaetspruefung fuer ausgewaehlte Versandarten.

## Features

- Modulname: `internautenav`
- Unterstuetzte Dokumenttypen:
  - CH ID (TD1, 3 MRZ-Zeilen)
  - CH Pass (TD3, 2 MRZ-Zeilen)
  - EU Pass (TD3, 2 MRZ-Zeilen)
- Unterschiedliche Eingabe je Dokumenttyp (2 oder 3 Zeilen)
- Eingabe der MRZ-Zeilen durch den Benutzer im Checkout
- Anzeige in der Versandart-Seite unter den konfigurierten Versandarten
- Pflichtpruefung nur fuer ausgewaehlte Versandarten (Konfiguration im Modul)
- Verifikation von:
  - Geburtsdatum aus MRZ
  - Name/Vorname gegen Lieferadresse
  - Alter >= 18
- Verifikation wird gespeichert:
  - **Registrierte Kunden:** Gespeichert in DB, bei Folgebestellungen nicht mehr erforderlich
  - **Gaeste:** Gespeichert in der Session der aktuellen Bestellung

## Installation

1. Ordner `internautenav` in den PrestaShop-Ordner `modules/` kopieren.
2. Modul im Backoffice installieren.
3. Unter Modul-Konfiguration die Versandarten auswaehlen, die MRZ-pflichtig sein sollen.

## Troubleshooting

### Modul wird nicht angezeigt im Checkout

1. **Konfiguration prüfen:**
   - Im Backoffice unter Module > Internautenav AV
   - Mindestens eine Versandart auswaehlen und speichern
   - Mit Mehrfach-Select (Ctrl+Click) mehrere auswählen

2. **Debug-Information:**
   - Aufruf: `http://dein-shop.de/modules/internautenav/debug.php`
   - Zeigt Modul-Status, Konfiguration und Carrier

3. **Checkout-Anforderungen:**
   - Versandart muss in der Konfiguration ausgewaehlt sein
   - Funktioniert fuer: registrierte Kunden UND Gaeste
   - Registrierte Kunden: Verifikation wird nur einmalig pro Kunde geprueft
   - Gaeste: Verifikation wird pro Gast-Bestellung geprueft (Session-basiert)

4. **Datenbankprüfung:**
   - Tabelle `wp_internautenav_customer_verification` muss existieren
   - Falls nicht: Modul neu installieren

## Technische Hinweise

- Kompatibel ab PrestaShop `1.7.8.0`.
- Modul nutzt die Hooks:
  - `displayAfterCarrier` (Anzeige nach Versandart)
  - `displayBeforeCarrier` (Alternative Anzeige)
  - `displayCarrierExtraContent` (Fallback)
  - `additionalCarrierFieldsForm` (Fallback)
  - `actionCarrierProcess` (Prüfung)
  - `actionValidateStepComplete` (Prüfung)
  - `actionFrontControllerSetMedia` (JS/CSS)
- Daten werden in Tabelle gespeichert:
  - `PREFIX_internautenav_customer_verification`
- JavaScript wird inline im Template geladen (Fallback für Hook-Probleme)

