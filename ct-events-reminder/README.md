# ChurchTools Open Services Reminder

#### (Choose your language below / Wähle Deine Sprache)
[![English](https://img.shields.io/badge/Language-English-blue)](README.md)
[![Deutsch](https://img.shields.io/badge/Language-Deutsch-green)](README.de.md)
---

Automatically scans upcoming ChurchTools events for unfilled services and sends reminder emails to **all members**, not just the leaders, in the responsible service groups.

The script is intended to be executed periodically (e.g. via cron) and informs all eligible persons about open services they can apply for.

---

# Features

- Scans one or more ChurchTools calendars
- Searches upcoming events within a configurable time period
- Detects all unaccepted services
- Configurable exclude of service groups from reminder mail
- Determines all possible persons for each service
- Groups reminders per person
- Sends **one email per person** listing all matching services
- Supports HTML emails with plain text fallback
- Respects the ChurchTools **informLeader** setting (optional)
- Test mode for sending only to a dedicated test address
- Uses the ChurchTools REST API with API Token

---

# Requirements

- PHP 7.4 or newer
- Mail transport configured for PHP (`mail()`)
- Access to the ChurchTools REST API
- API Token with following permissions
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

Copy the following files into one directory:

```
ct_events_reminder.php
config.json
ct_mail_template.html
ct_mail_template.txt
```

Adjust the values in `config.json`.

The script can then be executed manually

```bash
php ct_events_reminder.php
```

or via cron.

Example:

```cron
0 8 * * * /usr/bin/php /path/to/ct_events_reminder.php
```

---

# Configuration

All configuration is stored inside `config.json`.

Example:

```json
{
    "accessToken": "YOUR_SCRIPT_ACCESS_TOKEN",
    "apiToken": "YOUR_CHURCHTOOLS_API_TOKEN",

    "calendars": [1,2],

    "dateOffset": 60,

    "excludedServices": [],

    "mailerSleep": 30000,

    "checkInformLeader": true,

    "testMode": false,
    "testEmail": "test@example.com",

    "mailFrom": "Offener Abend <no-reply@example.com>",
    "mailSubject": "[CT] Open Services",

    "timezone": "Europe/Berlin",

    "ctDomain":"YOUR_CCHURCHTOOLS_DOMAIN",
    "ctReplyMail":"YOUR_CHURCHTOOLS_REPLY_MAIL",
    "ctMailLogo":"URL_TO_YOUR_LOGO",
    "ctMailTitle":"YOUR_TITLE_IN_THE_MAIL"
}
```

---

## Configuration Options

### accessToken

Shared secret protecting the script against unauthorized execution.

The token must be supplied as request parameter.

Example:

```
https://example.org/ct_events_reminder.php?token=YOUR_SCRIPT_ACCESS_TOKEN
```

---

### apiToken

ChurchTools API Login Token.

This token is used to authenticate all API requests.

---

### calendars

Array containing the calendar IDs that should be scanned.

Example:

```json
"calendars": [2,18,50]
```

---

### dateOffset

Number of days into the future that should be scanned.

Example:

```json
"dateOffset": 60
```

---

### excludedServices

Array containing service IDs that should never generate reminder emails.

Example:

```json
"excludedServices": [4,7]
```

---

### mailerSleep

Delay in microseconds between sending two emails.

Used to avoid triggering SMTP rate limits.

Example:

```json
"mailerSleep": 30000
```

---

### checkInformLeader

If enabled, the script checks every person's ChurchTools setting

```
informLeader
```

Only persons with this option enabled receive reminder emails.

Values:

```
true
false
```

If set to

```
false
```

the setting is ignored and every eligible person receives an email.

---

### testMode

If enabled, all emails are redirected to the configured test address.

No productive emails are sent.

Values:

```
true
false
```

---

### testEmail

Recipient used while test mode is enabled.

Example:

```json
"testEmail": "john@example.com"
```

---

### mailFrom

Sender shown in the email.

Example:

```json
"mailFrom": "Your Church <no-reply@example.com>"
```

---

### mailSubject

Subject line of the reminder emails.

Example:

```json
"mailSubject": "[CT] Offene Dienste"
```

---

### timezone

Timezone used when formatting event dates.

Recommended:

```json
"timezone": "Europe/Berlin"
```

---

### ctDomain

Your ChurchTools domain used for the links in the mail templates. Do not add protocol or paths.

Recommended:

```json
"ctDomain": "your-church-name.church.tools"
```

---

### ctReplyMail

Your reply to mail which is incl. in the mail templates. This is NOT the sender adress which is configured seperately.

Recommended:

```json
"ctReplyMail": "test@example.com"
```

---

### ctMailLogo

Your logo URL to be incl. in the mail html template

Recommended:

```json
"ctMailLogo": "http://your-domain.com/path-to-your-logo.png?your-options=your-value"
```

---

### ctMailTitle

Title text used in the mail templates

Recommended:

```json
"ctMailTitle": "Your Church Name"
```

---

# Email Templates

Two templates are required.

## HTML version

```
ct_mail_template.html
```

Contains the HTML version of the email.

---

## Plain text version

```
ct_mail_template.txt
```

Contains the plain text fallback.

---

## Available placeholders for both templates:

| Placeholder | Description |
|------------|-------------|
| `{{FIRSTNAME}}` | Recipient's first name |
| `{{TIME}}` | Number of scanned days, set in config.json:dateOffset   |
| `{{EVENT_ROWS}}` | Generated HTML table of open services |
| `{{TITLE}}` | The overall title set for the template, set in config.json:ctMailTitle |
| `{{LOGO}}` | URL to the image to be used in the mail template, set in config.json:ctMailLogo |
| `{{DOMAIN}}` | Your ChurchTools domain to be replaced in the links in the template, set in config.json:ctDomain |
| `{{REPLYMAIL}}` | Your reply to mail incl. in the template, set in config.json:ctReplyMail |

---

# Runtime Behaviour

The script performs the following steps:

1. Authenticate against ChurchTools
2. Scan upcoming events
3. Detect all unaccepted services
4. Determine all possible persons for each service
5. Filter recipients using `informLeader` (optional)
6. Group all open services per person
7. Send exactly one reminder email per person with all relevant open services

---

# Runtime Caches

For performance reasons several caches exist only during script execution.

- Person Cache
- Service Person Cache
- ChurchService Settings Cache
- ChurchTools Session Cookie

These caches are not persisted and are recreated every execution.

---

# Test Mode

When

```json
"testMode": true
```

all reminder emails are redirected to

```json
"testEmail"
```

The original recipient is appended to the subject for easier verification.

---

# Security

The script should never be publicly callable without protection.

Use

```
accessToken
```

to protect HTTP execution.

Never publish your

```
apiToken
```

or commit `config.json` into a public repository.

It is recommended to add

```
config.json
```

to `.gitignore`.

---

# Notes

The script uses the ChurchTools REST API.

During the first API request, ChurchTools creates a session cookie (`ChurchTools_ct_*`) which is reused for all subsequent requests to improve performance.

---