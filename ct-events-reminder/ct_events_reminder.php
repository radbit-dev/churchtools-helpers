<?php

// Used only for performance tests, if needed then activate at the bottom of the script as well
//$time_start = microtime(true);

/*
******* DOCUMENTATION **********
ChurchTools:
    User access token 
    User access rights required:
        churchcore:administer persons
        churchdb:view
        churchdb:view alldata(1,3,4)
        churchdb:security level person(1)
        churchservice:view
        churchservice:view servicegroup(-1)
        churchservice:edit servicegroup(-1)
        churchservice:view events(-1)

Configured PHP Mail to send the mails

Scheduler to call this script (e.g. CronJob)

User config options considered in ChurchTools (if enabled in config.json with checkInformLeader):
    Offene Dienste: https://churchtools.academy/de/help/system-einstellungen/kommunikation-system-einstellungen/system-e-mails-im-ueberblick/
*/

/******** LOAD CONFIG ***********/
$configFile = __DIR__ . '/config.json';
if (!is_readable($configFile)) die('Configuration file not found: '.$configFile);
$config = json_decode(file_get_contents($configFile));
if (json_last_error() !== JSON_ERROR_NONE) die('Invalid config.json: '.json_last_error_msg());

$textTemplate = file_get_contents(__DIR__ . '/ct_mail_template.txt');
$htmlTemplate = file_get_contents(__DIR__ . '/ct_mail_template.html');
date_default_timezone_set($config->timezone);
/**********************************/

/** SET CACHES TO REDUCE API CALLS **/
$runtime = (object) [
    'personCache'        => [],
    'settingsCache'      => [],
    'servicePersonsCache'=> []
];
/***********************************/

/******** ACCESS TOKEN CHECK **********/
// Used if triggering the script remotely, set enabled and access token in config.json
if ($config->accessCheck) {
    if (!isset($_GET['token'])) {
        echo('Token not set!');
        exit();
    } else {
        if ($_GET['token'] !== $config->accessToken) {
            echo('Token missmatch!');
            exit();
        }
    }
}
/*************************************/

/************ RESPOND DIRECTLY (IF REQUIRED) **************/
/*
http_response_code(200);
echo "OK";

// Flush the response to the client
if (ob_get_level()) {
    ob_end_flush();
}
flush();

// Continue processing in the background
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
*/
/******************************************/

// Generic CT API Call function
function sendCalRequest($url, $data, $method) {
    
    global $config;
    static $sessionCookie;

	$options = array(
		'http'=>array(
			'header' => "Authorization: Login ".$config->apiToken
                . (!empty($sessionCookie)
                    ? "\r\nCookie: ".$sessionCookie
                    : ""),
			'method' => $method,
			'content' => http_build_query($data),
			'timeout' => 10,
		)
	);
    
    $context = stream_context_create($options);
    
    if (empty($sessionCookie)) {
        $getcookies = get_headers($url,1,$context);
        $sessionCookie = strtok($getcookies['set-cookie'], ';');
    }
    
    if ($result = file_get_contents($url, false, $context)) {
        $obj = json_decode($result, true);
        return $obj;
    } else {
        return "Error";
        exit;
    }
}

// API Call to get Person Data
function getPerson($personId) {
   
    global $config;
    
    $url = 'https://'.$config->ctDomain.'/api/persons/'.$personId;
    
	$result = sendCalRequest($url, array(), "GET");
	if ($result != "Error") {
        return $result['data'];
    }
}

// API Call to get the mail setting for the user
function getChurchServiceSettings($personId) {

     global $config;
    
    $url = 'https://'.$config->ctDomain.'/api/persons/'.$personId.'/settings/churchservice';

    $result = sendCalRequest($url, array(), "GET");

    if ($result != "Error") {
        return $result['data'];
    }

    return null;
}

