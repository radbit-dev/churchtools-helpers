# WordPress PHP Snippet Installation Guide

#### (Choose your language below / Wähle Deine Sprache)
[![English](https://img.shields.io/badge/Language-English-blue)](README.md)
[![Deutsch](https://img.shields.io/badge/Language-Deutsch-green)](README.de.md)
---

This guide explains how to add the provided PHP script as a WordPress snippet and configure the required ChurchTools API settings.

## Prerequisites

- Administrator access to your WordPress website.
- The **Code Snippets** plugin installed and activated (or another plugin that allows execution of custom PHP snippets).
- The provided `ct-api-caller.php` script.
- A valid ChurchTools API token.
- Your ChurchTools host name (e.g. `oa.church.tools` or your self-hosted domain).

---

# Step 1 – Install the Code Snippets Plugin

1. Log in to your WordPress Admin Dashboard.
2. Navigate to **Plugins → Add New**.
3. Search for **Code Snippets**.
4. Install and activate the plugin.

---

# Step 2 – Create a New PHP Snippet

1. Go to **Snippets → Add New**.
2. Enter a name, for example:

   ```
   ChurchTools API Helper
   ```

3. Open the provided `ct-api-caller.php` file.
4. Copy **only the PHP code** from the file.
5. Paste it into the snippet editor.

> **Important:**  
> The Code Snippets plugin accepts PHP code only. If the editor already provides a PHP opening tag, remove the `<?php` and `?>` tags before pasting. Otherwise, paste the file exactly as provided.

6. Set the snippet to run **Everywhere** (default option).
7. Save and **Activate** the snippet.

---

# Step 3 – Configure the ChurchTools Settings

The snippet expects two constants to be defined in your site's `wp-config.php` file:

- `CHURCHTOOLS_API_TOKEN` – your ChurchTools API token.
- `CHURCHTOOLS_DEFAULT_HOST` – the default ChurchTools host that is used whenever no host is supplied in an API URL.

Open your site's `wp-config.php` file and add the following lines **before** the line:

```php
/* That's all, stop editing! Happy publishing. */
```

Replace the example values with your own.

```php
define('CHURCHTOOLS_API_TOKEN', 'your-api-token');
define('CHURCHTOOLS_DEFAULT_HOST', 'oa.church.tools');
```

Example:

```php
define('CHURCHTOOLS_API_TOKEN', 'abc1234567890abcdefghijkl');
define('CHURCHTOOLS_DEFAULT_HOST', 'mychurch.church.tools');
```

The snippet reads these values using the constants:

```php
$apiToken = CHURCHTOOLS_API_TOKEN;
$host = CHURCHTOOLS_DEFAULT_HOST;
```

Storing these settings in `wp-config.php` is recommended because:

- sensitive information is not stored in the WordPress database,
- configuration is separated from the application code,
- settings can be changed without editing the snippet,
- different environments (development, staging, production) can use different hosts and API tokens.

---

# How the Script Works

The helper provides three functions:

| Function | Purpose |
|----------|---------|
| `sendCalRequest()` | Sends authenticated HTTP requests to the ChurchTools API. |
| `setChurchToolsSessionCookie()` | Stores the ChurchTools session cookie in a WordPress transient until shortly before it expires. |
| `unparse_url()` | Builds a complete URL and automatically inserts the configured default host and HTTPS scheme when they are not provided. |

To improve performance, the script caches the session cookie in a WordPress transient and automatically refreshes it approximately **5 minutes before expiration**.

---

# Using the Helper

After activating the snippet, the `sendCalRequest()` function can be used anywhere within WordPress.

> **Important:**
> Be sure to have the appropriate access rights in ChurchTools for your token user.

## Function Signature

```php
sendCalRequest($url, $data, $method);
```

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$url` | `string` | The target URL or a relative API path. If only a path is provided (e.g. `/api/persons`), the snippet automatically prepends the HTTPS scheme and the default host configured in `CHURCHTOOLS_DEFAULT_HOST`. |
| `$data` | `array` | An associative PHP array containing the request parameters, typically for POST requests. The data is automatically URL-encoded and sent as the request body. If no data is required, pass an empty array (`[]`). |
| `$method` | `string` | The HTTP request method, such as `GET`, `POST`, `PUT`, or `DELETE`. |

## Examples

### Using a Full URL

```php
$response = sendCalRequest(
    'https://mychurch.church.tools/api/example',
    [
        'key' => 'value'
    ],
    'POST'
);
```

### Using a Relative API Path

If no host is specified, the function automatically uses the host configured in `CHURCHTOOLS_DEFAULT_HOST`.

```php
$response = sendCalRequest(
    '/api/example',
    [
        'key' => 'value'
    ],
    'POST'
);
```

### Sending a GET Request Without Parameters

```php
$response = sendCalRequest(
    '/api/persons',
    [],
    'GET'
);
```

The function returns the ChurchTools API response as a decoded JSON object in the form of a PHP associative array.

---

# Notes

- Ensure the API token has sufficient permissions for the API endpoints you intend to use.
- The helper uses a timeout of **10 seconds** for API requests.
- When no host is supplied in the request URL, the value configured in `CHURCHTOOLS_DEFAULT_HOST` is used automatically.