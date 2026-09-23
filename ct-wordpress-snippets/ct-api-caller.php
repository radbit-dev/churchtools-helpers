<?php


/**
 * Send a request to the ChurchTools API.
 *
 * @param string $url    API URL or path.
 * @param array  $data   Request data.
 * @param string $method HTTP method (GET, POST, PUT, DELETE, ...).
 *
 * @return array|string Decoded API response or "Error".
 */
function sendCalRequest(string $url, array $data = [], string $method = 'GET'): array|string
{
    // Add your token to wp-config.php:
    // define('CHURCHTOOLS_API_TOKEN', 'your-token');
    $apiToken = CHURCHTOOLS_API_TOKEN;

    // Get existing ChurchTools session cookie.
    $sessionCookie = get_transient('churchtools_session_cookie');

    // Parse and normalize URL.
    $url = unparse_url(parse_url($url));

    // If no session cookie exists, create one.
    if ($sessionCookie === false || empty($sessionCookie)) {
        $cookieContext = stream_context_create([
            'http' => [
                'header'  => 'Authorization: Login ' . $apiToken,
                'method'  => 'GET',
                'timeout' => 10,
            ],
        ]);

        $headers = get_headers($url, true, $cookieContext);

        if ($headers !== false && isset($headers['Set-Cookie'])) {
            setChurchToolsSessionCookie($headers['Set-Cookie']);
        }

        // Retrieve the newly created cookie.
        $sessionCookie = get_transient('churchtools_session_cookie');
    }

    // Build request headers.
    $headers = [
        'Authorization: Login ' . $apiToken,
        'Content-Type: application/x-www-form-urlencoded',
    ];

    if (!empty($sessionCookie) && is_string($sessionCookie)) {
        $headers[] = 'Cookie: ' . $sessionCookie;
    }

    // Set up HTTP request.
    $options = [
        'http' => [
            'header'        => implode("\r\n", $headers),
            'method'        => strtoupper($method),
            'content'       => http_build_query($data, '', '&'),
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($options);

    // Make HTTP request.
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        return 'Error';
    }

    // Decode JSON response.
    try {
        return json_decode(
            $result,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        return 'Error';
    }
}


/**
 * Extract ChurchTools session cookie and store it as a WordPress transient.
 *
 * @param string|array $cookies Set-Cookie header(s).
 *
 * @return bool True if the cookie was stored successfully.
 */
function setChurchToolsSessionCookie(string|array $cookies): bool
{
    // get_headers(..., true) can return multiple Set-Cookie headers as an array.
    if (is_array($cookies)) {
        // Use the first cookie containing an expiry date.
        foreach ($cookies as $cookie) {
            if (is_string($cookie) && preg_match(
                '/(?:^|;\s*)Expires=([^;]+)/i',
                $cookie,
                $matches
            )) {
                $cookies = $cookie;
                break;
            }
        }

        // If no suitable cookie was found, use the first string value.
        if (is_array($cookies)) {
            $cookies = reset($cookies);

            if (!is_string($cookies)) {
                return false;
            }
        }
    }

    // Extract Expires from cookie.
    $expires = 0;

    if (preg_match(
        '/(?:^|;\s*)Expires=([^;]+)/i',
        $cookies,
        $matches
    )) {
        $expires = strtotime(trim($matches[1]));

        if ($expires === false) {
            $expires = 0;
        }
    }

    // Refresh 5 minutes before expiry.
    $refreshBuffer = 300;

    if ($expires <= 0) {
        return false;
    }

    $transientLifetime = max(
        ($expires - time()) - $refreshBuffer,
        60
    );

    // Only store the actual cookie name=value part.
    $cookie = strtok($cookies, ';');

    if (!is_string($cookie) || $cookie === '') {
        return false;
    }

    return set_transient(
        'churchtools_session_cookie',
        $cookie,
        $transientLifetime
    );
}


/**
 * Normalize a parsed URL and add the default ChurchTools host if necessary.
 *
 * @param array|false $parsedUrl Result from parse_url().
 *
 * @return string
 */
function unparse_url(array|false $parsedUrl): string
{
    // Add your default CT host to wp-config.php:
    // define('CHURCHTOOLS_DEFAULT_HOST', 'your-host.church.tools');

    $parsedUrl = is_array($parsedUrl) ? $parsedUrl : [];

    $host = CHURCHTOOLS_DEFAULT_HOST;

    $scheme = $parsedUrl['scheme'] ?? 'https';
    $host   = $parsedUrl['host'] ?? $host;
    $path   = isset($parsedUrl['path'])
        ? '/' . ltrim($parsedUrl['path'], '/')
        : '';
    $query  = isset($parsedUrl['query'])
        ? '?' . $parsedUrl['query']
        : '';

    return $scheme . '://' . $host . $path . $query;
}

?>
