<?php
declare(strict_types=1);

/**
 * Strip the leading "<student count>-" from an event summary, giving the
 * "<course>-<set>" key used to match calendar events against assignments.
 */
function splitCourseId(string $courseIdWithStudentCount): string
{
    $splitted = explode('-', $courseIdWithStudentCount, 2);

    return count($splitted) === 2 && ctype_digit($splitted[0]) ? $splitted[1] : $courseIdWithStudentCount;
}

/**
 * Whether two date-time strings denote the same instant.
 *
 * Google returns event times normalised to the calendar's time zone (for
 * example "2026-09-21T06:59:00Z"), so comparing that string with our own
 * ATOM-formatted local time ("2026-09-20T23:59:00-07:00") is never equal even
 * when nothing changed. Compare the instants instead. Unparseable or missing
 * values never compare equal, so a broken event still gets rewritten.
 */
function isSameInstant(?string $a, ?string $b): bool
{
    if ($a === null || $b === null) {
        return false;
    }
    $ta = strtotime($a);
    $tb = strtotime($b);

    return $ta !== false && $tb !== false && $ta === $tb;
}
