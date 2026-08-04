# Installationsanleitung für das WordPress PHP-Snippet

#### (Choose your language below / Wähle Deine Sprache)
[![English](https://img.shields.io/badge/Language-English-blue)](README.md)
[![Deutsch](https://img.shields.io/badge/Language-Deutsch-green)](README.de.md)
---

Diese Anleitung beschreibt, wie das bereitgestellte PHP-Skript als WordPress-Snippet eingebunden und die erforderlichen ChurchTools-API-Einstellungen konfiguriert werden.

## Voraussetzungen

- Administratorzugriff auf Ihre WordPress-Website.
- Das Plugin **Code Snippets** (oder ein vergleichbares Plugin zum Ausführen von PHP-Code) ist installiert und aktiviert.
- Die bereitgestellte Datei `ct-api-caller.php`.
- Ein gültiger ChurchTools API-Token.
- Der Hostname Ihrer ChurchTools-Instanz (z. B. `oa.church.tools` oder die Domain Ihrer selbst gehosteten Instanz).

---

# Schritt 1 – Plugin „Code Snippets“ installieren

1. Melden Sie sich im WordPress-Administrationsbereich an.
2. Navigieren Sie zu **Plugins → Installieren**.
3. Suchen Sie nach **Code Snippets**.
4. Installieren und aktivieren Sie das Plugin.

---

# Schritt 2 – Ein neues PHP-Snippet erstellen

1. Öffnen Sie **Snippets → Neu hinzufügen**.
2. Vergeben Sie einen Namen, zum Beispiel:

   ```
   ChurchTools API Helper
   ```

3. Öffnen Sie die bereitgestellte Datei `ct-api-caller.php`.
4. Kopieren Sie den gesamten PHP-Code aus der Datei.
5. Fügen Sie ihn in den Snippet-Editor ein.

> **Hinweis:**  
> Das Plugin **Code Snippets** akzeptiert ausschließlich PHP-Code. Falls der Editor bereits ein öffnendes PHP-Tag bereitstellt, entfernen Sie die `<?php`- und `?>`-Tags aus dem eingefügten Code. Andernfalls können Sie den Inhalt der Datei unverändert einfügen.

6. Stellen Sie den Ausführungsort auf **Überall ausführen** (Standardeinstellung).
7. Speichern und **aktivieren** Sie das Snippet.

---

# Schritt 3 – ChurchTools-Einstellungen konfigurieren

Das Snippet erwartet zwei Konstanten in der Datei `wp-config.php`:

- `CHURCHTOOLS_API_TOKEN` – Ihr ChurchTools API-Token.
- `CHURCHTOOLS_DEFAULT_HOST` – Der Standard-Host Ihrer ChurchTools-Instanz. Dieser wird verwendet, wenn in einer API-Anfrage kein Host angegeben ist.

Öffnen Sie die Datei `wp-config.php` Ihrer WordPress-Installation und fügen Sie die folgenden Zeilen **vor** der Zeile

```php
/* That's all, stop editing! Happy publishing. */
```

ein.

Ersetzen Sie die Beispielwerte durch Ihre eigenen:

```php
define('CHURCHTOOLS_API_TOKEN', 'ihr-api-token');
define('CHURCHTOOLS_DEFAULT_HOST', 'oa.church.tools');
```

Beispiel:

```php
define('CHURCHTOOLS_API_TOKEN', 'abc1234567890abcdefghijkl');
define('CHURCHTOOLS_DEFAULT_HOST', 'meinegemeinde.church.tools');
```

Das Snippet greift anschließend über die Konstanten auf diese Werte zu:

```php
$apiToken = CHURCHTOOLS_API_TOKEN;
$host = CHURCHTOOLS_DEFAULT_HOST;
```

Die Speicherung dieser Einstellungen in der `wp-config.php` wird empfohlen, weil:

- sensible Informationen nicht in der WordPress-Datenbank gespeichert werden,
- Konfigurationswerte vom Programmcode getrennt bleiben,
- Änderungen an Host oder API-Token ohne Anpassung des Snippets möglich sind,
- unterschiedliche Umgebungen (Entwicklung, Test, Produktion) einfach unterschiedliche Konfigurationen verwenden können.

---

# Funktionsweise des Skripts

Das Skript stellt drei Hilfsfunktionen bereit:

| Funktion | Beschreibung |
|----------|--------------|
| `sendCalRequest()` | Sendet authentifizierte HTTP-Anfragen an die ChurchTools-API. |
| `setChurchToolsSessionCookie()` | Speichert das ChurchTools-Session-Cookie als WordPress-Transient und erneuert es kurz vor Ablauf automatisch. |
| `unparse_url()` | Erstellt eine vollständige URL und ergänzt automatisch den konfigurierten Standard-Host sowie das HTTPS-Schema, falls diese nicht angegeben wurden. |

Zur Verbesserung der Performance wird das Session-Cookie in einem WordPress-Transient gespeichert und etwa **5 Minuten vor Ablauf** automatisch erneuert.

---

# Verwendung der Hilfsfunktion

Nach der Aktivierung des Snippets kann die Funktion `sendCalRequest()` überall innerhalb von WordPress verwendet werden.

> **Hinweis:**
> Stellen Sie sicher, dass Ihr Token-Benutzer in ChurchTools über die entsprechenden Zugriffsrechte verfügt.

## Funktionssignatur

```php
sendCalRequest($url, $data, $method);
```

### Parameter

| Parameter | Typ | Beschreibung |
|-----------|-----|--------------|
| `$url` | `string` | Die Ziel-URL oder ein relativer API-Pfad. Wird nur ein Pfad angegeben (z. B. `/api/persons`), ergänzt das Snippet automatisch das HTTPS-Schema sowie den in `CHURCHTOOLS_DEFAULT_HOST` konfigurierten Standard-Host. |
| `$data` | `array` | Ein assoziatives PHP-Array mit den zu übertragenden Parametern, typisch für POST requests. Die Daten werden automatisch URL-kodiert und als Request-Body an die API gesendet. Falls keine Daten erforderlich sind, kann ein leeres Array (`[]`) übergeben werden. |
| `$method` | `string` | Die HTTP-Methode der Anfrage, z. B. `GET`, `POST`, `PUT` oder `DELETE`. |

## Beispiele

### Vollständige URL verwenden

```php
$response = sendCalRequest(
    'https://meinegemeinde.church.tools/api/example',
    [
        'key' => 'value'
    ],
    'POST'
);
```

### Relativen API-Pfad verwenden

Wird kein Host angegeben, verwendet die Funktion automatisch den in `CHURCHTOOLS_DEFAULT_HOST` konfigurierten Standard-Host.

```php
$response = sendCalRequest(
    '/api/example',
    [
        'key' => 'value'
    ],
    'POST'
);
```

Die Funktion gibt die JSON-Antwort der API als assoziatives PHP-Array zurück.

---

# Hinweise

- Stellen Sie sicher, dass Ihr API-Token über die erforderlichen Berechtigungen für die verwendeten API-Endpunkte verfügt.
- Für API-Anfragen wird ein Timeout von **10 Sekunden** verwendet.
- Wird in einer Anfrage kein Host angegeben, verwendet das Skript automatisch den in `CHURCHTOOLS_DEFAULT_HOST` definierten Standard-Host.