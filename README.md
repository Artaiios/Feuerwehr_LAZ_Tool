# 🔥 LAZ Übungs-Tracker

Webanwendung zur Verwaltung von Leistungsabzeichen-Übungen (LAZ) für Freiwillige Feuerwehren.

![PHP](https://img.shields.io/badge/PHP-8.0+-blue) ![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange) ![Tailwind](https://img.shields.io/badge/Tailwind_CSS-3.x-38bdf8) ![License](https://img.shields.io/badge/License-MIT-green)

---

## Architektur

Die Anwendung hat eine zweistufige Verwaltungsstruktur mit klarer Rollentrennung:

```
┌──────────────────────────────────────────────────────────────────┐
│                     🔑 SERVER-ADMIN                              │
│                  admin.php?token={...}                            │
│                                                                  │
│  • Events erstellen / löschen          • Globale Einstellungen   │
│  • Admin-URLs verwalten & vergeben     • Organisationsname       │
│  • Übersicht aller Events              • Admin E-Mail            │
│  • Globales Audit-Log                  • Öffentliche Startseite  │
├──────────┬───────────────────────────────────────┬───────────────┤
│          │                                       │               │
│          ▼                                       ▼               │
│  ┌──────────────────────┐          ┌──────────────────────┐      │
│  │  🔑 EVENT-ADMIN       │          │  🔑 EVENT-ADMIN       │      │
│  │  ?event=...&admin=...│          │  ?event=...&admin=...│      │
│  │                      │          │                      │      │
│  │  • Teilnehmer        │          │  • Teilnehmer        │      │
│  │  • Termine           │          │  • Termine           │      │
│  │  • Anwesenheit       │          │  • Anwesenheit       │      │
│  │  • Strafenkatalog    │          │  • Strafenkatalog    │      │
│  │  • Strafen/Strafkasse│          │  • Strafen/Strafkasse│      │
│  │  • Einstellungen     │          │  • Einstellungen     │      │
│  │  • Audit-Log         │          │  • Audit-Log         │      │
│  ├──────────────────────┤          ├──────────────────────┤      │
│  │  🌐 DASHBOARD         │          │  🌐 DASHBOARD         │      │
│  │  ?event=...          │          │  ?event=...          │      │
│  │                      │          │                      │      │
│  │  • Frist-Countdowns  │          │  • Frist-Countdowns  │      │
│  │  • Wetter            │          │  • Wetter            │      │
│  │  • Mein Status       │          │  • Mein Status       │      │
│  │  • Terminliste       │          │  • Terminliste       │      │
│  │  • Teilnehmer-Tabelle│          │  • Teilnehmer-Tabelle│      │
│  ├──────────────────────┤          ├──────────────────────┤      │
│  │  👤 TEILNEHMER        │          │  👤 TEILNEHMER        │      │
│  │  ?event=...&member=..│          │  ?event=...&member=..│      │
│  │                      │          │                      │      │
│  │  • Fortschritt       │          │  • Fortschritt       │      │
│  │  • Entschuldigung    │          │  • Entschuldigung    │      │
│  │  • Strafenliste      │          │  • Strafenliste      │      │
│  └──────────────────────┘          └──────────────────────┘      │
│       Event A (z.B. LAZ Bronze)        Event B (z.B. LAZ Silber) │
└──────────────────────────────────────────────────────────────────┘
```

**Server-Admin** — Erstellt und verwaltet Events, vergibt Admin-URLs an Verantwortliche.

**Event-Admin** — Verwaltet ein einzelnes Event: Teilnehmer, Termine, Anwesenheit, Strafen. Erhält eine geheime Token-URL vom Server-Admin.

**Dashboard** — Öffentliche Übersicht eines Events. Wird per URL an Teilnehmer weitergegeben.

**Teilnehmer-Detail** — Persönliche Seite pro Teilnehmer mit Fortschritt und Entschuldigungsfunktion.

---

## Funktionen

### Server-Admin (`admin.php`)
- Events erstellen mit konfigurierbaren Fristen und optionalem Strafenkatalog-Template
- Event-URLs (Dashboard + Admin) direkt einsehen und kopieren
- Globaler Organisationsname (pro Event überschreibbar)
- Administrator E-Mail (wird im Footer und auf Fehlerseiten angezeigt)
- Öffentliche Startseite ein-/ausschaltbar
- Globales Audit-Log über alle Events
- Events archivieren und löschen

### Event-Admin (`index.php?event=...&admin=...`)
- Teilnehmer verwalten (einzeln + Bulk-Import)
- Termine verwalten (einzeln + Bulk-Import)
- Anwesenheit eintragen (aufklappbare Terminliste, Live-Save pro Klick)
- Strafenkatalog mit Inline-Bearbeitung und Sortierung
- Strafen zuweisen, Strafkasse-Statistik mit Diagrammen
- Event-Einstellungen: Name, Fristen, Übungsdauer, Wetter-Standort
- Frist 1 (Zwischenziel) optional aktivierbar
- Audit-Log mit Filtern und CSV-Export

### Dashboard (`index.php?event=...`)
- Frist-Countdown-Karten (Hauptfrist + optionales Zwischenziel)
- Nächster Termin mit Wetter-Vorhersage (Open-Meteo API)
- „Mein Status"-Widget (Cookie-basiert) mit persönlicher Ampel
- Diagramme: Teilnahmen pro Teilnehmer, Zeitverlauf
- Sortierbare Teilnehmer-Tabelle mit Frist-Ampelsystem
- Terminliste mit farblicher Hervorhebung

### Teilnehmer-Detail (`index.php?event=...&member=...`)
- Fortschrittsbalken für aktive Fristen
- Donut-Diagramm (Anwesend / Entschuldigt / Fehlend / Ausstehend)
- Entschuldigung setzen und zurückziehen (vor Übungsbeginn)
- Persönliche Strafenliste

---

## Technische Details

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.0+ (reines PHP, kein Framework) |
| Datenbank | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | Tailwind CSS 3.x (CDN), Chart.js 4.x (CDN) |
| Wetter | Open-Meteo API (kostenlos, kein API-Key) |
| Hosting | Shared Webspace (z.B. IONOS/1&1), kein Node.js nötig |

**Sicherheit:** PDO Prepared Statements, CSRF-Tokens, XSS-Schutz, Token-basierter Zugang (kein Login-System).

---

## Installation

### 1. Dateien hochladen

Lade alle Dateien per FTP auf deinen Webspace hoch.

### 2. Konfiguration

Kopiere `config.example.php` zu `config.php` und trage deine Datenbank-Zugangsdaten ein:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'laz_tracker');
define('DB_USER', 'dein_user');
define('DB_PASS', 'dein_passwort');
```

### 3. Ersteinrichtung

Rufe `https://deine-domain.de/setup.php` im Browser auf. Das Setup erstellt:
- Alle Datenbanktabellen
- Einen Server-Admin-Token

