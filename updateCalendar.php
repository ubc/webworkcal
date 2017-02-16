<?php
require_once __DIR__ . '/google-api/Google_Client.php';
require_once __DIR__ . '/google-api/contrib/Google_CalendarService.php';

$query = 'call assignment_due()';
$eventArray = array();

function splitCourseId($courseIdWithStudentCount) {
    $splitted = explode('-', $courseIdWithStudentCount, 2);
    return ctype_digit($splitted[0]) ? $splitted[1] : $courseIdWithStudentCount;
}

if (!$config = parse_ini_file(__DIR__ . '/config.ini', true)) {
    throw new RuntimeException('Unable to open config.ini!');
}

$con = new mysqli($config['host'], $config['user'], $config['password'], $config['dbname'], $config['port'])
        or die ('Could not connect to the database server' . mysqli_connect_error());

if ($stmt = $con->prepare($query)) {
    $stmt->execute();
    $stmt->bind_result($field1, $field2, $date, $studentCount);
    while ($stmt->fetch()) {
        $eventArray[$field1.'-'.$field2] = array('date' => $date, 'students' => $studentCount);
    }
    $stmt->close();
}

$con->close();

$apiConfig['oauth2_client_id'] = $config['oauth2_client_id'];
$apiConfig['oauth2_client_secret'] = $config['oauth2_client_secret'];
$apiConfig['developer_key'] = $config['developer_key'];

$client = new Google_Client();

/* Note: make sure to call $client->setUseObjects(true) if you want to see
 * objects returned instead of data (this example code uses objects)
 */
$client->setUseObjects(true);

/* Load the key in PKCS 12 format - remember: this is the file you had to
 * download when you created the Service account on the API console.
 */
if (dirname($config['key_file']) === '.') {
    // expend to full path
    $config['key_file'] = __DIR__ .'/'.$config['key_file'];
}
$key = file_get_contents($config['key_file']);
$client->setAssertionCredentials(new Google_AssertionCredentials(
    $config['service_account'],
    array('https://www.googleapis.com/auth/calendar'),
    $key)
);

/* ------------------------- We are now properly authenticated ------------------- */

$cal = new Google_CalendarService($client);

// get the list of events since now, don't care about old events
$eventsObj = $cal->events->listEvents($config['calendar'], array('maxResults' => 999, 'timeMin' => date(DateTime::ATOM), 'singleEvents' => true));
$events = $eventsObj->getItems();

//foreach ($events as $event) {
//    $cal->events->delete($config['calendar'], $event->getId());
//}

while ($eventsObj->getNextPageToken()) {
    $eventsObj = $cal->events->listEvents($config['calendar'], array(
        'pageToken' => $eventsObj->getNextPageToken(),
        'maxResults' => 999, 'timeMin' => date(DateTime::ATOM), 'singleEvents' => true)
    );
    $events[] = $eventsObj->getItems();
}

// update events
foreach ($events as $event) {
    $courseId = splitCourseId($event->getSummary());
    if (array_key_exists($courseId, $eventArray)) {
        // if the time is different, change it
        $startTime = date(DateTime::ATOM, strtotime($eventArray[$courseId]['date']));
        if ($event->getStart()->getDateTime() != $startTime) {
            $start = new Google_EventDateTime();
            $start->setDateTime($startTime);
            $event->setStart($start);
            $event->setEnd($start);
            $cal->events->update($config['calendar'], $event->getId(), $event);
            echo "Event ".$event->getSummary()." has been updated!\n";
        }
        // now two events are the same, we can remove it from array, so that it not get inserted again.
        unset($eventArray[$courseId]);
    } else {
        // delete the events
        $cal->events->delete($config['calendar'], $event->getId());
        echo "Event ".$event->getSummary()." has been deleted!\n";
    }
}

// the rest are the new events, we will insert them
foreach ($eventArray as $key => $courseData) {
    $event = new Google_Event();
    $event->setSummary($courseData['students'] . '-' . $key); /* what to do, summary of the appointment */
    $event->setLocation('UBC WeBWorK');

    /* Now, set the start date/time
     */
    $startTimestamp = strtotime($courseData['date']);
    $start = new Google_EventDateTime();
    $start->setDateTime(date(DateTime::ATOM, $startTimestamp));
    $event->setStart($start);

    /* Now, set the end date/time
     */
    $end = new Google_EventDateTime();
    $end->setDateTime(date(DateTime::ATOM, $startTimestamp));
    $event->setEnd($end);

    /* CREATE THE EVENT IN THE PROPER CALENDAR */
    try {
        $createdEvent = $cal->events->insert($config['calendar'], $event);
    } catch (Exception $e) {
        file_put_contents('php://stderr', 'Adding event failed! '.$e);
        continue;
    }
    echo 'Event '.$key." has been added!\n";
}

echo "Done.\n";
