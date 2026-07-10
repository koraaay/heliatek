# Heliatek Website – TYPO3 v13 mit Content-Blocks-Komponenten

Dieses Repository übersetzt das Heliatek-Design-System und den Website-Prototyp
in eine TYPO3-Umgebung. **Jede Sektion der Website ist eine eigenständige
Komponente (Content Block)**, die Redakteur:innen ohne technische Kenntnisse im
TYPO3-Backend anlegen, befüllen und umsortieren können.

## Aufbau

```
composer.json                          TYPO3-v13-Projekt (Composer)
packages/heliatek_sitepackage/         Sitepackage-Extension
├── Configuration/Sets/Sitepackage/    Site Set: TypoScript, Backend-Layout
├── ContentBlocks/ContentElements/     ⭐ Die Sektions-Komponenten
│   ├── hero/                          Hero-Bühne (Bild, Headline, Buttons)
│   ├── text_media/                    Text & Bild (Position wählbar, RTE)
│   ├── feature_grid/                  Vorteile/Features (Kacheln mit Icons)
│   ├── stats/                         Zahlen & Fakten (animierte Zähler)
│   ├── card_grid/                     Karten für Produkte/Lösungen
│   ├── quote/                         Zitat / Referenzstimme
│   ├── accordion/                     Akkordeon / FAQ
│   ├── logo_strip/                    Partner-/Referenzlogos
│   └── cta/                           Call-to-Action-Banner
└── Resources/
    ├── Private/                       Seitentemplate, Header, Footer (Fluid)
    └── Public/Css/tokens.css          ⭐ Design-Tokens (zentrale Stellschrauben)
```

## Design-System anpassen

Alle Farben, Schriften, Abstände und Radien liegen als CSS-Custom-Properties in
`packages/heliatek_sitepackage/Resources/Public/Css/tokens.css`. Die Werte sind
an den Heliatek-Markenauftritt angelehnt (Petrol + Heliatek-Grün) und können
1:1 gegen die Tokens aus dem eigenen Design-System (z. B. Figma-Variablen)
getauscht werden – **alle Komponenten ziehen sich ihr Styling ausschließlich
aus dieser Datei**.

## Installation (lokal, z. B. mit DDEV)

```bash
composer install
# TYPO3 einrichten (Datenbank, Admin-User):
vendor/bin/typo3 setup
```

Danach im Backend:

1. **Site Management → Sites**: der Site das Set **„Heliatek Sitepackage"**
   zuweisen (Tab *Sets*). Damit sind TypoScript, Backend-Layout und alle
   Komponenten aktiv – es muss kein TypoScript von Hand eingebunden werden.
2. Seite anlegen, als Backend-Layout **„Standardseite"** wählen.

Nach Änderungen an einer `config.yaml` einmal ausführen:

```bash
vendor/bin/typo3 cache:flush
vendor/bin/typo3 extension:setup   # legt neue Datenbankfelder an
```

## Für Redakteur:innen: Seiten aus Sektionen zusammenbauen

1. Seite im Seitenbaum öffnen → **„+ Inhalt"** klicken.
2. Im Dialog den Reiter **„Heliatek Sektionen"** wählen.
3. Gewünschte Sektion anklicken und die Felder ausfüllen – jedes Feld hat
   eine deutsche Beschriftung (Überschrift, Bild, Buttons, …).
4. Sektionen lassen sich per Drag & Drop umsortieren; die Reihenfolge im
   Backend entspricht der Reihenfolge auf der Website.

Wiederholbare Inhalte (z. B. einzelne Kacheln, Kennzahlen, FAQ-Einträge oder
Buttons) werden direkt im Element über **„Neu anlegen"** ergänzt und können
ebenfalls sortiert werden.

## Neue Sektion hinzufügen (für Entwickler:innen)

1. Ordner unter `ContentBlocks/ContentElements/<name>/` anlegen mit
   `config.yaml`, `templates/frontend.html`, `language/labels.xlf` und
   `assets/icon.svg` (eine bestehende Sektion als Vorlage kopieren).
2. Felder in der `config.yaml` deklarieren – ohne PHP, ohne TCA.
3. `vendor/bin/typo3 extension:setup && vendor/bin/typo3 cache:flush`

## Offene Punkte

- Die Design-Tokens in `tokens.css` sind Näherungswerte an die Heliatek-Marke.
  Sobald die Original-Tokens aus dem Design-System (Figma) vorliegen, dort
  ersetzen.
- Logo im Header/Footer ist aktuell eine Wortmarke; SVG-Logo unter
  `Resources/Public/Icons/` ablegen und in den Partials einbinden.