// Check and set if Mail should be sent
function shouldSendReminder($personId)
{
    global $config;
    global $runtime;

    // Ignore the preference completely
    if (!$config->checkInformLeader) {
        return true;
    }

    if (!isset($runtime->settingsCache[$personId])) {
        $runtime->settingsCache[$personId] = getChurchServiceSettings($personId);
    }

    foreach ($runtime->settingsCache[$personId] as $setting) {

        if ($setting['attribute'] == 'informLeader') {
            return $setting['value'] == 1;
        }
    }

    // Setting not found
    return false;
}

// API Call to get all possible persons for the service groups
function getServicePersons($serviceId) {

     global $config;
    
    $url = 'https://'.$config->ctDomain.'/api/events/'.$serviceId.'/possiblepersonsforservice';
    
	$result = sendCalRequest($url, array(), "GET");
	if ($result != "Error") {
        
        $returnArray = array();

        foreach ($result['data'] as $baseArray) {
            $returnArray[] = array(
                'personId' => $baseArray['personId']
            );
        };
        return $returnArray;
    }
}

// API Call to get the Event Service name
function getEventServiceDetails() {

     global $config;
    
    $url = 'https://'.$config->ctDomain.'/api/event/masterdata';

    $result = sendCalRequest($url, array(), "GET");

    if ($result != "Error") {

        $services = array();

        foreach ($result['data']['services'] as $service) {
            $services[$service['id']] = $service['name'];
        }

        return $services;
    }

    return array();
}

// Main API Call to get all events in filter range
function scanEvents($calendars, $toDateOffset, $exclseriveId) {

	 global $config;
    
    $url = 'https://'.$config->ctDomain.'/api/events?'.'include=eventServices&'.'from='.date('Y-m-d').'&to='.date("Y-m-d", strtotime(date("Y-m-d").'+ '.$toDateOffset.' days'));
    
	$result = sendCalRequest($url, array(), "GET");
	if ($result != "Error") {

        $returnArray = array();

        foreach ($result['data'] as $baseArray) {

            if (!in_array($baseArray['calendar']['domainIdentifier'], $calendars)) {
                continue;
            }

            $event = array(
                'id' => $baseArray['id'],
                'name' => $baseArray['name'],
                'note' => $baseArray['note'],
                'startDate' => $baseArray['startDate'],
                'eventServices' => array()
            );

            foreach ($baseArray['eventServices'] as $serviceArray) {

                if (!$serviceArray['isAccepted']) {
                    if (!in_array($serviceArray['serviceId'], $exclseriveId)) {
                        $event['eventServices'][] = array(
                            'serviceId' => $serviceArray['serviceId']
                        );
                    }
                }
            }

            if (!empty($event['eventServices'])) {
                $returnArray[] = $event;
            }
        }

        return $returnArray;
    }
}

// Start by getting all events
$events = scanEvents($config->calendars, $config->dateOffset, $config->excludedServices);

// Get the details of the services for the events
$serviceDetails = getEventServiceDetails();

// Set the array for the persons to get the mail
$personReminders = array();

// Start looping through the events
foreach ($events as $event) {

    // Check is services exist
    if (empty($event['eventServices'])) {
        continue;
    }

    // Start looping through the services of the event
    foreach ($event['eventServices'] as $service) {

        // Call getServicePersons and check if cached and get service name
        if (!isset($runtime->servicePersonsCache[$service['serviceId']])) {
            $runtime->servicePersonsCache[$service['serviceId']] = getServicePersons($service['serviceId']);
        }
        $persons = $runtime->servicePersonsCache[$service['serviceId']];

        if (empty($persons)) {
            continue;
        }

        $serviceName = isset($serviceDetails[$service['serviceId']])
            ? $serviceDetails[$service['serviceId']]
            : 'Unknown Service';

        // Loop through persons and get person data
        foreach ($persons as $servicePerson) {

            $personId = $servicePerson['personId'];

            // Only request person details once
            if (!isset($personReminders[$personId])) {

                if (!isset($runtime->personCache[$personId])) {
                    $runtime->personCache[$personId] = getPerson($personId);
                }
                $person = $runtime->personCache[$personId];

                if (empty($person['email'])) {
                    continue;
                }

                if (!shouldSendReminder($personId)) {
                    continue;
                }

                $personReminders[$personId] = array(
                    'firstname' => $person['firstName'],
                    'email'     => $person['email'],
                    'events'    => array()
                );
            }

            $personReminders[$personId]['events'][] = array(
                'date'    => date('d.m.Y H:i', strtotime($event['startDate'])),
                'event'   => $event['name'],
                'service' => $serviceName
            );
        }
    }
}

