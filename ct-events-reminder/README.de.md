# ChurchTools Erinnerung an offene Dienste

#### (Choose your language below / Wähle Deine Sprache)
[![English](https://img.shields.io/badge/Language-English-blue)](README.md)
[![Deutsch](https://img.shields.io/badge/Language-Deutsch-green)](README.de.md)
---

Durchsucht automatisch bevorstehende ChurchTools-Veranstaltungen nach unbesetzten Diensten und sendet Erinnerungs-E-Mails an **alle Mitglieder**, nicht nur an die Leiter, der zuständigen Dienstgruppen.

Das Skript ist für die regelmäßige Ausführung (z. B. per Cron) vorgesehen und informiert alle infrage kommenden Personen über offene Dienste, für die sie sich eintragen können.

---

# Funktionen

- Durchsucht einen oder mehrere ChurchTools-Kalender
- Sucht bevorstehende Veranstaltungen innerhalb eines konfigurierbaren Zeitraums
- Erkennt alle nicht angenommenen Dienste
- Konfigurierbarer Ausschluss von Dienstgruppen vom Erinnerungsversand
- Ermittelt alle möglichen Personen für jeden Dienst
- Gruppiert Erinnerungen pro Person
- Sendet **genau eine E-Mail pro Person** mit allen passenden offenen Diensten
- Unterstützt HTML-E-Mails mit Plain-Text-Fallback
- Berücksichtigt optional die ChurchTools-Einstellung **informLeader**
- Testmodus zum Versand ausschließlich an eine Testadresse
- Verwendet die ChurchTools REST-API mit API-Token

---

# Voraussetzungen

- PHP 7.4 oder neuer
- Konfigurierter Mailversand für PHP (`mail()`)
- Zugriff auf die ChurchTools REST-API
- API-Token mit folgenden Berechtigungen

```
churchcore:administer persons
churchdb:view
churchdb:view alldata(1,3,4)
churchdb:security level person(1)
churchservice:view
churchservice:view servicegroup(-1)
churchservice:edit servicegroup(-1)
churchservice:view events(-1)
```

---

# Installation

Kopiere folgende Dateien in ein gemeinsames Verzeichnis:

```text
ct_events_reminder.php
config.json
ct_mail_template.html
ct_mail_template.txt
```

Passe anschließend die Werte in `config.json` an.

Das Skript kann anschließend manuell mit

```bash
php ct_events_reminder.php
```

oder per Cron ausgeführt werden.

Beispiel:

```cron
0 8 * * * /usr/bin/php /path/to/ct_events_reminder.php
```

---

# Konfiguration

Alle Einstellungen werden in `config.json` gespeichert.

**Alle JSON-Beispiele, Konfigurationsoptionen, Platzhalter, Codeblöcke und Tabelleneinträge entsprechen dem englischen Original und bleiben unverändert.**\
Siehe dazu die englische [README](README.md#Configuration)

Die Bedeutung der Optionen:

- **accessToken** – Gemeinsames Geheimnis zum Schutz vor unbefugter Ausführung.
- **apiToken** – ChurchTools API-Anmeldetoken.
- **calendars** – Liste der zu durchsuchenden Kalender-IDs.
- **dateOffset** – Anzahl der Tage in der Zukunft, die durchsucht werden.
- **excludedServices** – Dienste, für die keine Erinnerungen versendet werden.
- **mailerSleep** – Wartezeit zwischen zwei E-Mails (Mikrosekunden).
- **checkInformLeader** – Berücksichtigt optional die Einstellung `informLeader`.
- **testMode** – Leitet alle E-Mails an die Testadresse um.
- **testEmail** – Empfängeradresse im Testmodus.
- **mailFrom** – Absender der E-Mail.
- **mailSubject** – Betreff der Erinnerungs-E-Mails.
- **timezone** – Zeitzone für die Datumsformatierung.
- **ctDomain** – ChurchTools-Domain für Links.
- **ctReplyMail** – Antwortadresse in den Mailvorlagen.
- **ctMailLogo** – Logo-URL für HTML-Mails.
- **ctMailTitle** – Titel in den Mailvorlagen.

---

# E-Mail-Vorlagen

Es werden zwei Vorlagen benötigt:

- `ct_mail_template.html` – HTML-Version der E-Mail
- `ct_mail_template.txt` – Plain-Text-Fallback

## Platzhalter

| Platzhalter | Beschreibung |
|-------------|--------------|
| `{{FIRSTNAME}}` | Vorname des Empfängers |
| `{{TIME}}` | Anzahl der geprüften Tage (`dateOffset`) |
| `{{EVENT_ROWS}}` | Generierte HTML-Tabelle mit offenen Diensten |
| `{{TITLE}}` | Titel aus `ctMailTitle` |
| `{{LOGO}}` | Logo-URL aus `ctMailLogo` |
| `{{DOMAIN}}` | ChurchTools-Domain |
| `{{REPLYMAIL}}` | Antwortadresse |

---

# Ablauf

1. Anmeldung bei ChurchTools
2. Durchsuchen kommender Veranstaltungen
3. Erkennen aller offenen Dienste
4. Ermitteln möglicher Personen
5. Optionales Filtern über `informLeader`
6. Gruppieren der Dienste pro Person
7. Versand genau einer Erinnerungs-E-Mail pro Person

---

# Laufzeit-Caches

Während der Ausführung werden folgende Caches verwendet:

- Person Cache
- Service Person Cache
- ChurchService Settings Cache
- ChurchTools Session Cookie

Diese werden nicht dauerhaft gespeichert.

---

# Testmodus

Ist

```json
"testMode": true
```

gesetzt, werden alle E-Mails an `testEmail` umgeleitet. Der ursprüngliche Empfänger wird dem Betreff angehängt.

---

# Sicherheit

Das Skript sollte niemals ohne Schutz öffentlich erreichbar sein.

Schütze HTTP-Aufrufe mit `accessToken`.

Veröffentliche niemals deinen `apiToken` und committe `config.json` nicht in öffentliche Repositories.

Es wird empfohlen, `config.json` zur `.gitignore` hinzuzufügen.

---

# Hinweise

Das Skript verwendet die ChurchTools REST-API.

Bei der ersten API-Anfrage erstellt ChurchTools ein Session-Cookie (`ChurchTools_ct_*`), das für nachfolgende Anfragen wiederverwendet wird, um die Performance zu verbessern.
