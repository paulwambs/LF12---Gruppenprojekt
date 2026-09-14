# Antragsprogramm Unterrichtsorganisation

Funktionale PHP/MySQL-Anwendung zum Klick-Mockup.

## Inbetriebnahme
1. `config.example.php` zu `config.php` kopieren (Standard: root ohne Passwort, für lokales XAMPP i.d.R. unverändert nutzbar).
2. In XAMPP **Apache** und **MySQL** starten.
3. Einmalig das Setup aufrufen: <http://localhost/Antragsprogramm/setup.php>
   (legt Abteilungen + Demo-Benutzer an; kann gefahrlos wiederholt werden).
4. Anwendung öffnen: <http://localhost/Antragsprogramm/> → Anmeldung.

Die Datenbank `antragsprogramm` samt Tabellen wird über `schema.sql` erzeugt
(bereits eingespielt). Zugangsdaten in `config.php` (siehe `config.example.php`,
Standard: root ohne Passwort). `config.php` ist lokal und wird nicht mit eingecheckt.

## Demo-Zugänge (Passwort jeweils `test1234`)
| Benutzer   | Rolle                | Abteilung      |
|------------|----------------------|----------------|
| mueller    | Lehrkraft            | Metalltechnik  |
| schmidt    | Lehrkraft            | Elektrotechnik |
| al_meier   | Abteilungsleiter     | Metalltechnik  |
| al_kraus   | Abteilungsleiter     | Elektrotechnik |
| stsl_wolf  | Stellv. Schulleiter  | –              |
| sl_becker  | Schulleiter          | –              |

## Prozess (laut Vorgaben)
1. Lehrkraft meldet sich an und wählt den **Anlass** (Punkt 1).
2. Je nach Anlass erscheint das passende Teilformular.
3. Absenden erzeugt den Antrag **und einen Log-Eintrag**.
4. Der **zuständige Abteilungsleiter** (gleiche Abteilung) sieht den Antrag,
   zeichnet ihn mit **doppelter Bestätigung** (Pop-up) ab → Log-Eintrag.
5. Danach erscheint er beim **stellv. Schulleiter** (doppelte Bestätigung) → Log-Eintrag.
6. Zuletzt **genehmigt** der **Schulleiter** → Log-Eintrag.

Der Antragsteller sieht den Stand jederzeit unter **„Meine Anträge“**
(Fortschrittsbalken + vollständiges Bearbeitungsprotokoll).
Ablehnen ist auf jeder Stufe möglich.

## Dateien
- `config.php` – DB-Zugangsdaten
- `db.php` – Verbindung, Session, Auth, CSRF, Beschriftungen, Workflow
- `schema.sql` – Datenbankschema
- `setup.php` – Demo-Daten anlegen
- `login.php` / `logout.php` – Anmeldung
- `index.php` – Übersicht (rollenabhängig)
- `neuer_antrag.php` – Antrag stellen
- `meine_antraege.php` – eigene Anträge + Status
- `bearbeitung.php` – Abzeichnen/Genehmigen (AL/StSL/SL)
- `antrag.php` – Detailansicht + Verlauf/Log
- `_header.php` / `_footer.php` / `assets/style.css` – Layout

Das ursprüngliche Mockup bleibt als `mockup_unterrichtsorganisation_v2.html` erhalten.
