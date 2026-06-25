# Hilfe-Bilder fuer CMS-Seiten

Dieser Ordner ist fuer statische Hilfe-Bilder gedacht, die auf CMS-Seiten verwendet werden.

## Oeffentliche URL

Die Dateien sind unter folgendem Pfad erreichbar:

`/modules/internautenav/views/img/help/<dateiname>`

Beispiel:

`/modules/internautenav/views/img/help/swisspass-de.png`

## Verwendung in einer CMS-Seite

```html
<img
  src="/modules/internautenav/views/img/help/swisspass-de.png"
  alt="Alterspruefung Schweizer-Pass"
/>
```

## Vorhandene Platzhalter

- `swisspass-de.png`
  ![Schweizer Pass Deutsch](swisspass-de.png)
- `swisspass-en.png`
  ![Schweizer Pass Englisch](swisspass-en.png)
- `aswisspasstoav.png`
  ![Schweizer Pass Felder für Alterskontrolle](swisspasstoav.png)
- `swissidtoav.png
  ![Schweizer ID Felder für Alterskontrolle](swissidtoav.png)
- eupass.png
  ![EU Pass Felder für Alterskontrolle](eupass.png)

## Benennungs-Konvention

- Nur Kleinbuchstaben
- Woerter mit Bindestrich trennen
- Nur ASCII-Zeichen in Dateinamen
- Sprachsuffix nutzen, falls noetig:
  - `-de`, `-en`, `-fr`, `-it`

## Hinweis

Keine Kunden-Uploads aus `uploads/` fuer CMS-Inhalte verwenden.
Diese sind fuer Verifikationsprozesse und Datenschutz-relevante Daten bestimmt.