**Speichere die Server-Admin URL sicher ab!**

### 4. Setup sperren

In `config.php` setzen:
```php
define('SETUP_COMPLETE', true);
```

### 5. Erstes Event erstellen

Öffne die Server-Admin URL und erstelle dein erstes Event. Du erhältst zwei URLs:
- **Öffentlich** — für Teilnehmer (Dashboard)
- **Admin** — für den Event-Verantwortlichen

---

## Dateistruktur

```
├── config.example.php           # Konfigurations-Template
├── admin.php                    # Server-Admin Router
├── index.php                    # Event-Router (Dashboard/Member/Admin)
├── api.php                      # AJAX-Endpunkte
├── db.php                       # Datenbankverbindung & Funktionen
├── setup.php                    # Ersteinrichtung
├── views/
│   ├── server_admin.php         # Server-Admin UI
│   ├── overview.php             # Öffentliche Event-Übersicht
│   ├── dashboard.php            # Event-Dashboard
│   ├── member.php               # Teilnehmer-Detailseite
│   ├── admin.php                # Event-Admin UI
│   └── partials/
│       ├── header.php           # Gemeinsamer Header
│       ├── footer.php           # Gemeinsamer Footer
│       └── error.php            # Fehlerseite
├── assets/style.css             # Ergänzende Styles
├── exports/                     # Temporäre CSV-Exporte
├── .htaccess                    # Apache-Konfiguration
└── CHANGELOG.md                 # Versionshistorie
```

---

## URL-Struktur

| Seite | URL |
|---|---|
| Server-Admin | `admin.php?token={server_token}` |
| Event-Dashboard | `index.php?event={public_token}` |
| Teilnehmer-Detail | `index.php?event={public_token}&member={id}` |
| Event-Admin | `index.php?event={public_token}&admin={admin_token}` |
| Startseite (optional) | `index.php` |

---

## Lizenz

MIT License – siehe [LICENSE](LICENSE)
