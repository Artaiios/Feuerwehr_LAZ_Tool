# Changelog

Alle relevanten Änderungen am LAZ Übungs-Tracker werden in dieser Datei dokumentiert.

## [1.6.3] – 2026-03-25

### Geändert
- **Live-Save bei Anwesenheit:** Jeder Klick auf einen Status-Button speichert sofort nur diesen einen Eintrag – kein Speichern-Button mehr nötig
- **Keine Überschreibung mehr:** Selbst-Entschuldigungen von Mitgliedern werden beim Speichern durch den Admin nicht mehr versehentlich überschrieben
- **Entschuldigt-Labels:** Zeigt jetzt „🟡 selbst entsch." oder „🔵 durch Admin" an, damit sichtbar ist, wer den Status gesetzt hat
- **Kurze Bestätigung:** Nach jedem Live-Save erscheint ein „✓ gespeichert"-Indikator neben dem Teilnehmernamen

## [1.6.2] – 2026-03-25

### Geändert
- **Toggle-Verhalten:** Klick auf den bereits aktiven Status-Button setzt den Status zurück (kein Status gewählt)
- **API:** Leerer Status-Wert löscht den Attendance-Eintrag aus der Datenbank

## [1.6.1] – 2026-03-25

### Hinzugefügt
- **Anwesenheits-Tab komplett neu:** Aufklappbare Terminliste statt Dropdown-Menü, mit Zählern (✅ 🟡 ❌) pro Termin
- **Nächster Termin automatisch aufgeklappt**, jeder Termin einzeln auf-/zuklappbar
- **Wetter-Standort konfigurierbar** unter Admin → Einstellungen (Ortssuche via Open-Meteo Geocoding API)
- Neue DB-Spalten: `weather_location`, `weather_lat`, `weather_lng`

### Geändert
- Dashboard nutzt konfigurierte Koordinaten statt hardcoded Rutesheim für Wetterabfrage

## [1.6.0] – 2026-03-25

### Hinzugefügt
- **Entschuldigung zurückziehen:** Teilnehmer können selbst gesetzte Entschuldigungen zurückziehen, solange der Termin nicht begonnen hat
- **Konfigurierbare Übungsdauer** (Standard: 3h) – bestimmt ab wann ein Termin als beendet gilt
- Neue Hilfsfunktionen: `is_session_in_future()`, `is_session_ended()`, `get_next_session()`, `can_member_change_excuse()`
- Neuer API-Endpunkt `withdraw_excuse`
- Neue DB-Spalte: `session_duration_hours`

### Geändert
- Entschuldigen nur noch möglich wenn der Termin-Startzeitpunkt in der Zukunft liegt
- Vom Admin gesetzte Status (Anwesend/Fehlend) können vom Teilnehmer nicht mehr überschrieben werden
- „Nächster Termin" wechselt erst nach Ablauf der Übungsdauer zum Folge-Termin

## [1.5.0] – 2026-03-25

### Hinzugefügt
- **Frist-Countdown-Karten** für beide Fristen mit Tagen und verbleibenden Terminen
- **Wetter-Vorhersage** für den nächsten Termin (Open-Meteo API, kostenlos, 1h Cache)
- **„Mein Status"-Widget** (Cookie-basiert) – Teilnehmer wählt seinen Namen, sieht persönliche Ampel + Strafkasse

## [1.4.0] – 2026-03-25

### Geändert
- **Upgrade von Tailwind CSS 2.x auf 3.x** (CDN)
- **Farbcodierte Anwesenheits-Buttons:** Grün (Anwesend), Gelb (Entschuldigt), Rot (Fehlend)
- Nächster-Termin-Hervorhebung im Dashboard und auf der Teilnehmerseite

## [1.3.0] – 2026-03-25

### Behoben
- **SQL-Bug in Strafkasse-Statistik:** LEFT JOIN Summierung lieferte falsche Werte bei 0 Strafen
- Kreisdiagramm zeigt jetzt Anzahl der Strafen statt Euro-Betrag

### Geändert
- Vergangene Termine werden ausgegraut, nächster Termin farblich hervorgehoben
- Tabellenkopf visuell stärker von grauen Terminzeilen abgesetzt

## [1.2.0] – 2026-03-25

### Hinzugefügt
- **Strafenkatalog Inline-Edit:** Straftypen direkt in der Liste bearbeiten (Sortierung, Betrag, Beschreibung, Status)
- Sichtbares Label für das Sortierfeld im Hinzufügen-Formular

## [1.1.0] – 2026-03-25

### Hinzugefügt
- **Konfigurierbare Frist-Namen:** Anzeigenamen für Frist 1 und Frist 2 im Admin einstellbar
- Neue DB-Spalten: `deadline_1_name`, `deadline_2_name`

## [1.0.0] – 2026-03-25

### Hinzugefügt
- Vollständige LAZ-Übungsverwaltung mit öffentlichem Dashboard, Teilnehmer-Detailseite und Admin-Bereich
- Mehrere Jahrgänge/Events mit eigenen URLs und Admin-Tokens
- Anwesenheitsverwaltung mit Entschuldigungsfunktion und Kurzfristig-Warnung
- Strafenkatalog und Strafenverwaltung (Soft-Delete)
- Diagramme (Chart.js): Teilnahmen-Balkendiagramm, Zeitverlauf, Donut-Diagramm
- Frist-Tracking mit Ampelsystem (Grün/Gelb/Rot)
- Audit-Log mit CSV-Export
- Responsive Design (Mobile-First) mit Tailwind CSS
- Sicherheit: PDO Prepared Statements, CSRF-Tokens, XSS-Schutz
