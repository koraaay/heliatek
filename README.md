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

## Design-System

Die Tokens stammen 1:1 aus dem Claude-Design-Projekt **„Heliatek"**
(`tokens/colors.css`, `typography.css`, `spacing.css`, `effects.css`) und
liegen gebündelt in
`packages/heliatek_sitepackage/Resources/Public/Css/tokens.css` –
**alle Komponenten ziehen sich ihr Styling ausschließlich aus dieser Datei**.

Markenregeln (aus dem Design-System-readme):

- **Mint `#94E8B5`** als einziger Akzent (CTAs, Kennzahl-Band, Footer-Fläche);
  Mint großflächig nur als Seitenabschluss.
- Serif-Headlines (Source Serif 4), Sans für UI/Fließtext (Inter).
- **Scharfe Kanten** (`border-radius: 0`), Kreise nur für Icons/Porträts.
- 1px-Hairlines statt Schatten; Buttons rechteckig mit Pfeil **→**.
- Keine Emoji, keine Icon-Fonts; USP-Icons als PNG-Assets.

**FLAG Font-Substitution:** Die Original-Webfonts lagen nicht vor; aktuell
werden Source Serif 4 + Inter per Google-Fonts-Import geladen. Vor dem
Livegang die echten Fonts als lokale `@font-face`-Regeln einbinden
(DSGVO: kein Remote-Google-Fonts-Load in Deutschland!).

## Installation (lokal mit DDEV)

DDEV-Konfiguration liegt bei (`.ddev/config.yaml` – PHP 8.3, MariaDB 10.11):

```bash
ddev start
ddev composer install
ddev exec vendor/bin/typo3 setup       # Datenbank + Admin-User anlegen
ddev launch /typo3                     # Backend öffnen
```

Ohne DDEV genügt `composer install` und `vendor/bin/typo3 setup`.

Danach im Backend:

1. **Root-Seite anlegen** (erste Seite im Seitenbaum, UID 1) und als
   Backend-Layout **„Standardseite"** wählen.
2. Die **Site-Konfiguration liegt bereits bei** (`config/sites/heliatek/`):
   Deutsch als Standardsprache, Englisch unter `/en/` (DE|EN wie im
   Design-System) und das Set „Heliatek Sitepackage" ist als Abhängigkeit
   eingetragen – TypoScript, Backend-Layout und alle Komponenten sind damit
   sofort aktiv.
3. **Assets übernehmen:** Bilder aus dem Claude-Design-Projekt „Heliatek"
   (USP-Icons `assets/USP-Icon-*.png`, Referenzfotos, Schichtaufbau,
   HeliaSol-v2-Konzeptbild) herunterladen und in **Dateiliste →
   fileadmin/** hochladen, damit Redakteure sie in den Komponenten
   auswählen können.

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

- Original-Webfonts nachliefern und Google-Fonts-Import in `tokens.css`
  durch lokale `@font-face`-Regeln ersetzen (DSGVO).
- Bild-Assets aus dem Design-System (USP-Icons, Referenzfotos,
  HeliaSol-v2-Konzeptbild) in die TYPO3-Dateiliste hochladen, damit
  Redakteure sie in den Komponenten auswählen können.
- Footer-Links (Impressum, Datenschutz, AGB) auf echte Seiten verlinken,
  sobald diese im Seitenbaum existieren.
