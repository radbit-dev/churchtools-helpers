<?php

// main function to call the CT API with the required API Path and Method, which returns the supplied array.
function sendCalRequest($url, $data, $method) {
	
	// add your token to your wp-config.php: define('CHURCHTOOLS_API_TOKEN', 'your-token');
    $apiToken = CHURCHTOOLS_API_TOKEN;
	$sessionCookie = get_transient('churchtools_session_cookie');

	//set up http request
	$options = array(
		'http'=>array(
			'header' => "Authorization: Login ".$apiToken
			 . (!empty($sessionCookie)
                    ? "\r\nCookie: ".$sessionCookie
                    : ""),
			'method' => $method,
			'content' => http_build_query($data),
			'timeout' => 10,
		)
	);
	
	// setup stream context for http call and parse the url
	$context = stream_context_create($options);
	$url = unparse_url(parse_url($url));

	//check session cookie and (re)create if empty or expired
	if (false === ($sessionCookie = get_transient('churchtools_session_cookie'))) {
        $getcookies = get_headers($url,1,$context);
		setChurchToolsSessionCookie($getcookies['set-cookie']);
    }

	// make the http call and get / decode response
	if ($result = file_get_contents($url, false, $context)) {
		$obj = json_decode($result, true);
		return $obj;
	} else {
		return "Error";
		exit;
	}
}

//Extract CT Session Cookie and store as transient
function setChurchToolsSessionCookie($cookie) {

    // Extract Expires from cookie
    $expires = 0;
	if (preg_match('/(?:^|;\s*)Expires=([^;]+)/i', $cookie, $matches)) {
		$expires = strtotime(trim($matches[1]));
	}

    // Refresh 5 minutes before expiry
    $refreshBuffer = 300;

    $transientLifetime = max(
         ($expires - time()) - $refreshBuffer,
        60
    );
	
	//if expires extracted, then set transient with cookie
	if ($expires != 0) {
		$cookie = strtok($cookie, ';');
		set_transient(
			'churchtools_session_cookie',
			$cookie,
			$transientLifetime
		);
		return true;
	} else {
		return false;
	}

}

//Extract URL and check for scheme and host, replace if not provided with default
function unparse_url($parsed_url) {

	// add your default CT host to your wp-config.php: define('CHURCHTOOLS_DEFAULT_HOST', 'your-host.church.tools');
	$host = CHURCHTOOLS_DEFAULT_HOST;
	
	$scheme = $parsed_url['scheme'] ?? 'https';
    $host   = $parsed_url['host'] ?? $host;
    $path   = isset($parsed_url['path']) ? '/' . ltrim($parsed_url['path'], '/') : '';
    $query  = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    return $scheme . '://' . $host . $path . $query;
}

?>