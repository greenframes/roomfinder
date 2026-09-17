# P20 Raumfinder — Installations- und Bedienungsanleitung

## 1. Installation

1. Den Ordner `p20-raumfinder` als ZIP-Datei packen (falls Sie den Ordner direkt erhalten haben, `p20-raumfinder.zip` verwenden).
2. WordPress-Backend öffnen → **Plugins → Installieren → Plugin hochladen**.
3. ZIP-Datei auswählen, **Jetzt installieren**, anschließend **Aktivieren**.
4. Nach der Aktivierung erscheint im Backend links ein neuer Menüpunkt **„Raumfinder“**. Beim ersten Aktivieren werden automatisch:
   - Beispiel-Ausstattungsmerkmale, Verpflegungsoptionen und Veranstaltungsarten angelegt,
   - sechs Beispielräume (angelehnt an die aktuelle Website) als Startdaten erzeugt.

   Alle diese Daten können Sie danach frei bearbeiten, ergänzen, deaktivieren oder löschen.

## 2. Raumfinder auf einer Seite einbinden

Fügen Sie auf einer beliebigen Seite (z. B. „Tagungsräume mieten“) folgenden Shortcode ein:

```
[p20_raumfinder]
```

Alternativ steht im Block-Editor der Block **„P20 Raumfinder“** zur Verfügung.

## 3. Räume verwalten

**Raumfinder → Räume** zeigt alle Räume. **Raumfinder → Raum hinzufügen** legt einen neuen Raum an.

Pro Raum können Sie pflegen:

- **Allgemein**: interne Bezeichnung, Kurzbeschreibung (für die Ergebnis-Karten), ausführliche Beschreibung (Beitragseditor oben), Hauptbild (Beitragsbild rechts)
- **Bildergalerie**: beliebig viele Bilder für die Detailansicht
- **Kapazität & Größe**: m², Mindest- und Maximalpersonenzahl
- **Bestuhlung**: maximale Personenzahl je Bestuhlungsart (leer lassen = nicht angeboten)
- **Ausstattung**: pro Merkmal auswählen, ob „im Raum vorhanden“, „optional zubuchbar“ oder „nicht verfügbar“
- **Verpflegung**: verfügbare Optionen ankreuzen
- **Preise**: 2 Std. / 4 Std. / Ganztags, optional individueller Preis oder „Preis auf Anfrage“
- **Status & Sortierung**: Aktiv/Inaktiv, Raumart/Eignung, Reihenfolge (Seiten-Attribute „Reihenfolge“)

Inaktive Räume werden im Raumfinder nicht berücksichtigt, bleiben aber im Backend erhalten.

## 4. Ausstattung und Verpflegung erweitern

**Raumfinder → Ausstattung** bzw. **Raumfinder → Verpflegung**: neue Merkmale/Optionen einfach über das Formular „Neues Element hinzufügen“ anlegen. Sie erscheinen danach automatisch in jedem Raum-Formular und im Frontend-Wizard — ganz ohne Programmierung.

## 5. Einstellungen

**Raumfinder → Einstellungen**:

- Headline/Subline der Startseite des Wizards
- E-Mail-Adresse, an die neue Anfragen gesendet werden
- Datenschutz-Einwilligungstext und Link zur Datenschutzerklärung
- Auswahl, welche Technik-Merkmale im Wizard-Schritt „Technik“ angezeigt werden

## 6. Anfragen

Anfragen werden per `wp_mail()` versendet — es wird also automatisch Ihre vorhandene SMTP-Konfiguration genutzt (z. B. über ein SMTP-Plugin). Besucher erhalten zusätzlich eine automatische Eingangsbestätigung. Es handelt sich stets um eine unverbindliche Anfrage, keine Buchung.

## 7. Häufige Aufgaben ohne Programmierung

- **Neuen Raum ergänzen**: Raumfinder → Raum hinzufügen
- **Preis ändern**: Raum öffnen → Bereich „Preise“
- **Bild austauschen**: Raum öffnen → Hauptbild/Galerie
- **Kapazität ändern**: Raum öffnen → Bereich „Kapazität & Größe“ bzw. „Bestuhlung“
- **Neue Technik hinzufügen**: Raumfinder → Ausstattung → neues Merkmal anlegen, anschließend bei den betroffenen Räumen aktivieren
- **Raum deaktivieren**: Raum öffnen → Status auf „Inaktiv“
- **Cateringoption ergänzen**: Raumfinder → Verpflegung → neue Option anlegen