$count = 0;
// Loop to put all data together and send mails
foreach ($personReminders as $person) {
    $count++;

    // set service entries for text mail
    $textrows = '';
    foreach ($person['events'] as $event) {
        $textrows .= sprintf(
            "- %s | %s | %s\r\n",
            $event['date'],
            $event['event'],
            $event['service']
        );
    }

    // set service entries for html mail
    $htmlrows = '';
    foreach ($person['events'] as $event) {
        $htmlrows .= '
            <tr style="border-top: 1px solid #e6e6e6; word-wrap:break-word">
            <td style="padding-left: 1%; padding-top: 1%; padding-bottom: 1%">'.$event['date'].' '.$event['event'].'
            <td style="padding-left: 3%; padding-top: 1%; padding-bottom: 1%">Dienst: '.$event['service'].'
            <font style="color:red">
            <b>?</b>
            </font>
        ';
    }

    // MailMessage Plain text version
    $textBody = str_replace(
        ['{{FIRSTNAME}}', '{{TIME}}', '{{EVENT_ROWS}}', '{{LOGO}}', '{{DOMAIN}}', '{{REPLYMAIL}}', '{{TITLE}}'],
        [$person['firstname'],  $config->dateOffset, $textrows, $config->ctMailLogo, $config->ctDomain, $config->ctReplyMail, $config->ctMailTitle],
        $textTemplate
    );

    // MailMessage HTML version
    $htmlBody = str_replace(
        ['{{FIRSTNAME}}', '{{TIME}}', '{{EVENT_ROWS}}', '{{LOGO}}', '{{DOMAIN}}', '{{REPLYMAIL}}', '{{TITLE}}'],
        [$person['firstname'],  $config->dateOffset, $htmlrows, $config->ctMailLogo, $config->ctDomain, $config->ctReplyMail, $config->ctMailTitle],
        $htmlTemplate
    );

    // Boundary for multipart mail
    $boundary = md5(uniqid(time()));

    // set Mail headers
    $headers = array(
        'MIME-Version: 1.0',
        'From: '.$config->mailFrom,
        'Content-Type: multipart/alternative; boundary="'.$boundary.'"'
    );

    // set recipient of mail
    $recipient = $person['email'];
    $mailSubject = $config->mailSubject;

    // check if test mode active and replace recipient and append subject
    if ($config->testMode) {
        $recipient = $config->testEmail;
        $mailSubject .= ' (original recipient: '.$person['email'].')';
    }

    // setup mail body
    $message  = "--".$boundary."\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textBody."\r\n\r\n";

    $message .= "--".$boundary."\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlBody."\r\n\r\n";

    $message .= "--".$boundary."--";

    // send mail
    mail(
        $recipient,
        $mailSubject,
        $message,
        implode("\r\n", $headers)
    );

    /*
    echo($recipient);
    echo("\n");
    echo($mailSubject);
    echo("\n");
    echo($message);
    echo("\n\n");
    */

    // slepp for ms before sending next mail
    usleep($config->mailerSleep);
}

// Show amount of recipients
//print_r($count);

/*
$time_end = microtime(true);
$execution_time = ($time_end - $time_start)/60;
echo '<b>Total Execution Time:</b> ' . $execution_time . ' Mins';
*/
?>
