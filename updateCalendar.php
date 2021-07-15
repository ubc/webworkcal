<?php
require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
	throw new Exception('This application must be run on the command line.');
}

$query = 'call assignment_due()';
$eventArray = array();

function splitCourseId($courseIdWithStudentCount) {
    $splitted = explode('-', $courseIdWithStudentCount, 2);
    return ctype_digit($splitted[0]) ? $splitted[1] : $courseIdWithStudentCount;
}

if (file_exists(__DIR__ . '/config.ini')) {
	if (!$config = parse_ini_file(__DIR__ . '/config.ini', true)) {
		throw new RuntimeException('Unable to open config.ini!');
	}
} else {
	$config['host'] = getenv('DB_HOST');
	$config['user'] = getenv('DB_USER');
	$config['password'] = getenv('DB_PASSWORD');
	$config['dbname'] = getenv('DB_NAME');
	$config['port'] = getenv('DB_PORT');
	$config['calendar'] = getenv('CALENDAR');
}

if ($config['host'] === false) {
	die ('Please set database parameters in config.ini or environment variables');
}
if ($config['calendar'] === false) {
	die ('Please set calender id in config.ini or environment variables');
}

$con = new mysqli($config['host'], $config['user'], $config['password'], $config['dbname'], $config['port'])
        or die ('Could not connect to the database server' . mysqli_connect_error());

$now = new DateTime();
if ($stmt = $con->prepare($query)) {
    $stmt->execute();
    $stmt->bind_result($field1, $field2, $open_date, $due_date, $studentCount);
    while ($stmt->fetch()) {
    	// ignore null date assignments
    	if ($open_date === null || $due_date === null) {
    		continue;
		}
    	// ignore the assignments start early than 3 months or end late than 6 months from now
		try {
			if ($now->diff(new DateTime($open_date))->format("%a") < 90
				&& $now->diff(new DateTime($due_date))->format("%a") < 180) {
				$eventArray[$field1 . '-' . $field2] = array(
					'open_date' => $open_date, 'due_date' => $due_date, 'students' => $studentCount);
			}
		} catch (Exception $e) {
			echo "Warning: invalid open ($open_date) or due ($due_date) date. Skipped $field2 assignment.";
		}
	}
    $stmt->close();
}

$con->close();

$client = new Google\Client();
$client->setApplicationName('WeBWorK_Calendar');
$client->useApplicationDefaultCredentials();
$client->addScope(Google\Service\Calendar::CALENDAR);

/* ------------------------- We are now properly authenticated ------------------- */

$cal = new Google\Service\Calendar($client);

// get the list of events since now, don't care about old events
$eventsObj = $cal->events->listEvents($config['calendar'], array('maxResults' => 999, 'timeMin' => date(DateTime::ATOM), 'singleEvents' => true));
$events = $eventsObj->getItems();

while ($eventsObj->getNextPageToken()) {
    $eventsObj = $cal->events->listEvents($config['calendar'], array(
        'pageToken' => $eventsObj->getNextPageToken(),
        'maxResults' => 999, 'timeMin' => date(DateTime::ATOM), 'singleEvents' => true)
    );
    $events[] = $eventsObj->getItems();
}

// Uncomment this when to remove all events from the calendar
//$eventArray = array();

// set default timezone to Vancouver as the dates from db query don't include timezone info
date_default_timezone_set('America/Vancouver');

// update events
foreach ($events as $event) {
    $courseId = splitCourseId($event->getSummary());
    if (array_key_exists($courseId, $eventArray)) {
        // if the time is different, change it
        $startTime = date(DateTime::ATOM, strtotime($eventArray[$courseId]['open_date']));
        $endTime = date(DateTime::ATOM, strtotime($eventArray[$courseId]['due_date']));
        $updated = false;
        if ($event->getStart()->getDateTime() !== $startTime) {
            $start = new Google\Service\Calendar\EventDateTime();
            $start->setDateTime($startTime);
            $event->setStart($start);
            $updated = true;
        }
		if ($event->getEnd()->getDateTime() !== $endTime) {
			$end = new Google\Service\Calendar\EventDateTime();
			$end->setDateTime($endTime);
			$event->setEnd($end);
			$updated = true;
		}
        if ($updated) {
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
    $event = new Google\Service\Calendar\Event();
    $event->setSummary($courseData['students'] . '-' . $key); /* what to do, summary of the appointment */
    $event->setLocation('UBC WeBWorK');

    /* Now, set the start date/time
     */
    $startTimestamp = strtotime($courseData['open_date']);
    $start = new Google\Service\Calendar\EventDateTime();
    $start->setDateTime(date(DateTime::ATOM, $startTimestamp));
    $event->setStart($start);

    /* Now, set the end date/time
     */
	$endTimestamp = strtotime($courseData['due_date']);
	$end = new Google\Service\Calendar\EventDateTime();
    $end->setDateTime(date(DateTime::ATOM, $endTimestamp));
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
